<?php
/**
 * admin_dataset_parts.php
 * AJAX endpoint — returns all parts for a given story_id as JSON.
 * Called by admin_dataset.php when admin selects a story from the dropdown.
 * Place inside: /admin/ (same folder as admin_dataset.php)
 */
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../db_connect.php';
header('Content-Type: application/json');

$story_id = (int)($_GET['story_id'] ?? 0);

if (!$story_id) {
    echo json_encode(['parts' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT part_id, part_number, status
        FROM story_parts
        WHERE story_id = ?
        ORDER BY part_number ASC
    ");
    $stmt->execute([$story_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['parts' => $parts]);
} catch (PDOException $e) {
    echo json_encode(['parts' => [], 'error' => $e->getMessage()]);
}
?>
