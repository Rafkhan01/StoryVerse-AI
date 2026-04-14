<?php
require_once __DIR__ . '/../db_connect.php';
require_once 'gemini.php';
require_once 'google_tts.php';

function processTTS($pdo, $story_id, $part_id, $content) {

    // Step 1: Gemini analysis
    $analysis = geminiAnalyze($content);

    $type = $analysis['type'] ?? 'narration';
    $character = $analysis['character'] ?? null;
    $gender = $analysis['gender'] ?? 'neutral';
    $mood = $analysis['mood'] ?? 'neutral';

    // Step 2: Save metadata
    $stmt = $pdo->prepare("
        INSERT INTO tts_metadata
        (story_part_id, story_id, type, character_name, gender, mood)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        type=VALUES(type),
        character_name=VALUES(character_name),
        gender=VALUES(gender),
        mood=VALUES(mood)
    ");

    $stmt->execute([
        $part_id,
        $story_id,
        $type,
        $character,
        $gender,
        $mood
    ]);

    // Step 3: Regenerate full story audio
    generateStoryAudio($pdo, $story_id);
}
