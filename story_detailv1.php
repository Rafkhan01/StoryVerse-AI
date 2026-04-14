<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in (but allow guests to view)
$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// --- FOLLOW/UNFOLLOW LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['follow', 'unfollow'])) {
    header('Content-Type: application/json');
    
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Please login to follow authors.']);
        exit();
    }
    
    $author_user_name = $_POST['author_user_name'] ?? null;
    $action = $_POST['action'];

    if (!$author_user_name) {
        echo json_encode(['success' => false, 'message' => 'Missing required data.']);
        exit();
    }

    try {
        // Get author user_id from user_name
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_name = ? AND user_type = 'author'");
        $stmt->execute([$author_user_name]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$author) {
            echo json_encode(['success' => false, 'message' => 'Author not found.']);
            exit();
        }
        
        $author_user_id = $author['user_id'];
        
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
    exit();
}

// --- STORY LIKE/UNLIKE LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['like_story', 'unlike_story'])) {
    header('Content-Type: application/json');
    
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Please login to like stories.']);
        exit();
    }
    
    $story_id = $_POST['story_id'] ?? null;
    $action = $_POST['action'];

    if (!$story_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required data.']);
        exit();
    }

    try {
        if ($action === 'like_story') {
            $stmt = $pdo->prepare("INSERT INTO story_likes (story_id, user_id) VALUES (?, ?)");
            $stmt->execute([$story_id, $current_user_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM story_likes WHERE story_id = ? AND user_id = ?");
            $stmt->execute([$story_id, $current_user_id]);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM story_likes WHERE story_id = ?");
        $stmt->execute([$story_id]);
        $like_count = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'like_count' => $like_count]);
    } catch (PDOException $e) {
        error_log("Story like error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}

// --- COMMENT SUBMISSION LOGIC (AJAX — no page reload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_comment') {
    header('Content-Type: application/json');

    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'error_type' => 'auth', 'message' => 'Please login to comment.']);
        exit();
    }

    $comment_text = trim($_POST['comment_text'] ?? '');
    $story_id     = $_POST['story_id'] ?? '';
    $part_number  = $_POST['part_number'] ?? '';

    if (empty($comment_text) || empty($story_id) || empty($part_number)) {
        echo json_encode(['success' => false, 'error_type' => 'validation', 'message' => 'Comment text is required.']);
        exit();
    }

    // ── Step 1: Spam check ──────────────────────────────────────────────────
    $is_spam        = false;
    $spam_api_error = false;
    try {
        $ch = curl_init('http://127.0.0.1:8000/detect-spam');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["text" => $comment_text]));
        $spam_raw = curl_exec($ch);
        $spam_err = curl_error($ch);
        curl_close($ch);

        if ($spam_raw === false || !empty($spam_err)) {
            $spam_api_error = true;
            error_log("Spam API cURL error: " . $spam_err);
        } elseif ($spam_raw) {
            $spam_result = json_decode($spam_raw, true);
            if ($spam_result === null) {
                $spam_api_error = true;
                error_log("Spam API returned invalid JSON: " . $spam_raw);
            } else {
                $is_spam = $spam_result['is_spam'] ?? false;
            }
        } else {
            $spam_api_error = true;
            error_log("Spam API returned empty response.");
        }
    } catch (Exception $e) {
        $spam_api_error = true;
        error_log("Spam check exception: " . $e->getMessage());
    }

    if ($spam_api_error) {
        echo json_encode([
            'success'    => false,
            'error_type' => 'api_error',
            'message'    => 'Spam detection API is unavailable. Please try again later.',
            'debug'      => 'Could not reach http://127.0.0.1:8000/detect-spam'
        ]);
        exit();
    }

    if ($is_spam) {
        echo json_encode(['success' => false, 'error_type' => 'spam', 'message' => "Spam's are not allowed"]);
        exit();
    }

    // ── Step 2: Sentiment analysis ─────────────────────────────────────────
    $sentiment        = null;
    $sentiment_score  = null;
    $sent_api_error   = false;
    $sent_error_detail = '';
    try {
        $ch = curl_init('http://127.0.0.1:8000/sentiment-predict');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["text" => $comment_text]));
        $sent_raw = curl_exec($ch);
        $sent_err = curl_error($ch);
        curl_close($ch);

        if ($sent_raw === false || !empty($sent_err)) {
            $sent_api_error   = true;
            $sent_error_detail = "cURL error: " . $sent_err;
            error_log("Sentiment API cURL error: " . $sent_err);
        } elseif ($sent_raw) {
            $sent_result = json_decode($sent_raw, true);
            if ($sent_result === null) {
                $sent_api_error   = true;
                $sent_error_detail = "Invalid JSON from sentiment API: " . $sent_raw;
                error_log($sent_error_detail);
            } else {
                $sentiment       = $sent_result['sentiment'] ?? null;
                $sentiment_score = $sent_result['sentiment_score'] ?? null;
            }
        } else {
            $sent_api_error   = true;
            $sent_error_detail = "Sentiment API returned empty response.";
            error_log($sent_error_detail);
        }
    } catch (Exception $e) {
        $sent_api_error   = true;
        $sent_error_detail = $e->getMessage();
        error_log("Sentiment API exception: " . $e->getMessage());
    }

    if ($sent_api_error) {
        echo json_encode([
            'success'    => false,
            'error_type' => 'api_error',
            'message'    => 'Sentiment API is unavailable. Please try again later.',
            'debug'      => $sent_error_detail
        ]);
        exit();
    }

    // ── Step 3: Insert into DB ─────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("
            INSERT INTO comments 
            (story_id, part_number, user_id, comment_text, sentiment, sentiment_score)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$story_id, $part_number, $current_user_id, $comment_text, $sentiment, $sentiment_score]);
        $new_comment_id = $pdo->lastInsertId();

        echo json_encode([
            'success'         => true,
            'message'         => 'Comment posted!',
            'comment_id'      => $new_comment_id,
            'first_name'      => $_SESSION['first_name'],
            'last_name'       => $_SESSION['last_name'] ?? '',
            'comment_text'    => $comment_text,
            'sentiment'       => $sentiment,
            'sentiment_score' => $sentiment_score,
            'created_at'      => date('M j'),
            'user_id'         => $current_user_id,
        ]);
    } catch (PDOException $e) {
        error_log("Comment DB error: " . $e->getMessage());
        echo json_encode([
            'success'    => false,
            'error_type' => 'db_error',
            'message'    => 'Failed to save comment. Database error.',
            'debug'      => $e->getMessage()
        ]);
    }
    exit();
}

