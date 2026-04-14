<?php

function geminiAnalyze($text) {

    $apiKey = "AIzaSyDzsEhd8L54Mp0Zzmo5kskCqYJZVd2Pl2Q";

    $prompt = "
Return JSON only:
{
  \"type\": \"dialogue or narration\",
  \"character\": \"name or null\",
  \"gender\": \"male/female/neutral\",
  \"mood\": \"emotion\"
}

Text: $text
";

    $data = [
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=$apiKey");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    $textOutput = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

    return json_decode($textOutput, true);
}
