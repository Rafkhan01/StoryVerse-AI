import torch
import nltk
from nltk.tokenize import sent_tokenize
from sentence_transformers import SentenceTransformer, CrossEncoder, util

# Load models (do this ONCE)
bi_encoder = SentenceTransformer('all-mpnet-base-v2')
nli_encoder = CrossEncoder('cross-encoder/nli-roberta-base')


def evaluate_prediction(story, prediction, top_k=3):
    """
    Evaluates whether a prediction is semantically implied by a story.
    Returns verdict, confidence %, and best matching story segment.
    """

    # 1️⃣ Split story into sentences
    sentences = sent_tokenize(story)

    # 2️⃣ SBERT embeddings
    pred_emb = bi_encoder.encode(prediction, convert_to_tensor=True)
    sent_embs = bi_encoder.encode(sentences, convert_to_tensor=True)

    # 3️⃣ Cosine similarity (SBERT filtering)
    cosine_scores = util.cos_sim(pred_emb, sent_embs)[0]

    # 4️⃣ Select top-K candidate sentences
    top_results = torch.topk(cosine_scores, k=min(top_k, len(sentences)))

    candidates = [sentences[idx] for idx in top_results.indices]

    # 5️⃣ Cross-Encoder NLI scoring
    best_entailment = 0
    best_sentence = ""

    for sent in candidates:
        logits = nli_encoder.predict([(sent, prediction)])[0]
        probs = torch.softmax(torch.tensor(logits), dim=0)
        entailment = probs[2].item()

        if entailment > best_entailment:
            best_entailment = entailment
            best_sentence = sent

    # 6️⃣ Final verdict
    if best_entailment >= 0.85:
        verdict = "Correct Prediction"
    elif best_entailment >= 0.60:
        verdict = "Partially Correct Prediction"
    else:
        verdict = "Incorrect Prediction"

    return {
        "verdict": verdict,
        "confidence_percent": round(best_entailment * 100, 2),
        "matched_story_part": best_sentence
    }


# 🔍 DEMO TEST
if __name__ == "__main__":
    story_text = """
    Arjun had always valued loyalty, but as power came closer,
    he started ignoring his closest friend.
    Finally, he chose ambition over friendship and left the village
    to become a ruler.
    """

    user_prediction = "He will betray his friend to gain power."

    result = evaluate_prediction(story_text, user_prediction)

    print("\n--- Evaluation Result ---")
    for k, v in result.items():
        print(f"{k}: {v}")
