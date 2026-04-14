import torch
import nltk
from nltk.tokenize import sent_tokenize
from sentence_transformers import SentenceTransformer, CrossEncoder, util

bi_encoder = SentenceTransformer('all-mpnet-base-v2')
nli_encoder = CrossEncoder('cross-encoder/nli-roberta-base')

# NLI label order for cross-encoder/nli-roberta-base: [contradiction, neutral, entailment]
IDX_CONTRADICTION = 0
IDX_NEUTRAL = 1
IDX_ENTAILMENT = 2


def evaluate_prediction(story, prediction, top_k=5):
    sentences = sent_tokenize(story)
    if not sentences:
        return "Invalid input", 0, ""

    # --- Step 1: Bi-encoder retrieval (broader top_k for better coverage) ---
    pred_emb = bi_encoder.encode(prediction, convert_to_tensor=True)
    sent_embs = bi_encoder.encode(sentences, convert_to_tensor=True)

    cosine_scores = util.cos_sim(pred_emb, sent_embs)[0]
    top_results = torch.topk(cosine_scores, k=min(top_k, len(sentences)))
    candidates = [(sentences[idx], cosine_scores[idx].item()) for idx in top_results.indices]

    # --- Step 2: NLI scoring over all candidates ---
    pairs = [(sent, prediction) for sent, _ in candidates]
    logits_batch = nli_encoder.predict(pairs)

    entailment_scores = []
    contradiction_scores = []
    best_entailment = 0
    best_sentence = ""

    for i, logits in enumerate(logits_batch):
        probs = torch.softmax(torch.tensor(logits), dim=0)
        entailment = probs[IDX_ENTAILMENT].item()
        contradiction = probs[IDX_CONTRADICTION].item()

        entailment_scores.append(entailment)
        contradiction_scores.append(contradiction)

        if entailment > best_entailment:
            best_entailment = entailment
            best_sentence = candidates[i][0]

    # --- Step 3: Compute aggregate signals ---

    # How many sentences support the prediction (entailment > 0.4)?
    support_count = sum(1 for e in entailment_scores if e > 0.40)
    coverage_score = support_count / len(candidates)  # 0.0 to 1.0

    # How many sentences actively contradict the prediction?
    contradiction_count = sum(1 for c in contradiction_scores if c > 0.50)
    contradiction_ratio = contradiction_count / len(candidates)  # 0.0 to 1.0

    # Average entailment across top candidates (not just the best one)
    avg_entailment = sum(entailment_scores) / len(entailment_scores)

    # --- Step 4: Compute final composite score ---
    # Weights: best entailment is important but not everything.
    # Coverage rewards consistency across sentences.
    # Contradiction penalty heavily discounts off-topic predictions.
    composite = (
        0.45 * best_entailment
        + 0.30 * avg_entailment
        + 0.25 * coverage_score
        - 0.60 * contradiction_ratio  # strong penalty for contradictions
    )

    # Clamp to [0, 1]
    composite = max(0.0, min(1.0, composite))

    # --- Step 5: Verdict thresholds ---
    if composite >= 0.55:
        verdict = "Correct Prediction"
    elif composite >= 0.30:
        verdict = "Partially Correct Prediction"
    else:
        verdict = "Incorrect Prediction"

    return verdict, round(composite * 100, 2), best_sentence


# ── Debug helper (remove in production) ──────────────────────────────────────
def evaluate_prediction_verbose(story, prediction, top_k=5):
    """Same as evaluate_prediction but prints internals for tuning."""
    sentences = sent_tokenize(story)
    pred_emb = bi_encoder.encode(prediction, convert_to_tensor=True)
    sent_embs = bi_encoder.encode(sentences, convert_to_tensor=True)

    cosine_scores = util.cos_sim(pred_emb, sent_embs)[0]
    top_results = torch.topk(cosine_scores, k=min(top_k, len(sentences)))
    candidates = [(sentences[idx], cosine_scores[idx].item()) for idx in top_results.indices]

    pairs = [(sent, prediction) for sent, _ in candidates]
    logits_batch = nli_encoder.predict(pairs)

    print(f"\n{'='*60}")
    print(f"PREDICTION: {prediction[:100]}...")
    print(f"{'='*60}")

    entailment_scores = []
    contradiction_scores = []
    best_entailment = 0
    best_sentence = ""

    for i, logits in enumerate(logits_batch):
        probs = torch.softmax(torch.tensor(logits), dim=0)
        contradiction = probs[IDX_CONTRADICTION].item()
        neutral = probs[IDX_NEUTRAL].item()
        entailment = probs[IDX_ENTAILMENT].item()

        entailment_scores.append(entailment)
        contradiction_scores.append(contradiction)

        print(f"\n[Candidate {i+1}] cosine={candidates[i][1]:.3f}")
        print(f"  Sent     : {candidates[i][0][:120]}")
        print(f"  Contra   : {contradiction:.3f} | Neutral: {neutral:.3f} | Entail: {entailment:.3f}")

        if entailment > best_entailment:
            best_entailment = entailment
            best_sentence = candidates[i][0]

    support_count = sum(1 for e in entailment_scores if e > 0.40)
    coverage_score = support_count / len(candidates)
    contradiction_count = sum(1 for c in contradiction_scores if c > 0.50)
    contradiction_ratio = contradiction_count / len(candidates)
    avg_entailment = sum(entailment_scores) / len(entailment_scores)

    composite = (
        0.45 * best_entailment
        + 0.30 * avg_entailment
        + 0.25 * coverage_score
        - 0.60 * contradiction_ratio
    )
    composite = max(0.0, min(1.0, composite))

    print(f"\n── SCORES ──")
    print(f"  best_entailment    : {best_entailment:.3f}")
    print(f"  avg_entailment     : {avg_entailment:.3f}")
    print(f"  coverage_score     : {coverage_score:.3f}  ({support_count}/{len(candidates)} sentences)")
    print(f"  contradiction_ratio: {contradiction_ratio:.3f}  ({contradiction_count}/{len(candidates)} sentences)")
    print(f"  COMPOSITE SCORE    : {composite*100:.2f}%")

    if composite >= 0.55:
        verdict = "Correct Prediction"
    elif composite >= 0.30:
        verdict = "Partially Correct Prediction"
    else:
        verdict = "Incorrect Prediction"

    print(f"  VERDICT            : {verdict}")
    return {
        "verdict": verdict,
        "ai_score": round(best_entailment * 100, 2),
        "best_sentence": best_sentence,
        "entailment": best_entailment,
        "cosine_score": float(torch.max(cosine_scores).item()),
        "contradiction": 1 - best_entailment  # temporary simple logic
    }