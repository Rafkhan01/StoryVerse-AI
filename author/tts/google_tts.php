<?php

function generateStoryAudio($pdo, $story_id) {

    $stmt = $pdo->prepare("
        SELECT content FROM story_parts
        WHERE story_id = ?
        ORDER BY part_number
    ");

    $stmt->execute([$story_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $fullText = implode("\n", $parts);

    $apiKey = "AIzaSyDzsEhd8L54Mp0Zzmo5kskCqYJZVd2Pl2Q";

    $payload = [
        "input" => ["text" => $fullText],
        "voice" => [
            "languageCode" => "en-US",
            "ssmlGender" => "NEUTRAL"
        ],
        "audioConfig" => [
            "audioEncoding" => "MP3"
        ]
    ];

    $ch = curl_init("https://texttospeech.googleapis.com/v1/text:synthesize?key=$apiKey");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['audioContent'])) return;

    $audioData = base64_decode($data['audioContent']);

    if (!is_dir('../audio')) {
        mkdir('../audio', 0777, true);
    }

    $filePath = "../audio/story_{$story_id}.mp3";

    file_put_contents($filePath, $audioData);

    $stmt = $pdo->prepare("
        INSERT INTO tts_audio (story_id, audio_path, status)
        VALUES (?, ?, 'completed')
        ON DUPLICATE KEY UPDATE
        audio_path=VALUES(audio_path)
    ");

    $stmt->execute([$story_id, $filePath]);
}
