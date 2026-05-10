# KNN Veterinary Module

This module trains and runs a local KNN model for:
- emergency level prediction (`low`, `medium`, `high`, `critical`)
- probable condition label

## 1. Install Python deps

```bash
pip install -r ml/requirements.txt
```

## 2. Train model

```bash
python ml/train_knn.py
```

Model output:
- `var/ml/vet_knn_model.joblib`

## 3. Quick predict test

```bash
echo {"symptoms":"cough wheezing rapid breathing","affected_parts":["lungs"],"consultation_type":"Pulmonary"} | python ml/predict_knn.py
```

## Notes

- Dataset file: `ml/data/vet_knn_dataset.csv`
- Delimiter is `;`
- Symfony service uses this predictor script automatically when model exists.
