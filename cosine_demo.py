import numpy as np
from sentence_transformers import SentenceTransformer

model = SentenceTransformer('all-MiniLM-L6-v2')

s1 = "He will betray his friend for power."
s2 = "He chose ambition over loyalty."

v1 = model.encode(s1)
v2 = model.encode(s2)

# Manual cosine similarity
cosine = np.dot(v1, v2) / (np.linalg.norm(v1) * np.linalg.norm(v2))

print("Cosine Similarity (manual):", cosine)