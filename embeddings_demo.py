from sentence_transformers import SentenceTransformer

model = SentenceTransformer('all-MiniLM-L6-v2')

sentence = "He will betray his friend for power."

embedding = model.encode(sentence)

print("Embedding length:", len(embedding))
print("First 10 values of embedding:", embedding[:10])
