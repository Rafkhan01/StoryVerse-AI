<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$author_user_id = isset($_POST['author_user_id']) ? (int)$_POST['author_user_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($author_user_id === 0 || !in_array($action, ['follow', 'unfollow'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

try {
    if ($action === 'follow') {
        $stmt = $pdo->prepare("INSERT INTO follows (follower_user_id, author_user_id) VALUES (?, ?)");
        $stmt->execute([$current_user_id, $author_user_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_user_id = ? AND author_user_id = ?");
        $stmt->execute([$current_user_id, $author_user_id]);
    }
    
    echo json_encode(['success' => true, 'action' => $action]);
} catch (PDOException $e) {
    error_log("Follow error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>