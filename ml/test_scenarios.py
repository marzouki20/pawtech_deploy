import json
import subprocess
import sys
from pathlib import Path


SCENARIOS = [
    {
        "name": "routine_stomach_low",
        "payload": {
            "task": "predict",
            "affected_parts": ["stomach"],
            "consultation_type": "Routine",
            "diagnostic": "follow-up stable, no anomaly",
            "treatment": "continue routine treatment",
            "symptoms": "follow-up stable no anomaly continue routine treatment mild appetite fluctuation",
        },
        "expected_levels": {"low"},
    },
    {
        "name": "emergency_lungs_critical",
        "payload": {
            "task": "predict",
            "affected_parts": ["lungs"],
            "consultation_type": "Emergency",
            "diagnostic": "acute respiratory distress",
            "treatment": "oxygen support immediately",
            "symptoms": "cannot breathe cyanosis collapse acute respiratory distress",
        },
        "expected_levels": {"critical"},
    },
    {
        "name": "general_stomach_medium",
        "payload": {
            "task": "predict",
            "affected_parts": ["stomach"],
            "consultation_type": "General",
            "diagnostic": "mild gastric irritation",
            "treatment": "antiemetic and bland diet",
            "symptoms": "vomiting nausea mild abdominal discomfort",
        },
        "expected_levels": {"medium", "high"},
    },
]


def run_predict(project_root: Path, payload: dict) -> dict:
    script = project_root / "ml" / "predict_knn.py"
    cmd = ["py", str(script)] if sys.platform.startswith("win") else ["python3", str(script)]
    proc = subprocess.run(
        cmd,
        input=json.dumps(payload),
        text=True,
        capture_output=True,
        cwd=str(project_root),
    )
    if proc.returncode != 0:
        raise RuntimeError(proc.stderr.strip() or proc.stdout.strip())
    return json.loads(proc.stdout.strip())


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    passed = 0
    for scenario in SCENARIOS:
        result = run_predict(root, scenario["payload"])
        level = str(result.get("emergency_level", ""))
        ok = level in scenario["expected_levels"]
        status = "PASS" if ok else "FAIL"
        print(f"[{status}] {scenario['name']} -> {level}")
        if not ok:
            print("  raw:", result)
        else:
            passed += 1

    print(f"Summary: {passed}/{len(SCENARIOS)} scenarios passed")
    if passed != len(SCENARIOS):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
