<?php

$url = "http://127.0.0.1:8000/story-predict";

$data = [
    "story" => "The boy went to school.",
    "prediction" => "He went to school."
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);

if ($response === false) {
    echo "CURL ERROR: " . curl_error($ch);
} else {
    echo "SUCCESS:<br>";
    echo $response;
}

curl_close($ch);
?>