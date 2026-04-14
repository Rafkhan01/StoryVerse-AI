<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is an author
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get author comprehensive statistics
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_picture,
            u.bio,
            u.website,
            u.total_stories,
            u.total_views,
            u.follower_count,
            u.following_count,
            u.total_likes,
            u.total_score,
            u.total_bonus,
            u.created_at
        FROM users 
        WHERE user_id = ? AND user_type = 'author'
    ");
    $stmt->execute([$user_id]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$author) {
        http_response_code(404);
        echo json_encode(['error' => 'Author not found']);
        exit();
    }

    // Get story statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as active,
            SUM(view_count) as total_story_views,
            SUM(like_count) as total_story_likes,
            SUM(prediction_count) as total_story_predictions,
            AVG(view_count) as avg_views_per_story
        FROM stories 
        WHERE created_by = ?
    ");
    $stmt->execute([$user_id]);
    $story_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent activity (new followers, likes in last 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_followers
        FROM follows
        WHERE author_user_id = ? AND followed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$user_id]);
    $new_followers_week = $stmt->fetchColumn();

    // Get new likes this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_likes
        FROM likes l
        JOIN predictions p ON l.prediction_id = p.prediction_id
        JOIN stories s ON p.story_id = s.story_id
        WHERE s.created_by = ? AND l.liked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$user_id]);
    $new_likes_week = $stmt->fetchColumn();

    // Get prediction statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(p.prediction_id) as total_predictions,
            AVG(CASE WHEN p.manual_accuracy IS NOT NULL THEN p.manual_accuracy ELSE 0 END) as avg_accuracy,
            COUNT(DISTINCT p.user_id) as unique_predictors
        FROM predictions p
        JOIN stories s ON p.story_id = s.story_id
        WHERE s.created_by = ?
    ");
    $stmt->execute([$user_id]);
    $prediction_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get author ranking
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as ranking
        FROM users
        WHERE user_type = 'author' AND total_score > (
            SELECT total_score FROM users WHERE user_id = ?
        )
    ");
    $stmt->execute([$user_id]);
    $ranking = $stmt->fetchColumn();

    // Get total author count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'author'");
    $total_authors = $stmt->fetchColumn();

    // Get unread notifications count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetchColumn();

    // Calculate growth rate (compared to last month)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as last_month_followers
        FROM follows
        WHERE author_user_id = ? 
        AND followed_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
        AND followed_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $stmt->execute([$user_id]);
    $last_month_followers = $stmt->fetchColumn();
    
    $this_month_followers = $new_followers_week * 4; // Rough estimate
    $follower_growth = $last_month_followers > 0 
        ? round((($this_month_followers - $last_month_followers) / $last_month_followers) * 100, 1)
        : 0;

    // Compile all statistics
    $response = [
        'success' => true,
        'author' => $author,
        'statistics' => [
            'stories' => [
                'total' => (int)$story_stats['total'],
                'active' => (int)$story_stats['active'],
                'completed' => (int)$story_stats['completed'],
                'total_views' => (int)$story_stats['total_story_views'],
                'total_likes' => (int)$story_stats['total_story_likes'],
                'total_predictions' => (int)$story_stats['total_story_predictions'],
                'avg_views_per_story' => round($story_stats['avg_views_per_story'], 1)
            ],
            'engagement' => [
                'follower_count' => (int)$author['follower_count'],
                'following_count' => (int)$author['following_count'],
                'new_followers_week' => (int)$new_followers_week,
                'new_likes_week' => (int)$new_likes_week,
                'follower_growth_rate' => $follower_growth
            ],
            'predictions' => [
                'total_predictions' => (int)$prediction_stats['total_predictions'],
                'avg_accuracy' => round($prediction_stats['avg_accuracy'], 1),
                'unique_predictors' => (int)$prediction_stats['unique_predictors']
            ],
            'ranking' => [
                'current_rank' => (int)$ranking,
                'total_authors' => (int)$total_authors,
                'total_score' => (int)$author['total_score']
            ],
            'notifications' => [
                'unread_count' => (int)$unread_notifications
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log("Error fetching author stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>