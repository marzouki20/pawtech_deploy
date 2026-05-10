from flask import Flask, request, jsonify
from flask_cors import CORS
import numpy as np
import pandas as pd
from sklearn.neighbors import NearestNeighbors
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.model_selection import cross_val_score, train_test_split
from sklearn.neighbors import KNeighborsClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score
from datetime import datetime
import os
import joblib

app = Flask(__name__)
CORS(app)

# ============================================================
# DATASET LOADING AND MODEL TRAINING
# ============================================================

# Load the dataset
DATASET_PATH = os.path.join(os.path.dirname(__file__), 'event_preferences_dataset.csv')
print(f"\n[LOADING] Dataset from: {DATASET_PATH}")

try:
    dataset = pd.read_csv(DATASET_PATH)
    print(f"[SUCCESS] Loaded {len(dataset)} user preference records")
    print(f"[INFO] Columns: {list(dataset.columns)}")
except Exception as e:
    print(f"[ERROR] Failed to load dataset: {e}")
    dataset = pd.DataFrame()

# Encoders for categorical features
EVENT_TYPES = ['VACCINATION', 'ADOPTION', 'SENSIBILISATION', 'COLLECTE_DONS', 'any']
CITIES = ['Tunis', 'Ariana', 'Ben Arous', 'Sousse', 'Sfax', 'Nabeul', 'Bizerte', 'Monastir', 'any']
TIMEFRAMES = ['this_week', 'this_month', 'next_month', 'anytime']
GROUP_SIZES = ['small', 'medium', 'large']

type_encoder = LabelEncoder()
type_encoder.fit(EVENT_TYPES)

city_encoder = LabelEncoder()
city_encoder.fit(CITIES)

timeframe_encoder = LabelEncoder()
timeframe_encoder.fit(TIMEFRAMES)

size_encoder = LabelEncoder()
size_encoder.fit(GROUP_SIZES)

def encode_preferences(pref_type, pref_city, pref_timeframe, pref_size):
    """Convert user preferences to numeric feature vector"""
    try:
        type_val = type_encoder.transform([pref_type])[0]
    except:
        type_val = type_encoder.transform(['any'])[0]
    
    try:
        city_val = city_encoder.transform([pref_city])[0]
    except:
        city_val = city_encoder.transform(['any'])[0]
    
    try:
        time_val = timeframe_encoder.transform([pref_timeframe])[0]
    except:
        time_val = timeframe_encoder.transform(['this_month'])[0]
    
    try:
        size_val = size_encoder.transform([pref_size])[0]
    except:
        size_val = size_encoder.transform(['medium'])[0]
    
    return [type_val, city_val, time_val, size_val]

# ============================================================
# TRAIN KNN MODEL ON DATASET
# ============================================================

knn_model = None
dataset_features = None
dataset_labels = None

def train_model():
    """Train KNN model on the historical preference dataset"""
    global knn_model, dataset_features, dataset_labels
    
    if dataset.empty:
        print("[WARNING] No dataset available for training")
        return False
    
    print("\n[TRAINING] Building KNN model from dataset...")
    
    # Prepare features from dataset
    features = []
    labels = []
    
    for _, row in dataset.iterrows():
        feature_vector = encode_preferences(
            row['preferred_type'],
            row['preferred_city'],
            row['preferred_timeframe'],
            row['group_size']
        )
        features.append(feature_vector)
        labels.append({
            'liked_type': row['liked_event_type'],
            'satisfaction': row['satisfaction_score']
        })
    
    dataset_features = np.array(features)
    dataset_labels = labels
    
    # Train KNN model
    knn_model = NearestNeighbors(n_neighbors=min(10, len(features)), metric='euclidean')
    knn_model.fit(dataset_features)
    
    print(f"[SUCCESS] KNN model trained on {len(features)} records")
    print(f"[INFO] Feature dimensions: {dataset_features.shape}")
    return True

# Train model on startup
model_trained = train_model()

# ============================================================
# RECOMMENDATION LOGIC
# ============================================================

def find_similar_users(user_preferences):
    """Find similar users from dataset using KNN"""
    if knn_model is None:
        return [], []
    
    user_vector = encode_preferences(
        user_preferences.get('preferred_type', 'any'),
        user_preferences.get('preferred_city', 'any'),
        user_preferences.get('preferred_timeframe', 'this_month'),
        user_preferences.get('group_size', 'medium')
    )
    
    user_point = np.array([user_vector])
    distances, indices = knn_model.kneighbors(user_point)
    
    return distances[0], indices[0]

