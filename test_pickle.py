import pickle

with open("sentiment_model.pkl", "rb") as f:
    data = pickle.load(f)

vectorizer = data["vectorizer"]
model = data["model"]

text = "This story is amazing"
vector = vectorizer.transform([text])
prediction = model.predict(vector)

print("Prediction:", prediction)

