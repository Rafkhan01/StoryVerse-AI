<?php
session_start();
require_once 'db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Ensure only authors can access this endpoint
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$user_name = $_SESSION['user_name'];

// Validate input parameters
if (!isset($_GET['story_id']) || !isset($_GET['part_number'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$story_id = intval($_GET['story_id']);
$part_number = intval($_GET['part_number']);

try {
    // Verify that the story belongs to the logged-in author
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM stories 
        WHERE story_id = ? AND created_by = ?
    ");
    $stmt->execute([$story_id, $user_name]);
    
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'error' => 'Story not found or unauthorized']);
        exit();
    }
    
    // Fetch comments with sentiment data
    $stmt = $pdo->prepare("
        SELECT 
            c.comment_id,
            c.comment_text,
            c.sentiment,
            c.sentiment_score,
            c.created_at,
            c.parent_comment_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.profile_picture
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.story_id = ? AND c.part_number = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$story_id, $part_number]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format comments for display
    $formatted_comments = [];
    foreach ($comments as $comment) {
        $formatted_comments[] = [
            'comment_id' => $comment['comment_id'],
            'comment_text' => htmlspecialchars($comment['comment_text']),
            'sentiment' => ucfirst($comment['sentiment']),
            'sentiment_score' => floatval($comment['sentiment_score']),
            'created_at' => timeAgo($comment['created_at']),
            'parent_comment_id' => $comment['parent_comment_id'],
            'user_name' => htmlspecialchars($comment['user_name']),
            'full_name' => htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']),
            'profile_picture' => $comment['profile_picture']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $formatted_comments,
        'total' => count($formatted_comments)
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching comments: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred'
    ]);
}

// Helper function to format time
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    return date('M d, Y', $timestamp);
}
?>