def get_preferred_types_from_similar_users(indices, distances):
    """Analyze what event types similar users liked"""
    type_scores = {}
    
    for i, idx in enumerate(indices):
        label = dataset_labels[idx]
        liked_type = label['liked_type']
        satisfaction = label['satisfaction']
        
        # Weight by distance (closer = more weight) and satisfaction
        distance_weight = 1 / (1 + distances[i])
        score = distance_weight * satisfaction
        
        if liked_type in type_scores:
            type_scores[liked_type] += score
        else:
            type_scores[liked_type] = score
    
    # Normalize scores
    total = sum(type_scores.values())
    if total > 0:
        type_scores = {k: v/total for k, v in type_scores.items()}
    
    return type_scores

def score_event(event, type_preferences, user_preferences):
    """Score an event based on type preferences and user criteria"""
    event_type = event.get('type', 'ADOPTION')
    event_city = event.get('ville', '')
    
    # Base score from similar users' preferences (50% weight)
    type_score = type_preferences.get(event_type, 0.1)
    
    # City match bonus (25% weight)
    preferred_city = user_preferences.get('preferred_city', 'any')
    if preferred_city == 'any' or preferred_city == event_city:
        city_score = 1.0
    else:
        city_score = 0.3
    
    # Date preference score (25% weight)
    date_str = event.get('date_debut', '')
    timeframe = user_preferences.get('preferred_timeframe', 'anytime')
    date_score = calculate_date_score(date_str, timeframe)
    
    # Combined score
    final_score = (0.50 * type_score) + (0.25 * city_score) + (0.25 * date_score)
    
    return final_score

def calculate_date_score(event_date_str, preferred_timeframe):
    """Calculate score based on date preference"""
    try:
        event_date = datetime.strptime(event_date_str, '%Y-%m-%d')
        today = datetime.now()
        days_until = (event_date - today).days
        
        if days_until < 0:
            return 0
        
        if preferred_timeframe == 'this_week':
            return max(0, 1 - (days_until / 7))
        elif preferred_timeframe == 'this_month':
            return max(0, 1 - (days_until / 30))
        elif preferred_timeframe == 'next_month':
            if 30 <= days_until <= 60:
                return 1
            return max(0, 1 - abs(45 - days_until) / 45)
        else:
            return max(0, 1 - (days_until / 365))
    except:
        return 0.5

# ============================================================
# DONOR PROPENSITY MODEL
# ============================================================

DONOR_DATASET_PATH = os.path.join(os.path.dirname(__file__), 'donor_dataset.csv')
print(f"\n[LOADING] Donor dataset from: {DONOR_DATASET_PATH}")

try:
    donor_dataset = pd.read_csv(DONOR_DATASET_PATH)
    print(f"[SUCCESS] Loaded {len(donor_dataset)} donor records")
except Exception as e:
    print(f"[ERROR] Failed to load donor dataset: {e}")
    donor_dataset = pd.DataFrame()

# Donor prediction model
donor_model = None
donor_scaler = None

def train_donor_model():
    """Train Logistic Regression model for donor propensity prediction"""
    global donor_model, donor_scaler
    
    if donor_dataset.empty:
        print("[WARNING] No donor dataset available for training")
        return False
    
    print("\n[TRAINING] Building Donor Propensity model...")
    
    # Features for prediction
    feature_columns = [
        'total_participations',
        'donation_events', 
        'charity_events',
        'adoption_events',
        'vaccination_events',
        'days_since_last',
        'avg_satisfaction'
    ]
    
    X = donor_dataset[feature_columns].values
    y = donor_dataset['donated'].values
    
    # Scale features for better model performance
    donor_scaler = StandardScaler()
    X_scaled = donor_scaler.fit_transform(X)
    
    # Train Logistic Regression model
    donor_model = LogisticRegression(random_state=42, max_iter=1000)
    donor_model.fit(X_scaled, y)
    
    # Calculate training accuracy
    accuracy = donor_model.score(X_scaled, y)
    print(f"[SUCCESS] Donor model trained with {accuracy*100:.2f}% accuracy")
    
    return True

# Train donor model on startup
donor_model_trained = train_donor_model()

