<?php

$conn = new mysqli("localhost", "root", "", "story_prediction2");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("
    INSERT INTO admin_story_dataset
    (story_id, prediction_text, label_score, label_type,
     ai_score, entailment, contradiction, entity_score, cosine_score, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isdsddddds",
    $_POST['story_id'],
    $_POST['prediction_text'],
    $_POST['label_score'],
    $_POST['label_type'],
    $_POST['ai_score'],
    $_POST['entailment'],
    $_POST['contradiction'],
    $_POST['entity_score'],
    $_POST['cosine_score'],
    $_POST['notes']
);

$stmt->execute();

echo "Saved Successfully";

?>