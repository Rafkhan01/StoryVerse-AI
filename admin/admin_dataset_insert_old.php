<!DOCTYPE html>
<html>
<head>
    <title>Admin Dataset Insert Test</title>
</head>
<body>

<h2>Manual Dataset Insert</h2>

<form method="POST" action="save_dataset.php">

    Story ID:
    <input type="number" name="story_id" required><br><br>

    Prediction:<br>
    <textarea name="prediction_text" rows="6" cols="80" required></textarea><br><br>

    Label Score (0-100):
    <input type="number" step="0.01" name="label_score" required><br><br>

    Label Type:
    <select name="label_type">
        <option value="correct">Correct</option>
        <option value="partial">Partial</option>
        <option value="wrong">Wrong</option>
    </select><br><br>

    AI Score:
    <input type="number" step="0.01" name="ai_score"><br><br>

    Entailment:
    <input type="number" step="0.0001" name="entailment"><br><br>

    Contradiction:
    <input type="number" step="0.0001" name="contradiction"><br><br>

    Entity Score:
    <input type="number" step="0.0001" name="entity_score"><br><br>

    Cosine Score:
    <input type="number" step="0.0001" name="cosine_score"><br><br>

    Notes:<br>
    <textarea name="notes" rows="3" cols="60"></textarea><br><br>

    <button type="submit">Save Dataset</button>

</form>

</body>
</html>