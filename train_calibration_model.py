import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error
import joblib

# Load dataset
df = pd.read_csv("dataset.csv")

# Features
X = df[[
    "ai_score",
    "entailment",
    "contradiction",
    "entity_score",
    "cosine_score"
]]

# Target
y = df["label_score"]

# Split
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

# Model
model = RandomForestRegressor(n_estimators=200)
model.fit(X_train, y_train)

# Test
preds = model.predict(X_test)
print("MAE:", mean_absolute_error(y_test, preds))

# Save model
joblib.dump(model, "calibration_model.pkl")

print("Model saved successfully.")