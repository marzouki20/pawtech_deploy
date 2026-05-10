import json
import os
import re
import sys
from typing import Any, Dict, List
from collections import defaultdict

import joblib

LEVEL_RANK = {"low": 0, "medium": 1, "high": 2, "critical": 3}

CRITICAL_KEYWORDS = [
    "acute decompensation",
    "cardiac decompensation",
    "collapse",
    "cannot breathe",
    "respiratory distress",
    "severe breathing distress",
    "cyanosis",
    "blue gums",
    "seizure",
    "seizures",
    "convulsion",
    "coma",
    "unconscious",
    "shock",
]

HIGH_KEYWORDS = [
    "dyspnea",
    "breathing difficulty",
    "chest pain",
    "tachycardia",
    "arrhythmia",
    "blood in stool",
    "blood in urine",
    "dehydration",
    "repeated vomiting",
    "persistent vomiting",
    "severe weakness",
]

NEGATION_PREFIXES = ("no ", "without ", "not ", "denies ")


def _safe_text(value: Any) -> str:
    return str(value or "").strip().lower()


def _normalize_affected_parts(value: Any) -> str:
    if isinstance(value, list):
        parts = [str(x).strip().lower() for x in value if str(x).strip()]
        return " ".join(parts)
    raw = _safe_text(value)
    for sep in [",", "|", ";"]:
        raw = raw.replace(sep, " ")
    return " ".join(raw.split())


def _contains_keyword(text: str, keyword: str) -> bool:
    needle = keyword.lower().strip()
    if needle == "":
        return False
    if needle not in text:
        return False
    # Basic negation handling to reduce false alerts (e.g. "no collapse").
    idx = text.find(needle)
    start = max(0, idx - 20)
    context = text[start:idx]
    for neg in NEGATION_PREFIXES:
        if context.endswith(neg):
            return False
    return True


def _raise_level(base_level: str, min_level: str) -> str:
    base = _safe_text(base_level)
    floor = _safe_text(min_level)
    if LEVEL_RANK.get(base, 0) >= LEVEL_RANK.get(floor, 0):
        return base
    return floor


def build_text(payload: Dict[str, Any]) -> str:
    affected_parts = _normalize_affected_parts(payload.get("affected_parts", []))
    symptoms = str(payload.get("symptoms", "")).strip()
    consultation_type = str(payload.get("consultation_type", "")).strip()
    diagnostic = str(payload.get("diagnostic", "")).strip()
    treatment = str(payload.get("treatment", "")).strip()
    # Repeat context fields to increase their influence in TF-IDF matching.
    return " ".join(
        [
            affected_parts,
            consultation_type,
            consultation_type,
            diagnostic,
            diagnostic,
            treatment,
            symptoms,
        ]
    ).strip()


def build_symptoms_text_from_neighbors(train_rows: List[Dict[str, Any]], indices: List[int]) -> str:
    chunks: List[str] = []
    for idx in indices:
        raw = str(train_rows[int(idx)].get("symptoms", ""))
        for part in raw.split(","):
            clean = part.strip()
            if clean and clean not in chunks:
                chunks.append(clean)
            if len(chunks) >= 8:
                break
        if len(chunks) >= 8:
            break
    return ", ".join(chunks)


def weighted_vote(labels: List[str], distances: List[float]) -> Dict[str, Any]:
    scores = defaultdict(float)
    for label, distance in zip(labels, distances):
        weight = 1.0 / (float(distance) + 1e-6)
        scores[label] += weight
    if not scores:
        return {"label": "", "confidence": 0.0}
    best_label = max(scores, key=scores.get)
    total = sum(scores.values())
    confidence = (scores[best_label] / total) if total > 0 else 0.0
    return {"label": best_label, "confidence": float(confidence)}


def calibrate_emergency_level(payload: Dict[str, Any], predicted_level: str) -> str:
    consultation_type = _safe_text(payload.get("consultation_type", ""))
    diagnostic = _safe_text(payload.get("diagnostic", ""))
    treatment = _safe_text(payload.get("treatment", ""))
    symptoms = _safe_text(payload.get("symptoms", ""))
    affected = _normalize_affected_parts(payload.get("affected_parts", []))
    full_text = " ".join([consultation_type, diagnostic, treatment, symptoms, affected]).strip()

    critical_hits = sum(1 for kw in CRITICAL_KEYWORDS if _contains_keyword(full_text, kw))
    high_hits = sum(1 for kw in HIGH_KEYWORDS if _contains_keyword(full_text, kw))

    calibrated = _safe_text(predicted_level or "low")

    # Strong safety floor for acute-critical signals.
    if critical_hits > 0:
        calibrated = _raise_level(calibrated, "critical")
    elif consultation_type in {"emergency", "urgent", "icu"} and high_hits > 0:
        calibrated = _raise_level(calibrated, "high")
    elif consultation_type in {"emergency", "urgent"} and high_hits >= 2:
        calibrated = _raise_level(calibrated, "high")

    # Additional generic guardrail: emergency + cardio-respiratory acute terms => critical.
    if consultation_type in {"emergency", "urgent"}:
        acute_terms = ["acute", "decompensation", "cardiac", "respiratory distress", "cannot breathe"]
        if sum(1 for term in acute_terms if _contains_keyword(full_text, term)) >= 3:
            calibrated = _raise_level(calibrated, "critical")

    return calibrated


