from fastapi import FastAPI
from pydantic import BaseModel
import pickle
import joblib
import nltk
from fastapi.middleware.cors import CORSMiddleware

# SBERT evaluation engine
from sbert_engine import evaluate_prediction

nltk.download('punkt')

app = FastAPI()

# -----------------------------
# Enable CORS
# -----------------------------
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# -----------------------------
# Load Models
# -----------------------------

# Sentiment model
with open("sentiment_model.pkl", "rb") as f:
    sentiment_pipeline = pickle.load(f)

# ML dataset model
ml_model = joblib.load("ml_prediction_model.pkl")


# -----------------------------
# Request Schemas
# -----------------------------

class PredictionRequest(BaseModel):
    story: str
    prediction: str


class Comment(BaseModel):
    text: str


# -----------------------------
# Home API
# -----------------------------

@app.get("/")
def home():
    return {"message": "Unified AI Prediction API running"}


# -----------------------------
# ML Feature Extraction
# -----------------------------

def extract_ml_features(story, prediction):

    story_words = set(story.lower().split())
    pred_words = set(prediction.lower().split())

    common_words = story_words.intersection(pred_words)

    story_length = len(story_words)
    prediction_length = len(pred_words)

    common_ratio = len(common_words) / max(prediction_length, 1)

    return [
        story_length,
        prediction_length,
        common_ratio
    ]


# -----------------------------
# STORY EVALUATION API
# -----------------------------

@app.post("/evaluate-story")
def evaluate_story(data: PredictionRequest):

    try:

        if not data.story or not data.prediction:
            return {
                "verdict": "Invalid input",
                "scores": {
                    "sbert_score": 0,
                    "ml_dataset_score": 0
                },
                "best_sentence": ""
            }

        # -------- SBERT ENGINE --------
        verdict, ai_score, best_sentence = evaluate_prediction(
            data.story,
            data.prediction
        )

        # -------- ML DATASET MODEL --------
        ml_features = extract_ml_features(
            data.story,
            data.prediction
        )

        ml_score = ml_model.predict([ml_features])[0]
        ml_score = round(float(ml_score), 2)

        return {

            "verdict": verdict,

            "scores": {
                "sbert_score": round(float(ai_score), 2),
                "ml_dataset_score": ml_score
            },

            "best_sentence": best_sentence
        }

    except Exception as e:

        print("ERROR:", str(e))

        return {
            "verdict": "Server error",
            "scores": {
                "sbert_score": 0,
                "ml_dataset_score": 0
            },
            "best_sentence": ""
        }


# -----------------------------
# SENTIMENT API
# -----------------------------

@app.post("/sentiment-predict")
def sentiment_predict(comment: Comment):

    probs = sentiment_pipeline.predict_proba([comment.text])[0]

    label_index = probs.argmax()

    class_names = [
        "Negative",
        "Neutral",
        "Positive"
    ]

    return {

        "sentiment": class_names[label_index],

        "sentiment_score": float(probs[label_index])

    }