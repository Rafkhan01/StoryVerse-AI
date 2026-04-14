"""
sbert_engine.py  –  Story Prediction Evaluator
================================================
Implements all four evaluation pillars:
  1. Full Chapter Comparison   – prediction scored against the ENTIRE story, not just top-k sentences
  2. Contradiction Penalty     – explicit NLI contradiction signals reduce the score
  3. Entity Drift Detection    – named entities in the prediction must appear / be compatible with the story
  4. Genre Consistency Check   – the prediction's genre/domain must match the story's domain

Score breakdown (all values 0-1, final score 0-100):
  - w_entailment   : NLI entailment signal over full story weighted by paragraph relevance
  - contra_ratio   : fraction of story sentences that actively contradict the prediction
  - entity_score   : named-entity overlap / semantic compatibility
  - genre_score    : cosine similarity between genre prototype embeddings
  - composite      : weighted combination with contradiction as a hard penalty
"""

import re
import torch
import spacy
import nltk
from nltk.tokenize import sent_tokenize
from sentence_transformers import SentenceTransformer, CrossEncoder, util

nltk.download('punkt',     quiet=True)
nltk.download('punkt_tab', quiet=True)

# ── Models ─────────────────────────────────────────────────────────────────────
bi_encoder  = SentenceTransformer('all-mpnet-base-v2')
nli_encoder = CrossEncoder('cross-encoder/nli-roberta-base')

# spaCy NER  (python -m spacy download en_core_web_sm)
try:
    nlp = spacy.load('en_core_web_sm')
except OSError:
    import subprocess, sys
    subprocess.run([sys.executable, '-m', 'spacy', 'download', 'en_core_web_sm'], check=True)
    nlp = spacy.load('en_core_web_sm')

# NLI label indices for cross-encoder/nli-roberta-base: [contradiction, neutral, entailment]
IDX_CONTRA = 0
IDX_ENTAIL = 2

# ── Genre / Domain Prototype Sentences ─────────────────────────────────────────
# These define what each genre "smells like" to the embedding model.
GENRE_PROTOTYPES = {
    "sci-fi / psychological thriller": [
        "A scientist wakes up inside a simulated reality designed to heal his broken mind.",
        "The neural network project secretly extracts memories from an unconscious patient.",
        "Consciousness is preserved inside a virtual construct built from subconscious data.",
        "Cognitive therapy uses a holographic environment to repair damaged neural pathways.",
        "An experiment with mind uploading reveals classified memories of a hidden crime.",
        "The patient discovers that his seven-month coma was a controlled neural simulation.",
        "Memories from other people were funneled through Aris's recovering brain.",
    ],
    "marine / action adventure": [
        "The secret underwater base hides a radical group of illegal marine biologists.",
        "A covert agent investigates the illegal breeding of giant squids in the ocean.",
        "The facility floods and the hero must lead trained dolphins to stop a tsunami.",
        "An undercover operation in an aquarium uncovers deep-sea smuggling networks.",
        "Marine biologists use high-tech aquariums to run dangerous illegal experiments.",
    ],
    "financial / tech thriller": [
        "A digital miner discovers a critical bug hidden deep inside the blockchain.",
        "The server farm secretly mines cryptocurrency using stolen computing power.",
        "Bitcoin data recovery unlocks the path to becoming the world's richest man.",
        "A cryptocurrency billionaire buys the facility after exposing the blockchain fraud.",
        "The global cryptocurrency network hides a massive financial conspiracy.",
        "Recovering lost Bitcoin data leads to uncovering a global financial crime.",
    ],
    "family drama / identity": [
        "A twin wakes up realising he has stolen his brother's life and identity.",
        "The experiment linked two brothers' minds across great distances.",
        "Glitches were actually the twin brother's thoughts bleeding into his own.",
        "Scientists struggle to undo the neural link that merged the siblings' identities.",
        "A man must live with the guilt of living his brother's life after the procedure.",
    ],
}

# Pre-compute genre prototype embeddings once at module load
_genre_embeddings = {
    name: bi_encoder.encode(sentences, convert_to_tensor=True)
    for name, sentences in GENRE_PROTOTYPES.items()
}