def predict(payload: Dict[str, Any]) -> Dict[str, Any]:
    model_path = payload.get("model_path") or os.path.join("var", "ml", "vet_knn_model.joblib")
    if not os.path.exists(model_path):
        return {"success": False, "error": f"Model file not found: {model_path}"}

    bundle = joblib.load(model_path)
    vectorizer = bundle["vectorizer"]
    emergency_model = bundle["emergency_model"]
    condition_model = bundle["condition_model"]
    emergency_encoder = bundle["emergency_encoder"]
    condition_encoder = bundle["condition_encoder"]

    task = str(payload.get("task", "predict")).strip().lower()
    text = build_text(payload)
    if not text:
        return {"success": False, "error": "Empty input for prediction."}

    x = vectorizer.transform([text])

    if task == "suggest_symptoms":
        if not hasattr(emergency_model, "kneighbors"):
            return {"success": False, "error": "KNN model does not support neighbors lookup."}
        k = min(max(3, int(getattr(emergency_model, "n_neighbors", 5))), len(bundle["train_rows"]))
        distances, indices = emergency_model.kneighbors(x, n_neighbors=k)
        idx_list = [int(i) for i in indices[0]]
        dist_list = [float(d) for d in distances[0]]

        # Build symptoms from the dominant emergency class among closest neighbors.
        em_labels = [str(bundle["train_rows"][idx].get("emergency_level", "")) for idx in idx_list]
        em_vote = weighted_vote(em_labels, dist_list)
        dominant_level = str(em_vote["label"] or "").strip()
        filtered_indices = [idx for idx in idx_list if str(bundle["train_rows"][idx].get("emergency_level", "")) == dominant_level]
        if not filtered_indices:
            filtered_indices = idx_list
        symptoms = build_symptoms_text_from_neighbors(bundle["train_rows"], filtered_indices)
        neighbors = []
        for distance, idx in zip(distances[0], indices[0]):
            row = bundle["train_rows"][int(idx)]
            neighbors.append(
                {
                    "distance": float(distance),
                    "emergency_level": row.get("emergency_level", ""),
                    "condition_label": row.get("condition_label", ""),
                    "symptoms": row.get("symptoms", ""),
                }
            )
        return {
            "success": True,
            "symptoms": symptoms,
            "dominant_emergency_level": dominant_level,
            "neighbors": neighbors,
            "source": "knn",
        }

    # Weighted vote from KNN neighbors for emergency and condition.
    em_k = min(max(3, int(getattr(emergency_model, "n_neighbors", 5))), len(bundle["train_rows"]))
    co_k = min(max(3, int(getattr(condition_model, "n_neighbors", 5))), len(bundle["train_rows"]))
    em_distances, em_indices = emergency_model.kneighbors(x, n_neighbors=em_k)
    co_distances, co_indices = condition_model.kneighbors(x, n_neighbors=co_k)

    em_labels = [str(bundle["train_rows"][int(idx)].get("emergency_level", "")) for idx in em_indices[0]]
    co_labels = [str(bundle["train_rows"][int(idx)].get("condition_label", "")) for idx in co_indices[0]]
    em_vote = weighted_vote(em_labels, [float(d) for d in em_distances[0]])
    co_vote = weighted_vote(co_labels, [float(d) for d in co_distances[0]])

    emergency_level = em_vote["label"] or "low"
    condition_label = co_vote["label"] or "unknown"
    em_proba = float(em_vote["confidence"])
    co_proba = float(co_vote["confidence"])
    emergency_level = calibrate_emergency_level(payload, str(emergency_level))

    neighbors = []
    distances, indices = emergency_model.kneighbors(x, n_neighbors=min(3, len(bundle["train_rows"])))
    for distance, idx in zip(distances[0], indices[0]):
        row = bundle["train_rows"][int(idx)]
        neighbors.append(
            {
                "distance": float(distance),
                "emergency_level": row.get("emergency_level", ""),
                "condition_label": row.get("condition_label", ""),
                "symptoms": row.get("symptoms", ""),
            }
        )

    return {
        "success": True,
        "emergency_level": str(emergency_level),
        "condition_label": str(condition_label),
        "emergency_confidence": round(em_proba, 4),
        "condition_confidence": round(co_proba, 4),
        "neighbors": neighbors,
        "source": "knn",
    }


def main() -> None:
    raw = sys.stdin.read().strip().lstrip("\ufeff")
    if raw.startswith("ï»¿"):
        raw = raw[3:]
    if not raw:
        print(json.dumps({"success": False, "error": "No JSON input provided."}))
        return

    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        print(json.dumps({"success": False, "error": "Invalid JSON input."}))
        return

    result = predict(payload)
    print(json.dumps(result, ensure_ascii=True))


if __name__ == "__main__":
    main()
