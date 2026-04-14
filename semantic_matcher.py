from sentence_transformers import SentenceTransformer, util
import nltk
from nltk.tokenize import sent_tokenize

model = SentenceTransformer('all-mpnet-base-v2')

def semantic_accuracy(score):
    if score >= 0.65:
        return 100, "Highly Correct"
    elif score >= 0.45:
        percent = (score - 0.45) / (0.65 - 0.45) * 100
        return round(percent, 2), "Correct"
    elif score >= 0.30:
        percent = (score - 0.30) / (0.45 - 0.30) * 100
        return round(percent, 2), "Partially Correct"
    else:
        return 0, "Incorrect"

story = """
Arjun had always valued loyalty, but as power came closer, he started ignoring his closest friend.
Finally, he chose ambition over friendship and left the village to become a ruler.
The villagers were shocked by his sudden change in character.
"""

prediction = "He will betray his friend to gain power."

# Split into proper sentences
sentences = sent_tokenize(story)

pred_embedding = model.encode(prediction, convert_to_tensor=True)

scores = []

print("Sentence-wise similarity:\n")

for i, sent in enumerate(sentences):
    sent_embedding = model.encode(sent, convert_to_tensor=True)
    sim = util.cos_sim(pred_embedding, sent_embedding).item()
    scores.append(sim)
    print(f"Sentence {i+1} similarity: {sim:.3f}")
    print(f"Text: {sent}\n")

final_score = max(scores)
print("Final Semantic Accuracy:", round(final_score * 100, 2), "%")

best_score = max(scores)
acc, label = semantic_accuracy(best_score)

print("Semantic Verdict:", label)
print("Confidence Level:", acc, "%")