def predict_donor_propensity(user_stats):
    """Predict donor propensity for a user based on their stats"""
    if donor_model is None or donor_scaler is None:
        return None
    
    features = np.array([[
        user_stats.get('total_participations', 0),
        user_stats.get('donation_events', 0),
        user_stats.get('charity_events', 0),
        user_stats.get('adoption_events', 0),
        user_stats.get('vaccination_events', 0),
        user_stats.get('days_since_last', 365),
        user_stats.get('avg_satisfaction', 0)
    ]])
    
    features_scaled = donor_scaler.transform(features)
    
    # Get probability of being a donor (class 1)
    probability = donor_model.predict_proba(features_scaled)[0][1]
    
    return probability

def get_donor_category(propensity_score):
    """Categorize user based on propensity score"""
    if propensity_score >= 0.7:
        return "High Potential"
    elif propensity_score >= 0.4:
        return "Medium"
    else:
        return "Low"

# ============================================================
# API ENDPOINTS
# ============================================================

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'healthy',
        'service': 'Event Recommendation API',
        'algorithm': 'KNN (K-Nearest Neighbors)',
        'model_trained': model_trained,
        'dataset_records': len(dataset) if not dataset.empty else 0
    })

@app.route('/dataset/info', methods=['GET'])
def dataset_info():
    """Get information about the training dataset"""
    if dataset.empty:
        return jsonify({'ok': False, 'message': 'No dataset loaded'})
    
    return jsonify({
        'ok': True,
        'total_records': len(dataset),
        'columns': list(dataset.columns),
        'event_types': dataset['liked_event_type'].value_counts().to_dict(),
        'cities': dataset['preferred_city'].value_counts().to_dict(),
        'avg_satisfaction': round(dataset['satisfaction_score'].mean(), 3)
    })

@app.route('/model/accuracy', methods=['GET'])
def model_accuracy():
    """
    Calculate model accuracy using cross-validation
    This tests: Can we predict what event type a user will like based on their preferences?
    """
    if dataset.empty:
        return jsonify({'ok': False, 'message': 'No dataset loaded'})
    
    try:
        # Prepare features (user preferences) and labels (what they liked)
        X = []
        y = []
        
        for _, row in dataset.iterrows():
            feature_vector = encode_preferences(
                row['preferred_type'],
                row['preferred_city'],
                row['preferred_timeframe'],
                row['group_size']
            )
            X.append(feature_vector)
            y.append(row['liked_event_type'])
        
        X = np.array(X)
        y = np.array(y)
        
        # Encode labels for classification
        label_enc = LabelEncoder()
        y_encoded = label_enc.fit_transform(y)
        
        # Get the k value from query parameter (default 5)
        k = request.args.get('k', default=5, type=int)
        k = min(k, len(X) - 1)  # Can't have k >= n_samples
        
        # Split data: 80% train, 20% test
        X_train, X_test, y_train, y_test = train_test_split(
            X, y_encoded, test_size=0.2, random_state=42
        )
        
        # Train KNN classifier
        knn_classifier = KNeighborsClassifier(n_neighbors=k, metric='euclidean')
        knn_classifier.fit(X_train, y_train)
        
        # Predict on test set
        y_pred = knn_classifier.predict(X_test)
        
        # Calculate metrics
        accuracy = accuracy_score(y_test, y_pred)
        
        # Cross-validation (5-fold)
        cv_scores = cross_val_score(knn_classifier, X, y_encoded, cv=5)
        
        # Try different k values to find best
        k_results = []
        for test_k in [3, 5, 7, 10, 15]:
            if test_k < len(X):
                knn_temp = KNeighborsClassifier(n_neighbors=test_k)
                cv_temp = cross_val_score(knn_temp, X, y_encoded, cv=5)
                k_results.append({
                    'k': test_k,
                    'accuracy': round(cv_temp.mean() * 100, 2)
                })
        
        # Find best k
        best_k = max(k_results, key=lambda x: x['accuracy']) if k_results else {'k': k, 'accuracy': accuracy * 100}
        
        return jsonify({
            'ok': True,
            'current_k': k,
            'accuracy': round(accuracy * 100, 2),
            'cross_validation': {
                'folds': 5,
                'mean_accuracy': round(cv_scores.mean() * 100, 2),
                'std_deviation': round(cv_scores.std() * 100, 2),
                'fold_scores': [round(s * 100, 2) for s in cv_scores]
            },
            'k_comparison': k_results,
            'best_k': best_k,
            'dataset_size': len(dataset),
            'test_size': len(X_test),
            'tips_to_improve': [
                'Add more data to the dataset (current: {} records)'.format(len(dataset)),
                'Best k value appears to be: {}'.format(best_k['k']),
                'Add more features (e.g., age, past attendance)',
                'Balance the dataset (equal samples per event type)',
                'Collect real user feedback data'
            ]
        })
        
    except Exception as e:
        return jsonify({'ok': False, 'message': str(e)}), 500