# ══════════════════════════════════════════════════════════════════════════════
# PILLAR 1  –  Full Chapter Comparison
# ══════════════════════════════════════════════════════════════════════════════
def _full_chapter_score(story: str, prediction: str):
    """
    Split story into paragraphs. For each paragraph:
      - Compute bi-encoder cosine similarity to prediction  → relevance weight
      - Run NLI to get entailment and contradiction probabilities
    Return relevance-weighted entailment, relevance-weighted contradiction,
    and the best-matched paragraph text.
    """
    paragraphs = [p.strip() for p in re.split(r'\n+', story) if len(p.strip()) > 30]
    if not paragraphs:
        return 0.0, 0.0, ""

    pred_emb  = bi_encoder.encode(prediction, convert_to_tensor=True)
    para_embs = bi_encoder.encode(paragraphs, convert_to_tensor=True)
    cosines   = util.cos_sim(pred_emb, para_embs)[0]

    # Softmax with temperature to create relevance weights
    weights = torch.softmax(cosines * 5.0, dim=0)

    pairs  = [(para, prediction) for para in paragraphs]
    logits = nli_encoder.predict(pairs)

    entailments    = []
    contradictions = []
    best_e         = 0.0
    best_para      = ""

    for i, lg in enumerate(logits):
        probs = torch.softmax(torch.tensor(lg), dim=0)
        e = probs[IDX_ENTAIL].item()
        c = probs[IDX_CONTRA].item()
        entailments.append(e)
        contradictions.append(c)
        if e > best_e:
            best_e    = e
            best_para = paragraphs[i]

    e_tensor = torch.tensor(entailments)
    c_tensor = torch.tensor(contradictions)

    w_entailment    = (weights * e_tensor).sum().item()
    w_contradiction = (weights * c_tensor).sum().item()

    return w_entailment, w_contradiction, best_para


# ══════════════════════════════════════════════════════════════════════════════
# PILLAR 2  –  Contradiction Penalty
# ══════════════════════════════════════════════════════════════════════════════
def _contradiction_penalty(story: str, prediction: str) -> float:
    """
    Run NLI on EVERY sentence in the story (not just retrieved top-k).
    Return the fraction of sentences whose contradiction score exceeds threshold.
    A high ratio means the prediction is broadly incompatible with the story.
    """
    sentences = sent_tokenize(story)
    if not sentences:
        return 0.0

    pairs  = [(sent, prediction) for sent in sentences]
    logits = nli_encoder.predict(pairs)

    contra_count = 0
    for lg in logits:
        probs = torch.softmax(torch.tensor(lg), dim=0)
        if probs[IDX_CONTRA].item() > 0.55:
            contra_count += 1

    return contra_count / len(sentences)


# ══════════════════════════════════════════════════════════════════════════════
# PILLAR 3  –  Entity Drift Detection
# ══════════════════════════════════════════════════════════════════════════════
def _entity_drift_score(story: str, prediction: str) -> float:
    """
    Extract named entities (PERSON, ORG, GPE, FAC, PRODUCT, EVENT, WORK_OF_ART)
    from both story and prediction.

    Scoring logic:
      - Entities in BOTH story and prediction → grounded (good)
      - Prediction entities NOT in story but semantically close (cosine > 0.60)
        to a story entity → partially grounded
      - Prediction entities completely foreign → drift (bad)

    Returns a float in [0, 1]:
      1.0 = all prediction entities are grounded in the story
      0.0 = all prediction entities are completely foreign
      0.5 = neutral (no named entities in prediction)
    """
    TRACKED = {'PERSON', 'ORG', 'GPE', 'FAC', 'PRODUCT', 'EVENT', 'WORK_OF_ART'}

    def get_entities(text):
        doc = nlp(text)
        return {ent.text.lower() for ent in doc.ents if ent.label_ in TRACKED}

    story_ents = get_entities(story)
    pred_ents  = get_entities(prediction)

    if not pred_ents:
        return 0.5  # neutral if prediction has no named entities

    direct_overlap = pred_ents & story_ents
    drifted        = pred_ents - direct_overlap

    if not drifted or not story_ents:
        return len(direct_overlap) / len(pred_ents)

    # Semantic proximity check for drifted entities
    drift_embs      = bi_encoder.encode(list(drifted),     convert_to_tensor=True)
    story_ent_embs  = bi_encoder.encode(list(story_ents),  convert_to_tensor=True)
    sim_matrix      = util.cos_sim(drift_embs, story_ent_embs)
    max_sims        = sim_matrix.max(dim=1).values

    grounded_drifted = (max_sims > 0.60).sum().item()
    total_grounded   = len(direct_overlap) + grounded_drifted
    return total_grounded / len(pred_ents)


# ══════════════════════════════════════════════════════════════════════════════
# PILLAR 4  –  Genre Consistency Check
# ══════════════════════════════════════════════════════════════════════════════
def _genre_consistency_score(story: str, prediction: str):
    """
    Embed the story and prediction then compare each to genre prototype embeddings.
    The story defines the correct genre.
    The prediction is heavily penalised if its closest genre differs from the story's.

    Returns:
      genre_score  float [0, 1]
      story_genre  str
      pred_genre   str
    """
    story_emb = bi_encoder.encode(story,      convert_to_tensor=True)
    pred_emb  = bi_encoder.encode(prediction, convert_to_tensor=True)

    story_genre_scores = {}
    pred_genre_scores  = {}

    for name, proto_embs in _genre_embeddings.items():
        s_sims = util.cos_sim(story_emb, proto_embs)[0]
        p_sims = util.cos_sim(pred_emb,  proto_embs)[0]
        story_genre_scores[name] = s_sims.mean().item()   # mean across prototypes
        pred_genre_scores[name]  = p_sims.mean().item()

    story_genre = max(story_genre_scores, key=story_genre_scores.get)
    pred_genre  = max(pred_genre_scores,  key=pred_genre_scores.get)

    if story_genre == pred_genre:
        genre_score = 1.0
    else:
        # How well does the prediction match the story's genre prototype?
        story_proto_embs = _genre_embeddings[story_genre]
        cross_sims       = util.cos_sim(pred_emb, story_proto_embs)[0]
        genre_score      = float(cross_sims.mean().item()) * 0.4   # heavy penalty for wrong genre

    return genre_score, story_genre, pred_genre


