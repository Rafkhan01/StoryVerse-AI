from fastapi import FastAPI
from pydantic import BaseModel
import pickle
import nltk
from fastapi.middleware.cors import CORSMiddleware
from sbert_engine2 import evaluate_prediction

nltk.download('punkt')

app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

with open("sentiment_model.pkl", "rb") as f:
    sentiment_pipeline = pickle.load(f)

class StoryRequest(BaseModel):
    story: str
    prediction: str

class Comment(BaseModel):
    text: str

@app.get("/")
def home():
    return {"message": "Unified AI API is running!"}

@app.post("/story-predict")
async def story_predict(data: dict):
    try:
        story = data.get("story", "")
        prediction = data.get("prediction", "")

        if not story or not prediction:
            return {
                "verdict": "Invalid input",
                "confidence": 0,
                "matched_story_part": ""
            }

        result = evaluate_prediction(story, prediction)

        # 🔥 SAFETY CHECK
        if result is None:
            return {
                "verdict": "No match",
                "confidence": 0,
                "matched_story_part": ""
            }

        return {
            "verdict": result[0],
            "confidence": result[1],
            "matched_story_part": result[2]
        }


    except Exception as e:
        print("ERROR:", str(e))

        return {
            "verdict": "Server error",
            "confidence": 0,
            "matched_story_part": ""
        }

@app.post("/sentiment-predict")
def sentiment_predict(comment: Comment):
    probs = sentiment_pipeline.predict_proba([comment.text])[0]
    label_index = probs.argmax()

    class_names = ["Negative", "Neutral", "Positive"]

    return {
        "sentiment": class_names[label_index],
        "sentiment_score": float(probs[label_index])
    }
#uvicorn mainv1:app --reload