// --- PREDICTION SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prediction'])) {
    if (!$is_logged_in) {
        $_SESSION['submission_message'] = ['status' => 'error', 'message' => 'Please login to submit predictions.'];
        header("Location: story_detail.php?story_id={$_POST['story_id']}&part_number={$_POST['part_number']}");
        exit();
    }
    
    $userPrediction = trim($_POST['prediction_text'] ?? '');
    $storyId = $_POST['story_id'] ?? '';
    $partNumber = $_POST['part_number'] ?? '';

    if (empty($userPrediction) || empty($storyId) || empty($partNumber)) {
        $_SESSION['submission_message'] = ['status' => 'error', 'message' => "Missing required data."];
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO predictions (story_id, prediction_part_no, user_id, prediction_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$storyId, $partNumber, $current_user_id, $userPrediction]);
            $_SESSION['submission_message'] = ['status' => 'success', 'message' => 'Prediction submitted successfully!'];
        } catch (PDOException $e) {
            error_log("Prediction error: " . $e->getMessage());
            $_SESSION['submission_message'] = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
        }
    }
    
    header("Location: story_detail.php?story_id={$storyId}&part_number={$partNumber}");
    exit();
}

// --- PREDICTION DELETION LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit();
    }
    
    $predictionId = $_POST['prediction_id'] ?? null;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM predictions WHERE prediction_id = ? AND user_id = ?");
        $stmt->execute([$predictionId, $current_user_id]);
        
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE prediction_id = ?");
            $stmt->execute([$predictionId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Prediction deleted successfully.']);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Prediction not found or unauthorized.']);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}

