from fastapi import FastAPI
from pydantic import BaseModel
import joblib

# SBERT evaluation engine
from sbert_engine import evaluate_prediction

app = FastAPI()

# Load ML dataset model
ml_model = joblib.load("ml_prediction_model.pkl")


# Request schema
class PredictionRequest(BaseModel):
    story: str
    prediction: str


# ML feature extractor (independent of SBERT)
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


@app.post("/evaluate")
def evaluate(data: PredictionRequest):

    # -------- SYSTEM 1 : SBERT ENGINE --------
    verdict, ai_score, best_sentence = evaluate_prediction(
        data.story,
        data.prediction
    )

    # -------- SYSTEM 2 : ML DATASET MODEL --------
    ml_features = extract_ml_features(data.story, data.prediction)

    ml_score = ml_model.predict([ml_features])[0]
    ml_score = round(float(ml_score), 2)

    # -------- RESPONSE --------
    return {

        "verdict": verdict,

        "scores": {
            "sbert_score": round(float(ai_score), 2),
            "ml_dataset_score": ml_score
        },

        "best_sentence": best_sentence
    }