<?php
session_start();
require_once 'db_connect.php';

// Get parameters
$story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : 0;
$part_number = isset($_GET['part_number']) ? (int)$_GET['part_number'] : 1;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Only track views for logged-in users
if ($story_id > 0 && $user_id !== null) {
    try {
        // Check if this user has already viewed this story
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM story_views 
            WHERE story_id = ? AND user_id = ?
        ");
        $stmt->execute([$story_id, $user_id]);
        $already_viewed = $stmt->fetchColumn() > 0;
        
        // Only track if not already viewed
        if (!$already_viewed) {
            $pdo->beginTransaction();
            
            // Get user's IP address
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Insert the view record
            $stmt = $pdo->prepare("
                INSERT INTO story_views (story_id, user_id, part_number, viewed_at, ip_address)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$story_id, $user_id, $part_number, $ip_address]);
            
            // Update view count in stories table
            $stmt = $pdo->prepare("
                UPDATE stories 
                SET view_count = view_count + 1 
                WHERE story_id = ?
            ");
            $stmt->execute([$story_id]);
            
            // Update author's total views
            $stmt = $pdo->prepare("
                UPDATE users u
                JOIN stories s ON u.user_name = s.created_by
                SET u.total_views = u.total_views + 1
                WHERE s.story_id = ?
            ");
            $stmt->execute([$story_id]);
            
            $pdo->commit();
        }
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log error but don't stop the redirect
        error_log("View tracking error: " . $e->getMessage());
    }
}

// Redirect to story detail page
header("Location: story_detail.php?story_id={$story_id}&part_number={$part_number}");
exit();
?>