// --- PREDICTION EDIT LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    header('Content-Type: application/json');
    
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit();
    }
    
    $predictionId = $_POST['prediction_id'] ?? null;
    $updatedText = trim($_POST['prediction_text'] ?? '');

    if (!$predictionId || !$updatedText) {
        echo json_encode(['success' => false, 'message' => 'Missing required data.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE predictions SET prediction_text = ?, is_edited = 1 WHERE prediction_id = ? AND user_id = ?");
        $stmt->execute([$updatedText, $predictionId, $current_user_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Prediction updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Prediction not found or unauthorized.']);
        }
    } catch (PDOException $e) {
        error_log("Edit error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}

// --- PREDICTION LIKE/UNLIKE LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['like', 'unlike'])) {
    header('Content-Type: application/json');
    
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Please login to like predictions.']);
        exit();
    }
    
    $predictionId = $_POST['prediction_id'] ?? null;
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        if ($action === 'like') {
            $stmt = $pdo->prepare("INSERT INTO likes (prediction_id, user_id, liked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$predictionId, $current_user_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE prediction_id = ? AND user_id = ?");
            $stmt->execute([$predictionId, $current_user_id]);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE prediction_id = ?");
        $stmt->execute([$predictionId]);
        $newLikeCount = $stmt->fetchColumn();

        $pdo->commit();
        echo json_encode(['success' => true, 'new_like_count' => $newLikeCount]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Like error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}

// --- COMMENT DELETE LOGIC (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {

    header('Content-Type: application/json');

    if (!$is_logged_in) {
        echo json_encode(['success' => false]);
        exit();
    }

    $comment_id = $_POST['comment_id'];

    $stmt = $pdo->prepare("
        DELETE FROM comments 
        WHERE comment_id = ? AND user_id = ?
    ");

    $stmt->execute([$comment_id, $current_user_id]);

    echo json_encode(['success' => true]);
    exit();
}

// --- MAIN PAGE LOGIC ---
$story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : 0;
$requested_part_number = isset($_GET['part_number']) ? (int)$_GET['part_number'] : null;
$sort_predictions_by = isset($_GET['sort_predictions']) ? $_GET['sort_predictions'] : 'newest';

$story = null;
$story_part = null;
$error_message = '';
$all_parts = [];
$predictions = [];
$comments = [];
$dynamic_status = 'unknown';
$is_following = false;
$story_liked = false;
$story_like_count = 0;

function getStoryStatus($upload_date, $prediction_deadline) {
    $current_time = time();
    $upload_timestamp = strtotime($upload_date);
    $deadline_timestamp = strtotime($prediction_deadline);

    if ($current_time < $upload_timestamp) {
        return 'coming_soon';
    } elseif ($current_time < $deadline_timestamp) {
        return 'open_for_predictions';
    } else {
        return 'closed';
    }
}

$submission_message = $_SESSION['submission_message'] ?? null;
unset($_SESSION['submission_message']);

try {
    // Fetch story details
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE story_id = ?");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$story) {
        throw new Exception("Story not found.");
    }

    // Check if user is following the author
    if ($is_logged_in) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM follows f
            JOIN users u ON f.author_user_id = u.user_id
            WHERE f.follower_user_id = ? AND u.user_name = ?
        ");
        $stmt->execute([$current_user_id, $story['created_by']]);
        $is_following = $stmt->fetchColumn() > 0;
        
        // Check if user liked the story
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM story_likes WHERE story_id = ? AND user_id = ?");
        $stmt->execute([$story_id, $current_user_id]);
        $story_liked = $stmt->fetchColumn() > 0;
    }
    
    // Get story like count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM story_likes WHERE story_id = ?");
    $stmt->execute([$story_id]);
    $story_like_count = $stmt->fetchColumn();

    // Fetch all parts
    $stmt = $pdo->prepare("SELECT * FROM story_parts WHERE story_id = ? ORDER BY part_number ASC");
    $stmt->execute([$story_id]);
    $all_parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($requested_part_number === null) {
        $story_part = end($all_parts);
    } else {
        foreach ($all_parts as $part) {
            if ($part['part_number'] === $requested_part_number) {
                $story_part = $part;
                break;
            }
        }
        if (!$story_part) {
            throw new Exception("Story part not found.");
        }
    }

    $dynamic_status = getStoryStatus($story_part['upload_date'], $story_part['prediction_deadline']);

    // Determine if this is the last (final) part of the story
    $max_part_number = max(array_column($all_parts, 'part_number'));
    $is_last_part = ($story_part['part_number'] == $max_part_number);
    
    // Fetch predictions
    $predictions_query = "
        SELECT 
            p.*, 
            u.user_name, 
            u.first_name,
            u.last_name,
            (SELECT COUNT(*) FROM likes WHERE prediction_id = p.prediction_id) AS like_count,
            " . ($is_logged_in ? "(SELECT COUNT(*) FROM likes WHERE prediction_id = p.prediction_id AND user_id = :current_user_id) AS user_liked" : "0 AS user_liked") . "
        FROM predictions p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.story_id = :story_id AND p.prediction_part_no = :part_number
    ";

    if ($sort_predictions_by === 'most_liked') {
        $predictions_query .= " ORDER BY like_count DESC, p.created_at DESC";
    } else {
        $predictions_query .= " ORDER BY p.created_at DESC";
    }

    $stmt = $pdo->prepare($predictions_query);
    $params = [
        'story_id' => $story_id,
        'part_number' => $story_part['part_number']
    ];
    if ($is_logged_in) {
        $params['current_user_id'] = $current_user_id;
    }
    $stmt->execute($params);
    $predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch comments
    $stmt = $pdo->prepare("
        SELECT c.*, u.user_name, u.first_name, u.last_name 
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.story_id = ? AND c.part_number = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$story_id, $story_part['part_number']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($story['title'] ?? 'Story Detail'); ?> - Story Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        /* Story body typography for comfortable reading */
        .story-body {
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 1.0625rem;
            line-height: 1.85;
            color: #374151;
        }
        /* Sticky comment sidebar */
        .comments-sidebar {
            position: sticky;
            top: 1.5rem;
            max-height: calc(100vh - 3rem);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }
        .comments-sidebar::-webkit-scrollbar {
            width: 4px;
        }
        .comments-sidebar::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 4px;
        }
        /* Spam alert animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        .shake { animation: shake 0.4s ease; }
    </style>
</head>
<body class="bg-gray-50">

<!-- Spam Alert Overlay -->
<div id="spam-alert" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black bg-opacity-40" onclick="closeSpamAlert()"></div>
    <div id="spam-alert-box" class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center z-10">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-ban text-3xl text-red-500"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Comment Blocked</h3>
        <p class="text-gray-600 mb-6">Spam's are not allowed</p>
        <button onclick="closeSpamAlert()" class="px-6 py-2 bg-red-500 text-white font-semibold rounded-lg hover:bg-red-600 transition">
            Got it
        </button>
    </div>
</div>

<div class="container mx-auto px-4 md:px-6 py-8" style="max-width: 1400px;">
    <a href="index.php" class="inline-flex items-center text-purple-600 hover:text-purple-800 font-semibold mb-6">
        <i class="fas fa-arrow-left mr-2"></i> Back to Stories
    </a>
    
    <?php if ($story && $story_part): ?>
    
    <!-- Guest Login Prompt -->
    <?php if (!$is_logged_in): ?>
    <div class="bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl shadow-lg p-6 mb-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold mb-2">
                    <i class="fas fa-gift mr-2"></i>Join Story Pulse to Predict & Win Rewards!
                </h3>
                <p class="text-white text-opacity-90">Login to make predictions, like stories, follow authors, and earn bonuses for accurate predictions!</p>
            </div>
            <div class="flex space-x-3">
                <a href="signin.php" class="px-6 py-3 bg-white text-purple-600 rounded-lg font-semibold hover:bg-opacity-90 transition">
                    Login
                </a>
                <a href="signup.php" class="px-6 py-3 bg-purple-700 text-white rounded-lg font-semibold hover:bg-purple-800 transition">
                    Sign Up
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Story Header Card -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6" style="margin-bottom: 1.5rem;">
        <div class="p-6 md:p-8">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <h1 class="text-4xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($story['title']); ?></h1>
                    <div class="flex items-center space-x-4 text-gray-600">
                        <span class="flex items-center">
                            <i class="fas fa-user-pen mr-2"></i>
                            By <span class="font-semibold ml-1"><?php echo htmlspecialchars($story['created_by']); ?></span>
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-tag mr-2"></i>
                            <?php echo htmlspecialchars($story['category']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($is_logged_in): ?>
                <div class="flex space-x-3">
                    <!-- Follow Button -->
                    <button onclick="toggleFollow()" id="follow-btn" 
                        class="px-5 py-2 rounded-lg font-semibold transition <?php echo $is_following ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-purple-600 text-white hover:bg-purple-700'; ?>">
                        <i class="fas fa-user-<?php echo $is_following ? 'check' : 'plus'; ?> mr-2"></i>
                        <span id="follow-text"><?php echo $is_following ? 'Following' : 'Follow'; ?></span>
                    </button>
                    
                    <!-- Like Story Button -->
                    <button onclick="toggleStoryLike()" id="story-like-btn"
                        class="px-5 py-2 rounded-lg font-semibold transition <?php echo $story_liked ? 'bg-red-100 text-red-600 hover:bg-red-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        <i class="fas fa-heart mr-2"></i>
                        <span id="story-like-count"><?php echo $story_like_count; ?></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <h2 class="text-2xl font-semibold text-purple-600 mb-4">Part <?php echo htmlspecialchars($story_part['part_number']); ?></h2>
            
            <div class="story-body">
                <?php echo nl2br(htmlspecialchars($story_part['content'])); ?>
            </div>
            
            <div class="mt-6 pt-6 border-t flex justify-between items-center text-sm text-gray-500">
                <span><i class="fas fa-calendar mr-2"></i>Published: <?php echo date('F j, Y', strtotime($story_part['upload_date'])); ?></span>
                <span><i class="fas fa-clock mr-2"></i>Prediction Deadline: <?php echo date('F j, Y, g:i A', strtotime($story_part['prediction_deadline'])); ?></span>
            </div>
        </div>
    </div>
    
    <?php if ($submission_message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $submission_message['status'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <div class="flex items-center">
            <i class="fas fa-<?php echo $submission_message['status'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3 text-xl"></i>
            <p class="font-medium"><?php echo htmlspecialchars($submission_message['message']); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- TWO-COLUMN LAYOUT: Left = story content + predictions | Right = comments -->
    <div class="flex gap-6 items-start">

        <!-- ===== LEFT COLUMN (80%) ===== -->
        <div class="flex-1 min-w-0" style="flex: 0 0 79%;">

            <!-- Prediction Box (Only if logged in, deadline not passed, and NOT the final part) -->
            <?php if ($is_logged_in && $dynamic_status === 'open_for_predictions' && !$is_last_part): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-lightbulb mr-2 text-yellow-500"></i>Make Your Prediction
                </h3>
                <form method="POST" action="">
                    <input type="hidden" name="story_id" value="<?php echo htmlspecialchars($story['story_id']); ?>">
                    <input type="hidden" name="part_number" value="<?php echo htmlspecialchars($story_part['part_number']); ?>">
                    <textarea name="prediction_text" rows="4" required
                        class="w-full p-4 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition text-gray-700 resize-none" 
                        placeholder="What happens next? Write your prediction here..."></textarea>
                    <button type="submit" name="submit_prediction" 
                        class="mt-3 px-6 py-2.5 gradient-bg text-white font-semibold rounded-lg hover:opacity-90 transition text-sm">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Prediction
                    </button>
                </form>
            </div>
            <?php elseif ($dynamic_status === 'closed' && !$is_last_part): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6 flex items-center gap-3">
                <i class="fas fa-lock text-2xl text-amber-400"></i>
                <p class="text-amber-800 font-medium">This part is now closed for predictions.</p>
            </div>
            <?php endif; ?>

            <!-- Community Predictions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-users mr-2 text-purple-600"></i>Community Predictions
                    </h3>
                    <div class="flex gap-2">
                        <a href="?story_id=<?php echo $story_id; ?>&part_number=<?php echo $story_part['part_number']; ?>&sort_predictions=newest" 
                           class="px-3 py-1.5 rounded-lg text-sm font-medium transition <?php echo $sort_predictions_by === 'newest' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            Newest
                        </a>
                        <a href="?story_id=<?php echo $story_id; ?>&part_number=<?php echo $story_part['part_number']; ?>&sort_predictions=most_liked" 
                           class="px-3 py-1.5 rounded-lg text-sm font-medium transition <?php echo $sort_predictions_by === 'most_liked' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                            Most Liked
                        </a>
                    </div>
                </div>

                <?php if (!empty($predictions)): ?>
                <div class="space-y-4">
                    <?php foreach ($predictions as $prediction): ?>
                    <div class="bg-gray-50 rounded-lg p-5 border-l-4 border-purple-400 prediction-item" data-prediction-id="<?php echo $prediction['prediction_id']; ?>">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full gradient-bg flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                    <?php echo strtoupper(substr($prediction['first_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($prediction['first_name'] . ' ' . $prediction['last_name']); ?></span>
                                    <span class="text-xs text-gray-400 block"><?php echo date('M j, Y', strtotime($prediction['created_at'])); ?><?php echo $prediction['is_edited'] ? ' · Edited' : ''; ?></span>
                                </div>
                            </div>
                            <!-- Show accuracy if deadline passed -->
                            <?php if ($dynamic_status === 'closed' && $prediction['manual_accuracy'] !== null): ?>
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold <?php echo $prediction['manual_accuracy'] >= 70 ? 'bg-green-100 text-green-800' : ($prediction['manual_accuracy'] >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <i class="fas fa-bullseye"></i>
                                <?php echo number_format($prediction['manual_accuracy'], 1); ?>% Accurate
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="prediction-content ml-12">
                            <p class="text-gray-700 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($prediction['prediction_text'])); ?></p>
                        </div>
                        
                        <div class="mt-3 ml-12 flex items-center justify-between text-sm">
                            <div class="flex items-center gap-4">
                                <?php if ($is_logged_in): ?>
                                <button class="like-button flex items-center gap-1.5 text-gray-500 hover:text-red-500 focus:outline-none transition" 
                                    data-prediction-id="<?php echo $prediction['prediction_id']; ?>" 
                                    data-liked="<?php echo $prediction['user_liked'] ? '1' : '0'; ?>">
                                    <i class="fas fa-heart <?php echo $prediction['user_liked'] ? 'text-red-500' : ''; ?>"></i>
                                    <span class="like-count font-medium" data-prediction-id="<?php echo $prediction['prediction_id']; ?>"><?php echo $prediction['like_count']; ?></span>
                                </button>
                                <?php else: ?>
                                <span class="flex items-center gap-1.5 text-gray-400">
                                    <i class="fas fa-heart"></i>
                                    <span><?php echo $prediction['like_count']; ?></span>
                                </span>
                                <?php endif; ?>
                            </div>
                            <!-- Edit/Delete buttons -->
                            <?php if ($is_logged_in && $dynamic_status === 'open_for_predictions' && $prediction['user_id'] == $current_user_id): ?>
                            <div class="flex gap-3">
                                <button class="edit-button text-blue-500 hover:text-blue-700 font-medium transition text-xs" 
                                    data-prediction-id="<?php echo $prediction['prediction_id']; ?>">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <button class="delete-button text-red-500 hover:text-red-700 font-medium transition text-xs" 
                                    data-prediction-id="<?php echo $prediction['prediction_id']; ?>">
                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-comments text-5xl text-gray-200 mb-4"></i>
                    <p class="text-gray-400">No predictions yet. Be the first to predict!</p>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- end left column -->

        <!-- ===== RIGHT COLUMN (20%) — Comments Panel ===== -->
        <div style="flex: 0 0 20%; min-width: 220px;">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 comments-sidebar">

                <!-- Header -->
                <div class="px-4 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                        <i class="fas fa-comment-dots text-blue-500"></i>
                        Comments
                    </h3>
                    <span class="bg-blue-50 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full">
                        <?php echo count($comments); ?>
                    </span>
                </div>

                <!-- Comment Form -->
                <?php if ($is_logged_in): ?>
                <div class="px-4 py-4 border-b border-gray-100 bg-gray-50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-7 h-7 rounded-full gradient-bg flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        </div>
                        <span class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                    </div>
                    <form id="comment-form" method="POST" action="">
                        <input type="hidden" name="story_id" value="<?php echo htmlspecialchars($story['story_id']); ?>">
                        <input type="hidden" name="part_number" value="<?php echo htmlspecialchars($story_part['part_number']); ?>">
                        <textarea id="comment-textarea" name="comment_text" rows="3" required
                            class="w-full p-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 transition resize-none text-gray-700 bg-white"
                            placeholder="Share your thoughts…"></textarea>
                        <button type="button" id="submit-comment-btn"
                            class="mt-2 w-full px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
                            <span id="submit-btn-text"><i class="fas fa-paper-plane mr-1"></i>Post Comment</span>
                            <span id="submit-btn-loading" class="hidden"><i class="fas fa-spinner fa-spin mr-1"></i>Checking…</span>
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="px-4 py-4 border-b border-gray-100 text-center">
                    <p class="text-xs text-gray-500 mb-2">Login to leave a comment</p>
                    <a href="signin.php" class="inline-block px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">Sign In</a>
                </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div class="px-4 py-3 space-y-4" id="comments-list">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="flex items-start gap-2">
                                <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xs flex-shrink-0 mt-0.5">
                                    <?php echo strtoupper(substr($comment['first_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-semibold text-gray-900 text-xs truncate"><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></span>
                                        <?php if ($is_logged_in && $comment['user_id'] == $current_user_id): ?>
                                        <button class="delete-comment flex-shrink-0 text-gray-300 hover:text-red-500 transition text-xs" data-comment-id="<?php echo $comment['comment_id']; ?>" title="Delete">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-gray-400 mb-1"><?php echo date('M j', strtotime($comment['created_at'])); ?></p>
                                    <p class="text-xs text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                    <?php if (!empty($comment['sentiment'])): ?>
                                    <span class="inline-flex items-center gap-1 mt-1.5 text-xs px-1.5 py-0.5 rounded-full font-medium
                                        <?php echo $comment['sentiment'] === 'Positive' ? 'bg-green-50 text-green-600' : ($comment['sentiment'] === 'Negative' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500'); ?>">
                                        <i class="fas fa-<?php echo $comment['sentiment'] === 'Positive' ? 'smile' : ($comment['sentiment'] === 'Negative' ? 'frown' : 'meh'); ?>"></i>
                                        <?php echo htmlspecialchars($comment['sentiment']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-comment-slash text-3xl text-gray-200 mb-2"></i>
                        <p class="text-xs text-gray-400"><?php echo $is_logged_in ? 'Be the first to comment!' : 'Login to add a comment.'; ?></p>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div><!-- end right column -->

    </div><!-- end two-column flex -->

    <?php else: ?>
    <div class="bg-white rounded-xl shadow-lg p-8 text-center">
        <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const storyId = <?php echo $story_id; ?>;
    const authorUserName = '<?php echo htmlspecialchars($story['created_by'] ?? ''); ?>';

    // ─── Spam Alert helpers ───────────────────────────────────────────────────
    function showSpamAlert() {
        const overlay = document.getElementById('spam-alert');
        const box = document.getElementById('spam-alert-box');
        overlay.classList.remove('hidden');
        box.classList.add('shake');
        setTimeout(() => box.classList.remove('shake'), 500);
    }
    function closeSpamAlert() {
        document.getElementById('spam-alert').classList.add('hidden');
    }

    // ─── Comment Submit (Full AJAX — no page reload) ─────────────────────────
    const submitCommentBtn = document.getElementById('submit-comment-btn');
    if (submitCommentBtn) {
        submitCommentBtn.addEventListener('click', async function () {
            const textarea  = document.getElementById('comment-textarea');
            const text      = textarea ? textarea.value.trim() : '';
            const storyIdInput   = document.querySelector('#comment-form input[name="story_id"]');
            const partNumInput   = document.querySelector('#comment-form input[name="part_number"]');

            if (!text) {
                textarea.focus();
                return;
            }

            // ── Show loading state ──────────────────────────────────────
            document.getElementById('submit-btn-text').classList.add('hidden');
            document.getElementById('submit-btn-loading').classList.remove('hidden');
            submitCommentBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action',       'submit_comment');
                formData.append('comment_text', text);
                formData.append('story_id',     storyIdInput ? storyIdInput.value : '');
                formData.append('part_number',  partNumInput ? partNumInput.value : '');

                const res  = await fetch('story_detail.php', { method: 'POST', body: formData });

                // ── Check HTTP level errors ─────────────────────────────
                if (!res.ok) {
                    throw new Error(`Server returned HTTP ${res.status} ${res.statusText}`);
                }

                let data;
                try {
                    data = await res.json();
                } catch (jsonErr) {
                    const rawText = await res.text().catch(() => '(could not read response)');
                    showApiErrorAlert(
                        'Server Response Error',
                        'The server returned an unexpected response (not valid JSON).',
                        rawText.substring(0, 300)
                    );
                    return;
                }

                // ── Handle response types ───────────────────────────────
                if (data.success) {
                    // ✅ Inject the new comment into the sidebar without reload
                    textarea.value = '';
                    injectNewComment(data);
                    showInlineSuccess('Comment posted!');

                } else if (data.error_type === 'spam') {
                    showSpamAlert();
                    textarea.value = '';

                } else if (data.error_type === 'api_error') {
                    showApiErrorAlert(
                        'API Unavailable',
                        data.message || 'One of the AI APIs is not responding.',
                        data.debug || ''
                    );

                } else if (data.error_type === 'auth') {
                    window.location.href = 'signin.php';

                } else if (data.error_type === 'db_error') {
                    showApiErrorAlert(
                        'Database Error',
                        data.message || 'Failed to save your comment.',
                        data.debug || ''
                    );

                } else {
                    showApiErrorAlert(
                        'Unknown Error',
                        data.message || 'Something went wrong. Please try again.',
                        JSON.stringify(data)
                    );
                }

            } catch (networkErr) {
                // ── Network / fetch failure ─────────────────────────────
                showApiErrorAlert(
                    'Network Error',
                    'Could not reach the server. Check your connection or that the Python API is running.',
                    networkErr.message
                );
            }

            // ── Restore button ──────────────────────────────────────────
            document.getElementById('submit-btn-text').classList.remove('hidden');
            document.getElementById('submit-btn-loading').classList.add('hidden');
            submitCommentBtn.disabled = false;
        });
    }

    // ─── Inject new comment into sidebar (no reload) ──────────────────────
    function injectNewComment(data) {
        const list = document.getElementById('comments-list');
        if (!list) return;

        // Remove "no comments" placeholder if present
        const empty = list.querySelector('.text-center');
        if (empty) empty.remove();

        const sentimentColor = data.sentiment === 'Positive'
            ? 'bg-green-50 text-green-600'
            : (data.sentiment === 'Negative' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500');
        const sentimentIcon  = data.sentiment === 'Positive'
            ? 'smile' : (data.sentiment === 'Negative' ? 'frown' : 'meh');
        const sentimentBadge = data.sentiment
            ? `<span class="inline-flex items-center gap-1 mt-1.5 text-xs px-1.5 py-0.5 rounded-full font-medium ${sentimentColor}">
                   <i class="fas fa-${sentimentIcon}"></i> ${data.sentiment}
               </span>` : '';

        const safeText = data.comment_text
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/\n/g,'<br>');

        const html = `
        <div class="comment-item pb-4 border-b border-gray-100" data-comment-id="${data.comment_id}">
            <div class="flex items-start gap-2">
                <div class="w-7 h-7 rounded-full gradient-bg flex items-center justify-center text-white font-bold text-xs flex-shrink-0 mt-0.5">
                    ${data.first_name.charAt(0).toUpperCase()}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-semibold text-gray-900 text-xs truncate">${escHtml(data.first_name + ' ' + data.last_name)}</span>
                        <button class="delete-comment flex-shrink-0 text-gray-300 hover:text-red-500 transition text-xs"
                            data-comment-id="${data.comment_id}" title="Delete">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mb-1">${data.created_at}</p>
                    <p class="text-xs text-gray-700 leading-relaxed">${safeText}</p>
                    ${sentimentBadge}
                </div>
            </div>
        </div>`;

        // Prepend (newest first)
        list.insertAdjacentHTML('afterbegin', html);

        // Re-attach delete listener on the new comment
        const newBtn = list.querySelector(`[data-comment-id="${data.comment_id}"].delete-comment`);
        if (newBtn) attachDeleteComment(newBtn);

        // Update count badge
        const badge = document.querySelector('.comments-sidebar .rounded-full');
        if (badge) badge.textContent = parseInt(badge.textContent.trim(), 10) + 1;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Inline success flash inside sidebar ─────────────────────────────
    function showInlineSuccess(msg) {
        const existing = document.getElementById('comment-success-flash');
        if (existing) existing.remove();
        const flash = document.createElement('div');
        flash.id = 'comment-success-flash';
        flash.className = 'mx-4 mt-3 px-3 py-2 bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg flex items-center gap-2';
        flash.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
        const form = document.getElementById('comment-form');
        if (form) form.insertAdjacentElement('afterend', flash);
        setTimeout(() => flash.remove(), 3000);
    }

    // ─── API Error Alert Modal ────────────────────────────────────────────
    function showApiErrorAlert(title, message, debug) {
        // Remove existing if any
        const old = document.getElementById('api-error-modal');
        if (old) old.remove();

        const debugHtml = debug
            ? `<div class="mt-3 text-left bg-gray-100 rounded p-2 text-xs text-gray-500 font-mono overflow-auto max-h-24">${escHtml(debug)}</div>`
            : '';

        const modal = document.createElement('div');
        modal.id = 'api-error-modal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black bg-opacity-40" onclick="document.getElementById('api-error-modal').remove()"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl p-7 max-w-sm w-full mx-4 text-center z-10">
                <div class="w-14 h-14 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-exclamation-triangle text-2xl text-orange-500"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">${escHtml(title)}</h3>
                <p class="text-sm text-gray-600">${escHtml(message)}</p>
                ${debugHtml}
                <button onclick="document.getElementById('api-error-modal').remove()"
                    class="mt-5 px-6 py-2 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition text-sm">
                    Close
                </button>
            </div>`;
        document.body.appendChild(modal);
    }

    // ─── Follow / Unfollow ───────────────────────────────────────────────────
    function toggleFollow() {
        if (!isLoggedIn) {
            window.location.href = 'signin.php';
            return;
        }
        
        const btn = document.getElementById('follow-btn');
        const text = document.getElementById('follow-text');
        const isFollowing = text.textContent === 'Following';
        const action = isFollowing ? 'unfollow' : 'follow';
        
        const formData = new FormData();
        formData.append('author_user_name', authorUserName);
        formData.append('action', action);
        
        fetch('story_detail.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (action === 'follow') {
                    btn.className = 'px-5 py-2 rounded-lg font-semibold transition bg-gray-200 text-gray-700 hover:bg-gray-300';
                    btn.innerHTML = '<i class="fas fa-user-check mr-2"></i><span id="follow-text">Following</span>';
                } else {
                    btn.className = 'px-5 py-2 rounded-lg font-semibold transition bg-purple-600 text-white hover:bg-purple-700';
                    btn.innerHTML = '<i class="fas fa-user-plus mr-2"></i><span id="follow-text">Follow</span>';
                }
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // ─── Story Like Toggle ───────────────────────────────────────────────────
    function toggleStoryLike() {
        if (!isLoggedIn) {
            window.location.href = 'signin.php';
            return;
        }
        
        const btn = document.getElementById('story-like-btn');
        const countSpan = document.getElementById('story-like-count');
        const isLiked = btn.classList.contains('bg-red-100');
        const action = isLiked ? 'unlike_story' : 'like_story';
        
        const formData = new FormData();
        formData.append('story_id', storyId);
        formData.append('action', action);
        
        fetch('story_detail.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                countSpan.textContent = data.like_count;
                if (action === 'like_story') {
                    btn.className = 'px-5 py-2 rounded-lg font-semibold transition bg-red-100 text-red-600 hover:bg-red-200';
                } else {
                    btn.className = 'px-5 py-2 rounded-lg font-semibold transition bg-gray-100 text-gray-700 hover:bg-gray-200';
                }
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    // ─── Prediction Like / Unlike ────────────────────────────────────────────
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', async function() {
            if (!isLoggedIn) {
                window.location.href = 'signin.php';
                return;
            }
            
            const predictionId = this.dataset.predictionId;
            const liked = this.dataset.liked === '1';
            const action = liked ? 'unlike' : 'like';
            
            const formData = new FormData();
            formData.append('prediction_id', predictionId);
            formData.append('action', action);
            
            try {
                const response = await fetch('story_detail.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    const likeCountSpan = document.querySelector(`.like-count[data-prediction-id="${predictionId}"]`);
                    likeCountSpan.textContent = data.new_like_count;
                    
                    const heartIcon = this.querySelector('.fas.fa-heart');
                    if (action === 'like') {
                        heartIcon.classList.add('text-red-500');
                        this.dataset.liked = '1';
                    } else {
                        heartIcon.classList.remove('text-red-500');
                        this.dataset.liked = '0';
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
    });
    
    // ─── Delete Prediction ───────────────────────────────────────────────────
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to delete this prediction?')) return;
            
            const predictionId = this.dataset.predictionId;
            const formData = new FormData();
            formData.append('prediction_id', predictionId);
            formData.append('action', 'delete');
            
            try {
                const response = await fetch('story_detail.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    document.querySelector(`.prediction-item[data-prediction-id="${predictionId}"]`).remove();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
    });
    
    // ─── Edit Prediction ─────────────────────────────────────────────────────
    document.querySelectorAll('.edit-button').forEach(button => {
        button.addEventListener('click', function() {
            const predictionId = this.dataset.predictionId;
            const predictionItem = this.closest('.prediction-item');
            const contentContainer = predictionItem.querySelector('.prediction-content');
            const originalText = contentContainer.querySelector('p').textContent.trim();
            
            contentContainer.innerHTML = `
                <textarea class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500 text-sm resize-none" rows="4">${originalText}</textarea>
            `;
            
            const actionButtons = this.closest('.flex');
            actionButtons.innerHTML = `
                <button class="save-button text-green-600 hover:text-green-800 font-medium transition text-xs" data-prediction-id="${predictionId}">
                    <i class="fas fa-save mr-1"></i>Save
                </button>
                <button class="cancel-button text-gray-500 hover:text-gray-700 font-medium transition text-xs">
                    <i class="fas fa-times mr-1"></i>Cancel
                </button>
            `;
            
            actionButtons.querySelector('.save-button').addEventListener('click', async function() {
                const updatedText = contentContainer.querySelector('textarea').value.trim();
                const formData = new FormData();
                formData.append('prediction_id', predictionId);
                formData.append('prediction_text', updatedText);
                formData.append('action', 'edit');
                
                try {
                    const response = await fetch('story_detail.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    
                    if (data.success) {
                        contentContainer.innerHTML = `<p class="text-gray-700 text-sm leading-relaxed">${updatedText.replace(/\n/g, '<br>')}</p>`;
                        const dateSpan = predictionItem.querySelector('.text-xs.text-gray-400');
                        if (dateSpan && !dateSpan.textContent.includes('Edited')) {
                            dateSpan.textContent += ' · Edited';
                        }
                        restoreButtons(actionButtons, predictionId);
                    } else {
                        alert(data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
            
            actionButtons.querySelector('.cancel-button').addEventListener('click', function() {
                contentContainer.innerHTML = `<p class="text-gray-700 text-sm leading-relaxed">${originalText.replace(/\n/g, '<br>')}</p>`;
                restoreButtons(actionButtons, predictionId);
            });
        });
    });
    
    function restoreButtons(actionButtons, predictionId) {
        actionButtons.innerHTML = `
            <button class="edit-button text-blue-500 hover:text-blue-700 font-medium transition text-xs" data-prediction-id="${predictionId}">
                <i class="fas fa-edit mr-1"></i>Edit
            </button>
            <button class="delete-button text-red-500 hover:text-red-700 font-medium transition text-xs" data-prediction-id="${predictionId}">
                <i class="fas fa-trash-alt mr-1"></i>Delete
            </button>
        `;
        attachPredictionEvents();
    }
    
    function attachPredictionEvents() {
        document.querySelectorAll('.edit-button:not([data-listener])').forEach(btn => {
            btn.dataset.listener = 'true';
        });
        document.querySelectorAll('.delete-button:not([data-listener])').forEach(btn => {
            btn.dataset.listener = 'true';
        });
    }

    // ─── Delete Comment ──────────────────────────────────────────────────────
    function attachDeleteComment(btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this comment?')) return;

            const id = this.dataset.commentId;
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', id);

            try {
                const res  = await fetch('story_detail.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    const item = this.closest('.comment-item');
                    if (item) item.remove();

                    // Update count badge
                    const badge = document.querySelector('.comments-sidebar .rounded-full');
                    if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent.trim(), 10) - 1);
                } else {
                    showApiErrorAlert('Delete Failed', data.message || 'Could not delete comment.', '');
                }
            } catch (err) {
                showApiErrorAlert('Network Error', 'Could not reach server to delete comment.', err.message);
            }
        });
    }

    // Attach to all existing delete buttons on page load
    document.querySelectorAll('.delete-comment').forEach(btn => attachDeleteComment(btn));

</script>

<!-- ============================================================
     STORYVERSE VISUAL ENHANCEMENTS
     1. Gold ink cursor trail (canvas overlay)
     2. Typewriter effect on story title
     3. Story body fade-in paragraph reveal on load
     4. Prediction card entrance animation
     No external dependencies — pure vanilla JS + CSS.
     Zero interference with existing AJAX / PHP logic.
============================================================ -->
<canvas id="sv-cursor-canvas" style="
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    pointer-events: none;
    z-index: 9999;
    opacity: 0.75;
"></canvas>

<style>
/* ── Typewriter title ─────────────────────────────────── */
#sv-story-title {
    display: inline-block;
    border-right: 3px solid #c9a84c;
    white-space: normal;
    overflow: hidden;
    animation: sv-blink-cursor 0.75s step-end infinite;
}
@keyframes sv-blink-cursor {
    0%, 100% { border-color: #c9a84c; }
    50%       { border-color: transparent; }
}

/* ── Story body paragraphs fade-in ───────────────────── */
.sv-para {
    opacity: 0;
    transform: translateY(14px);
    transition: opacity 0.55s ease, transform 0.55s ease;
}
.sv-para.sv-visible {
    opacity: 1;
    transform: translateY(0);
}

/* ── Prediction card slide-in ────────────────────────── */
.prediction-item {
    opacity: 0;
    transform: translateX(-18px);
    transition: opacity 0.45s ease, transform 0.45s ease;
}
.prediction-item.sv-visible {
    opacity: 1;
    transform: translateX(0);
}

/* ── Part nav pill hover glow ────────────────────────── */
.sv-part-pill {
    transition: box-shadow 0.25s ease, transform 0.2s ease;
}
.sv-part-pill:hover {
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.45);
    transform: translateY(-1px);
}
</style>

<script>
(function () {
    'use strict';

    /* ── 1. GOLD INK CURSOR TRAIL ─────────────────────────────────── */
    const canvas = document.getElementById('sv-cursor-canvas');
    const ctx    = canvas.getContext('2d');

    // Resize canvas to window
    function resizeCanvas() {
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    const GOLD    = '#c9a84c';
    const GOLD2   = '#e8c97a';
    const NAVY    = '#0d1b2a';
    const MAX_PTS = 28;      // trail length
    const trail   = [];      // { x, y, age, r }
    let   mx = -999, my = -999;

    // Spawn particles on move
    document.addEventListener('mousemove', e => {
        mx = e.clientX;
        my = e.clientY;
        // Add a small ink dot at cursor
        trail.push({
            x:   mx + (Math.random() - 0.5) * 6,
            y:   my + (Math.random() - 0.5) * 6,
            age: 0,
            r:   Math.random() * 3.5 + 1.5,
            vx:  (Math.random() - 0.5) * 0.8,
            vy:  (Math.random() - 0.5) * 0.8 - 0.4   // slight upward drift
        });
        if (trail.length > MAX_PTS) trail.shift();
    });

    // Tiny sparkle burst on click
    const bursts = [];
    document.addEventListener('click', e => {
        for (let i = 0; i < 10; i++) {
            const angle = (Math.PI * 2 / 10) * i;
            bursts.push({
                x: e.clientX, y: e.clientY,
                vx: Math.cos(angle) * (Math.random() * 3 + 1),
                vy: Math.sin(angle) * (Math.random() * 3 + 1),
                life: 1.0,
                r: Math.random() * 2.5 + 1
            });
        }
    });

    function renderTrail() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw trail dots
        trail.forEach((pt, i) => {
            pt.age++;
            pt.x += pt.vx;
            pt.y += pt.vy;
            const alpha  = (1 - pt.age / MAX_PTS) * 0.85;
            const radius = pt.r * (1 - pt.age / MAX_PTS);
            if (alpha <= 0 || radius <= 0) return;

            // Alternate gold shades for depth
            const color = i % 2 === 0 ? GOLD : GOLD2;
            ctx.beginPath();
            ctx.arc(pt.x, pt.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.globalAlpha = alpha;
            ctx.fill();
        });

        // Draw click bursts
        for (let i = bursts.length - 1; i >= 0; i--) {
            const b = bursts[i];
            b.x  += b.vx;
            b.y  += b.vy;
            b.vy += 0.08; // gravity
            b.life -= 0.045;
            if (b.life <= 0) { bursts.splice(i, 1); continue; }

            ctx.beginPath();
            ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
            ctx.fillStyle = GOLD;
            ctx.globalAlpha = b.life;
            ctx.fill();
        }

        ctx.globalAlpha = 1;
        requestAnimationFrame(renderTrail);
    }
    renderTrail();


    /* ── 2. TYPEWRITER ON STORY TITLE ────────────────────────────── */
    const h1 = document.querySelector('.bg-white.rounded-xl h1');
    if (h1) {
        const fullText = h1.textContent.trim();
        h1.textContent = '';
        h1.id = 'sv-story-title';

        let idx = 0;
        function typeChar() {
            if (idx < fullText.length) {
                h1.textContent += fullText[idx++];
                setTimeout(typeChar, idx === 1 ? 300 : 38 + Math.random() * 22);
            } else {
                // Remove blinking cursor after typing completes
                setTimeout(() => h1.style.borderRight = 'none', 1200);
            }
        }
        // Start after a small page-settle delay
        setTimeout(typeChar, 420);
    }


    /* ── 3. STORY BODY PARAGRAPH FADE-IN ─────────────────────────── */
    const storyBody = document.querySelector('.story-body');
    if (storyBody) {
        // Wrap each sentence-group / line into a span for staggered reveal
        const raw = storyBody.innerHTML;
        // Split on <br> tags to get natural paragraph breaks
        const chunks = raw.split(/(<br\s*\/?>)/gi);
        let wrapped = '';
        let paraIdx = 0;
        chunks.forEach(chunk => {
            if (/^<br/i.test(chunk)) {
                wrapped += chunk;
            } else if (chunk.trim()) {
                wrapped += `<span class="sv-para" style="display:block;transition-delay:${paraIdx * 80}ms">${chunk}</span>`;
                paraIdx++;
            } else {
                wrapped += chunk;
            }
        });
        storyBody.innerHTML = wrapped;

        // IntersectionObserver to reveal on scroll
        const obs = new IntersectionObserver(entries => {
            entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('sv-visible'); obs.unobserve(e.target); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('.sv-para').forEach(el => obs.observe(el));
    }


    /* ── 4. PREDICTION CARDS STAGGERED ENTRANCE ──────────────────── */
    const predItems = document.querySelectorAll('.prediction-item');
    if (predItems.length) {
        const predObs = new IntersectionObserver(entries => {
            entries.forEach((e, i) => {
                if (e.isIntersecting) {
                    setTimeout(() => e.target.classList.add('sv-visible'), i * 90);
                    predObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.05 });
        predItems.forEach(el => predObs.observe(el));
    }


    /* ── 5. PART NAV PILL GLOW ───────────────────────────────────── */
    document.querySelectorAll('a[href*="part_number"]').forEach(el => {
        el.classList.add('sv-part-pill');
    });

})();
</script>

</body>
</html>