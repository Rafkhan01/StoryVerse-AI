import pandas as pd
import joblib
from sklearn.ensemble import RandomForestRegressor
from db_connection import engine


def extract_features(story, prediction):

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


# load dataset
df = pd.read_sql(
    '''SELECT 
        sp.content AS story,
        ads.prediction_text AS prediction,
        ads.label_score
    FROM admin_story_dataset ads
    JOIN story_parts sp ON ads.story_id = sp.story_id''',
    engine
)

X = []
y = []

for _, row in df.iterrows():

    features = extract_features(row["story"], row["prediction"])

    X.append(features)
    y.append(row["label_score"])


model = RandomForestRegressor()

model.fit(X, y)

joblib.dump(model, "ml_prediction_model.pkl")

print("ML dataset model trained successfully.")