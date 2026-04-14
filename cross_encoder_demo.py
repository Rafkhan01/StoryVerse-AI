import torch
from sentence_transformers import CrossEncoder

model = CrossEncoder('cross-encoder/nli-roberta-base')

story_sentence = "Finally, he chose ambition over friendship and left the village to become a ruler."
prediction = "He will betray his friend to gain power."

# Get logits
logits = model.predict([(story_sentence, prediction)])[0]

# Convert logits → probabilities
probs = torch.softmax(torch.tensor(logits), dim=0)

contradiction, neutral, entailment = probs.tolist()

print("Contradiction:", round(contradiction, 4))
print("Neutral:", round(neutral, 4))
print("Entailment:", round(entailment, 4))