@app.route('/recommend', methods=['POST'])
def recommend_events():
    """
    Main recommendation endpoint
    
    1. Takes user preferences
    2. Finds similar users in the trained dataset using KNN
    3. Analyzes what event types those similar users liked
    4. Scores available events based on this analysis
    5. Returns top recommendations
    """
    try:
        data = request.get_json()
        
        if not data:
            return jsonify({'ok': False, 'message': 'No JSON data provided'}), 400
        
        user_preferences = data.get('user_preferences', {})
        events = data.get('events', [])
        top_n = data.get('top_n', 5)
        
        print(f"\n[REQUEST] New recommendation request")
        print(f"[INPUT] User preferences: {user_preferences}")
        print(f"[INPUT] Available events: {len(events)}")
        
        if not events:
            return jsonify({'ok': False, 'message': 'No events provided'}), 400
        
        # Step 1: Find similar users in dataset
        distances, indices = find_similar_users(user_preferences)
        print(f"[KNN] Found {len(indices)} similar users in dataset")
        
        # Step 2: Analyze what event types similar users liked
        type_preferences = get_preferred_types_from_similar_users(indices, distances)
        print(f"[ANALYSIS] Type preferences from similar users: {type_preferences}")
        
        # Step 3: Score each event
        event_scores = []
        for event in events:
            # Filter out past events
            date_str = event.get('date_debut', '')
            try:
                event_date = datetime.strptime(date_str, '%Y-%m-%d')
                if event_date < datetime.now():
                    continue
            except:
                pass
            
            score = score_event(event, type_preferences, user_preferences)
            event_scores.append((event['id'], score))
        
        # Step 4: Sort and get top N
        event_scores.sort(key=lambda x: x[1], reverse=True)
        top_recommendations = event_scores[:top_n]
        
        recommended_ids = [eid for eid, _ in top_recommendations]
        scores = [round(score, 3) for _, score in top_recommendations]
        
        print(f"[RESULT] Top {len(recommended_ids)} recommendations: {recommended_ids}")
        print(f"[RESULT] Scores: {scores}")
        
        return jsonify({
            'ok': True,
            'recommendations': recommended_ids,
            'scores': scores,
            'algorithm': 'KNN',
            'similar_users_found': len(indices),
            'type_preferences': type_preferences
        })
        
    except Exception as e:
        print(f"[ERROR] {str(e)}")
        return jsonify({'ok': False, 'message': str(e)}), 500

# ============================================================
# DONOR PROPENSITY ENDPOINTS
# ============================================================

@app.route('/donor/predict', methods=['POST'])
def predict_donor():
    """
    Predict donor propensity for a single user
    
    Input: {
        "total_participations": 5,
        "donation_events": 2,
        "charity_events": 1,
        "adoption_events": 1,
        "vaccination_events": 1,
        "days_since_last": 15,
        "avg_satisfaction": 4.2
    }
    
    Output: {
        "propensity_score": 78.5,
        "category": "High Potential",
        "recommendation": "Include in donation event invitations"
    }
    """
    try:
        data = request.get_json()
        
        if not data:
            return jsonify({'ok': False, 'message': 'No JSON data provided'}), 400
        
        print(f"\n[DONOR PREDICTION] Input: {data}")
        
        propensity = predict_donor_propensity(data)
        
        if propensity is None:
            return jsonify({'ok': False, 'message': 'Donor model not trained'}), 500
        
        score_percent = round(propensity * 100, 1)
        category = get_donor_category(propensity)
        
        # Generate recommendation based on category
        if category == "High Potential":
            recommendation = "Priority: Include in all donation event invitations"
        elif category == "Medium":
            recommendation = "Consider: Send personalized donation event invites"
        else:
            recommendation = "Nurture: Focus on engagement before donation asks"
        
        result = {
            'ok': True,
            'propensity_score': score_percent,
            'category': category,
            'recommendation': recommendation,
            'factors': {
                'total_participations': data.get('total_participations', 0),
                'donation_events': data.get('donation_events', 0),
                'days_since_last': data.get('days_since_last', 0)
            }
        }
        
        print(f"[DONOR PREDICTION] Result: {score_percent}% - {category}")
        
        return jsonify(result)
        
    except Exception as e:
        print(f"[ERROR] Donor prediction failed: {str(e)}")
        return jsonify({'ok': False, 'message': str(e)}), 500

