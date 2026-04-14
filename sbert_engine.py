import torch
import nltk
from nltk.tokenize import sent_tokenize
from sentence_transformers import SentenceTransformer, CrossEncoder, util

bi_encoder = SentenceTransformer('all-mpnet-base-v2')
nli_encoder = CrossEncoder('cross-encoder/nli-roberta-base')

def evaluate_prediction(story, prediction, top_k=3):
    sentences = sent_tokenize(story)

    pred_emb = bi_encoder.encode(prediction, convert_to_tensor=True)
    sent_embs = bi_encoder.encode(sentences, convert_to_tensor=True)

    cosine_scores = util.cos_sim(pred_emb, sent_embs)[0]
    top_results = torch.topk(cosine_scores, k=min(top_k, len(sentences)))

    candidates = [sentences[idx] for idx in top_results.indices]

    best_entailment = 0
    best_sentence = ""

    for sent in candidates:
        logits = nli_encoder.predict([(sent, prediction)])[0]
        probs = torch.softmax(torch.tensor(logits), dim=0)
        entailment = probs[2].item()

        if entailment > best_entailment:
            best_entailment = entailment
            best_sentence = sent

    if best_entailment >= 0.85:
        verdict = "Correct Prediction"
    elif best_entailment >= 0.60:
        verdict = "Partially Correct Prediction"
    else:
        verdict = "Incorrect Prediction"

    return verdict, round(best_entailment * 100, 2), best_sentence
