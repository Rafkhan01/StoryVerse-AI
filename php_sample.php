<?php
$url = "http://127.0.0.1:5000/predict";

$data = [
    "story" => $story_text,
    "prediction" => $user_prediction
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json",
        "method"  => "POST",
        "content" => json_encode($data)
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);

$result = json_decode($response, true);

echo "Verdict: " . $result['verdict'];
echo "<br>Confidence: " . $result['confidence'] . "%";
echo "<br>Matched Part: " . $result['matched_story_part'];
?>