@app.route('/donor/predict-batch', methods=['POST'])
def predict_donor_batch():
    """
    Predict donor propensity for multiple users
    
    Input: {
        "users": [
            {"user_id": 1, "total_participations": 5, ...},
            {"user_id": 2, "total_participations": 2, ...}
        ]
    }
    """
    try:
        data = request.get_json()
        users = data.get('users', [])
        
        if not users:
            return jsonify({'ok': False, 'message': 'No users provided'}), 400
        
        print(f"\n[DONOR BATCH] Processing {len(users)} users")
        
        results = []
        for user in users:
            propensity = predict_donor_propensity(user)
            
            if propensity is not None:
                score_percent = round(propensity * 100, 1)
                category = get_donor_category(propensity)
                
                results.append({
                    'user_id': user.get('user_id'),
                    'propensity_score': score_percent,
                    'category': category
                })
        
        # Sort by propensity score (highest first)
        results.sort(key=lambda x: x['propensity_score'], reverse=True)
        
        # Summary stats
        high_potential = len([r for r in results if r['category'] == 'High Potential'])
        medium = len([r for r in results if r['category'] == 'Medium'])
        low = len([r for r in results if r['category'] == 'Low'])
        
        return jsonify({
            'ok': True,
            'total_users': len(results),
            'predictions': results,
            'summary': {
                'high_potential': high_potential,
                'medium': medium,
                'low': low
            }
        })
        
    except Exception as e:
        print(f"[ERROR] Batch prediction failed: {str(e)}")
        return jsonify({'ok': False, 'message': str(e)}), 500

@app.route('/donor/model-info', methods=['GET'])
def donor_model_info():
    """Get information about the donor propensity model"""
    if donor_dataset.empty:
        return jsonify({'ok': False, 'message': 'No donor dataset loaded'})
    
    # Calculate model accuracy with cross-validation
    if donor_model is not None:
        feature_columns = [
            'total_participations', 'donation_events', 'charity_events',
            'adoption_events', 'vaccination_events', 'days_since_last', 'avg_satisfaction'
        ]
        X = donor_dataset[feature_columns].values
        y = donor_dataset['donated'].values
        X_scaled = donor_scaler.transform(X)
        
        cv_scores = cross_val_score(donor_model, X_scaled, y, cv=5)
        accuracy = round(cv_scores.mean() * 100, 2)
    else:
        accuracy = 0
    
    return jsonify({
        'ok': True,
        'model': 'Logistic Regression',
        'dataset_records': len(donor_dataset),
        'accuracy': accuracy,
        'features': [
            'total_participations',
            'donation_events',
            'charity_events', 
            'adoption_events',
            'vaccination_events',
            'days_since_last',
            'avg_satisfaction'
        ],
        'categories': {
            'High Potential': '>= 70%',
            'Medium': '40-69%',
            'Low': '< 40%'
        }
    })

# ============================================================
# START SERVER
# ============================================================

if __name__ == '__main__':
    print("\n" + "=" * 60)
    print("  PAWTECH AI SERVICES API")
    print("  Author: Nesrine Fendri")
    print("=" * 60)
    
    print("\n  [1] EVENT RECOMMENDATION (KNN)")
    print(f"      Dataset: {len(dataset)} records")
    print(f"      Status: {'Ready' if model_trained else 'Not trained'}")
    
    print("\n  [2] DONOR PROPENSITY (Logistic Regression)")
    print(f"      Dataset: {len(donor_dataset)} records")
    print(f"      Status: {'Ready' if donor_model_trained else 'Not trained'}")
    
    print("\n  Endpoints:")
    print("    GET  /health              - Health check")
    print("    GET  /dataset/info        - Event dataset info")
    print("    GET  /model/accuracy      - Event model accuracy")
    print("    POST /recommend           - Get event recommendations")
    print("    ---")
    print("    POST /donor/predict       - Predict single user")
    print("    POST /donor/predict-batch - Predict multiple users")
    print("    GET  /donor/model-info    - Donor model info")
    
    print("\n  Server: http://127.0.0.1:8003")
    print("=" * 60 + "\n")
    
    app.run(host='127.0.0.1', port=8003, debug=True)