# ══════════════════════════════════════════════════════════════════════════════
# MAIN ENTRY POINT
# ══════════════════════════════════════════════════════════════════════════════
def evaluate_prediction(story: str, prediction: str):
    """
    Returns: (verdict: str, confidence: float 0-100, matched_story_part: str)

    Composite formula
    -----------------
    base          = weighted_entailment from Pillar 1  (full chapter NLI)
    entity_boost  = up to +0.10  if prediction entities match story entities
    genre_factor  = multiplier in [0.25, 1.00] — wrong genre crushes the score
    contra_penalty= up to -0.90  based on sentence-level contradiction ratio

    composite = (base + entity_boost) * genre_factor - contra_penalty
    """
    if not story.strip() or not prediction.strip():
        return "Invalid input", 0.0, ""

    w_entailment, w_contradiction, best_para = _full_chapter_score(story, prediction)
    contra_ratio                             = _contradiction_penalty(story, prediction)
    entity_score                             = _entity_drift_score(story, prediction)
    genre_score, story_genre, pred_genre     = _genre_consistency_score(story, prediction)

    base          = w_entailment
    entity_boost  = 0.10 * entity_score
    genre_factor  = 0.25 + 0.75 * genre_score   # range [0.25, 1.00]
    contra_penalty = 1.20 * contra_ratio          # range [0.00, 1.20]

    raw       = (base + entity_boost) * genre_factor - contra_penalty
    composite = max(0.0, min(1.0, raw))

    if composite >= 0.50:
        verdict = "Correct Prediction"
    elif composite >= 0.20:
        verdict = "Partially Correct Prediction"
    else:
        verdict = "Incorrect Prediction"

    return verdict, round(composite * 100, 2), best_para


# ══════════════════════════════════════════════════════════════════════════════
# VERBOSE DEBUG  –  call this manually to inspect and tune weights
# ══════════════════════════════════════════════════════════════════════════════
def evaluate_prediction_verbose(story: str, prediction: str):
    """Same as evaluate_prediction but prints a full per-pillar breakdown."""

    print(f"\n{'='*65}")
    print(f"PREDICTION: {prediction[:120]}")
    print(f"{'='*65}")

    w_entailment, w_contradiction, best_para = _full_chapter_score(story, prediction)
    contra_ratio                             = _contradiction_penalty(story, prediction)
    entity_score                             = _entity_drift_score(story, prediction)
    genre_score, story_genre, pred_genre     = _genre_consistency_score(story, prediction)

    base           = w_entailment
    entity_boost   = 0.10 * entity_score
    genre_factor   = 0.25 + 0.75 * genre_score
    contra_penalty = 1.20 * contra_ratio
    raw            = (base + entity_boost) * genre_factor - contra_penalty
    composite      = max(0.0, min(1.0, raw))

    if composite >= 0.50:
        verdict = "Correct Prediction"
    elif composite >= 0.20:
        verdict = "Partially Correct Prediction"
    else:
        verdict = "Incorrect Prediction"

    print(f"\n── PILLAR 1: Full Chapter Comparison ──────────────────────")
    print(f"   Weighted Entailment (base)   : {base:.4f}")
    print(f"   Weighted Contradiction       : {w_contradiction:.4f}")
    print(f"   Best matched paragraph       : {best_para[:100]}...")

    print(f"\n── PILLAR 2: Contradiction Penalty ────────────────────────")
    print(f"   Sentence-level contra ratio  : {contra_ratio:.4f}")
    print(f"   Penalty applied              : -{contra_penalty:.4f}")

    print(f"\n── PILLAR 3: Entity Drift Detection ───────────────────────")
    print(f"   Entity overlap / grounded    : {entity_score:.4f}")
    print(f"   Entity boost                 : +{entity_boost:.4f}")

    print(f"\n── PILLAR 4: Genre Consistency ────────────────────────────")
    print(f"   Story genre detected         : {story_genre}")
    print(f"   Prediction genre detected    : {pred_genre}")
    print(f"   Genre score                  : {genre_score:.4f}")
    print(f"   Genre factor (multiplier)    : x{genre_factor:.4f}")

    print(f"\n── COMPOSITE ──────────────────────────────────────────────")
    print(f"   raw = ({base:.3f} + {entity_boost:.3f}) x {genre_factor:.3f} - {contra_penalty:.3f}")
    print(f"   FINAL SCORE  :  {composite*100:.2f}%")
    print(f"   VERDICT      :  {verdict}\n")

    return verdict, round(composite * 100, 2), best_para