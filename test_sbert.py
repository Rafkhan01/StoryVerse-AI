from sentence_transformers import SentenceTransformer, util

model = SentenceTransformer('all-MiniLM-L6-v2')

s1 = "He will betray his friend for power."
s2 = "He chose ambition over loyalty."

e1 = model.encode(s1, convert_to_tensor=True)
e2 = model.encode(s2, convert_to_tensor=True)

sim = util.cos_sim(e1, e2)
print("Semantic Similarity:", sim.item())