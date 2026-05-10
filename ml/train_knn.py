import argparse
import csv
import os
from typing import List, Dict

import joblib
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.model_selection import train_test_split
from sklearn.neighbors import KNeighborsClassifier
from sklearn.preprocessing import LabelEncoder


def read_dataset(path: str) -> List[Dict[str, str]]:
    rows: List[Dict[str, str]] = []
    with open(path, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f, delimiter=";")
        for row in reader:
            rows.append(row)
    if not rows:
        raise ValueError("Dataset is empty.")
    return rows


def _normalize_affected_parts(value: str) -> str:
    raw = (value or "").strip()
    if raw == "":
        return ""
    separators = [",", "|", ";"]
    for sep in separators:
        raw = raw.replace(sep, " ")
    return " ".join(raw.split())


def build_text(row: Dict[str, str]) -> str:
    affected_parts = _normalize_affected_parts(row.get("affected_parts", ""))
    consultation_type = row.get("consultation_type", "").strip()
    diagnostic = row.get("diagnostic", "").strip()
    treatment = row.get("treatment", "").strip()
    symptoms = row.get("symptoms", "").strip()

    # Keep the same feature strategy as prediction for stable behavior across future retrains.
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


def main() -> None:
    parser = argparse.ArgumentParser(description="Train veterinary KNN model.")
    parser.add_argument(
        "--dataset",
        default=os.path.join("ml", "data", "vet_knn_dataset.csv"),
        help="Path to semicolon-separated CSV dataset.",
    )
    parser.add_argument(
        "--output",
        default=os.path.join("var", "ml", "vet_knn_model.joblib"),
        help="Output path for trained model bundle.",
    )
    parser.add_argument("--neighbors", type=int, default=5, help="k neighbors")
    args = parser.parse_args()

    rows = read_dataset(args.dataset)

    texts = [build_text(r) for r in rows]
    y_emergency_raw = [r["emergency_level"].strip() for r in rows]
    y_condition_raw = [r["condition_label"].strip() for r in rows]

    vectorizer = TfidfVectorizer(ngram_range=(1, 2), min_df=1)
    X = vectorizer.fit_transform(texts)

    emergency_encoder = LabelEncoder()
    condition_encoder = LabelEncoder()
    y_emergency = emergency_encoder.fit_transform(y_emergency_raw)
    y_condition = condition_encoder.fit_transform(y_condition_raw)

    # Use split for quick sanity metrics.
    X_train, X_test, y_em_train, y_em_test, y_co_train, y_co_test = train_test_split(
        X, y_emergency, y_condition, test_size=0.2, random_state=42, stratify=y_emergency
    )

    k = max(1, min(args.neighbors, X_train.shape[0]))
    # Eval models on split
    eval_emergency_model = KNeighborsClassifier(n_neighbors=k, weights="distance", metric="cosine")
    eval_condition_model = KNeighborsClassifier(n_neighbors=k, weights="distance", metric="cosine")
    eval_emergency_model.fit(X_train, y_em_train)
    eval_condition_model.fit(X_train, y_co_train)

    em_acc = eval_emergency_model.score(X_test, y_em_test) if X_test.shape[0] > 0 else 1.0
    co_acc = eval_condition_model.score(X_test, y_co_test) if X_test.shape[0] > 0 else 1.0

    # Final models on full dataset (needed for consistent neighbors mapping)
    emergency_model = KNeighborsClassifier(n_neighbors=k, weights="distance", metric="cosine")
    condition_model = KNeighborsClassifier(n_neighbors=k, weights="distance", metric="cosine")
    emergency_model.fit(X, y_emergency)
    condition_model.fit(X, y_condition)

    bundle = {
        "vectorizer": vectorizer,
        "emergency_model": emergency_model,
        "condition_model": condition_model,
        "emergency_encoder": emergency_encoder,
        "condition_encoder": condition_encoder,
        "train_rows": rows,  # same order as full X used for final model fit
        "text_builder_version": 1,
    }

    os.makedirs(os.path.dirname(args.output), exist_ok=True)
    joblib.dump(bundle, args.output)

    print(f"Model saved to: {args.output}")
    print(f"Emergency accuracy: {em_acc:.3f}")
    print(f"Condition accuracy: {co_acc:.3f}")
    print(f"Neighbors (k): {k}")
    print(f"Samples: {len(rows)}")


if __name__ == "__main__":
    main()
