from text_cleaner import TextCleaner
import pickle
from fastapi import FastAPI
from pydantic import BaseModel

with open("./sentiment_model.pkl", "rb") as f:
    pipeline = pickle.load(f)

class Comment(BaseModel):
    text: str

@app.post("/predict")
def predict(comment: Comment):
    probs = pipeline.predict_proba([comment.text])[0]
    label_index = probs.argmax()

    class_names = ["Negative", "Neutral", "Positive"]

    sentiment = class_names[label_index]
    score = float(probs[label_index])

    return {
        "sentiment": sentiment,
        "sentiment_score": score
    }



#uvicorn sentiment_api:app --reload