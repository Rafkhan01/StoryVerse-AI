<?php
session_start();
require_once 'db_connect.php'; // Your database connection

header('Content-Type: application/json'); // Ensure the response is JSON

$response = ['success' => false, 'message' => '', 'new_like_count' => 0];

// Ensure it's a POST request and necessary data is present
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prediction_id'], $_POST['user_id'], $_POST['action'])) {
    $prediction_id = (int)$_POST['prediction_id'];
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action']; // 'like' or 'unlike'

    // Basic validation
    if ($prediction_id <= 0 || $user_id <= 0 || !in_array($action, ['like', 'unlike'])) {
        $response['message'] = 'Invalid input data.';
        echo json_encode($response);
        exit();
    }

    try {
        // First, get the prediction's story_id and part_number to check its status
        $stmt_pred_info = $pdo->prepare("SELECT story_id, prediction_part_no FROM predictions WHERE prediction_id = :prediction_id");
        $stmt_pred_info->execute(['prediction_id' => $prediction_id]);
        $pred_info = $stmt_pred_info->fetch();

        if (!$pred_info) {
            $response['message'] = 'Prediction not found.';
            echo json_encode($response);
            exit();
        }

        // Get the prediction deadline for this specific part
        $stmt_part_deadline = $pdo->prepare("SELECT prediction_deadline FROM story_parts WHERE story_id = :story_id AND part_number = :part_number");
        $stmt_part_deadline->execute([
            'story_id' => $pred_info['story_id'],
            'part_number' => $pred_info['prediction_part_no']
        ]);
        $part_deadline_row = $stmt_part_deadline->fetch();

        if (!$part_deadline_row) {
            $response['message'] = 'Story part deadline not found.';
            echo json_encode($response);
            exit();
        }

        $prediction_deadline = strtotime($part_deadline_row['prediction_deadline']);
        $current_time = time();

        // Prevent like/unlike if the deadline has passed
        if ($current_time > $prediction_deadline) {
            $response['message'] = 'Cannot like/unlike: Prediction deadline has passed for this part.';
            echo json_encode($response);
            exit();
        }

        $pdo->beginTransaction();

        if ($action === 'like') {
            // Check if already liked
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE prediction_id = :prediction_id AND user_id = :user_id");
            $stmt->execute(['prediction_id' => $prediction_id, 'user_id' => $user_id]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO likes (prediction_id, user_id) VALUES (:prediction_id, :user_id)");
                $stmt->execute(['prediction_id' => $prediction_id, 'user_id' => $user_id]);
                $response['success'] = true;
            } else {
                $response['message'] = 'Already liked.';
                $response['success'] = true; // Still a success if they tried to like again
            }
        } elseif ($action === 'unlike') {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE prediction_id = :prediction_id AND user_id = :user_id");
            $stmt->execute(['prediction_id' => $prediction_id, 'user_id' => $user_id]);
            $response['success'] = true;
        }

        // Get the updated like count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE prediction_id = :prediction_id");
        $stmt->execute(['prediction_id' => $prediction_id]);
        $response['new_like_count'] = $stmt->fetchColumn();

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Like/Unlike Error: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method or missing data.';
}

echo json_encode($response);
?>
