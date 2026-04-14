<?php
session_start();
require_once 'db_connect.php'; // Your database connection

// Check if user is logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$current_user_id = $_SESSION['user_id']; // Get current logged-in user ID

$story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : 0;
$requested_part_number = isset($_GET['part_number']) ? (int)$_GET['part_number'] : null;
$sort_predictions_by = isset($_GET['sort_predictions']) ? $_GET['sort_predictions'] : 'newest'; // 'newest' or 'most_liked'

$story = null;
$story_part = null;
$error_message = '';
$all_parts = []; // To store all released parts for navigation
$predictions = []; // To store predictions for the current part
$dynamic_status = 'unknown'; // Initialize dynamic status

// Helper function to determine story/part status based on dates
function getStoryStatus($upload_date, $prediction_deadline) {
    $current_time = time();
    $upload_timestamp = strtotime($upload_date);
    $deadline_timestamp = strtotime($prediction_deadline);

    if ($current_time < $upload_timestamp) {
        return 'coming_soon';
    } elseif ($current_time >= $upload_timestamp && $current_time <= $deadline_timestamp) {
        return 'active';
    } else {
        return 'completed';
    }
}

if ($story_id > 0) {
    try {
        // Fetch story details
        $stmt = $pdo->prepare("SELECT * FROM stories WHERE story_id = :story_id");
        $stmt->execute(['story_id' => $story_id]);
        $story = $stmt->fetch();

        if ($story) {
            // Determine the part number to display
            // If no specific part number is requested, default to the current_part_no from the stories table
            if ($requested_part_number === null) {
                $requested_part_number = $story['current_part_no'];
            }

            // Fetch the content for the requested part number
            $stmt_part = $pdo->prepare("SELECT * FROM story_parts WHERE story_id = :story_id AND part_number = :part_number");
            $stmt_part->execute(['story_id' => $story_id, 'part_number' => $requested_part_number]);
            $story_part = $stmt_part->fetch();

            if ($story_part) {
                // Determine the dynamic status of the CURRENTLY VIEWED PART
                $dynamic_status = getStoryStatus($story_part['upload_date'], $story_part['prediction_deadline']);

                // If the story is 'coming_soon' (based on the current part's dates), prevent content display
                if ($dynamic_status === 'coming_soon') {
                    $error_message = "This story part is coming soon and not yet available for reading.";
                    $story_part = null; // Clear part data to prevent display
                }

            } else {
                $error_message = "Story part " . htmlspecialchars($requested_part_number) . " not found or not yet released.";
            }

            // Fetch all released parts for navigation (only up to current_part_no in stories table)
            $stmt_all_parts = $pdo->prepare("SELECT part_id, part_number, upload_date FROM story_parts WHERE story_id = :story_id AND part_number <= :current_released_part ORDER BY part_number ASC");
            $stmt_all_parts->execute(['story_id' => $story_id, 'current_released_part' => $story['current_part_no']]);
            $all_parts = $stmt_all_parts->fetchAll();

            // --- Handle Prediction Submission ---
            // Only allow submission if the story part is 'active'
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prediction']) && $dynamic_status === 'active') {
                $prediction_text = trim($_POST['prediction_text']);
                $prediction_part_no = (int)$_POST['prediction_part_no'];

                if (empty($prediction_text)) {
                    $_SESSION['prediction_message'] = 'Prediction text cannot be empty.';
                    $_SESSION['prediction_message_type'] = 'error';
                } else {
                    try {
                        // Check if user already submitted a prediction for this part
                        $stmt_check = $pdo->prepare("SELECT prediction_id FROM predictions WHERE story_id = :story_id AND prediction_part_no = :part_no AND user_id = :user_id");
                        $stmt_check->execute([
                            'story_id' => $story_id,
                            'part_no' => $prediction_part_no,
                            'user_id' => $current_user_id
                        ]);

                        if ($stmt_check->fetch()) {
                            $_SESSION['prediction_message'] = 'You have already submitted a prediction for this part.';
                            $_SESSION['prediction_message_type'] = 'error';
                        } else {
                            $stmt_insert = $pdo->prepare("INSERT INTO predictions (story_id, prediction_part_no, user_id, prediction_text) VALUES (:story_id, :part_no, :user_id, :prediction_text)");
                            $stmt_insert->execute([
                                'story_id' => $story_id,
                                'part_no' => $prediction_part_no,
                                'user_id' => $current_user_id,
                                'prediction_text' => $prediction_text
                            ]);
                            $_SESSION['prediction_message'] = 'Your prediction has been submitted!';
                            $_SESSION['prediction_message_type'] = 'success';
                        }
                    } catch (PDOException $e) {
                        error_log("Prediction submission error: " . $e->getMessage());
                        $_SESSION['prediction_message'] = 'An error occurred while submitting your prediction.';
                        $_SESSION['prediction_message_type'] = 'error';
                    }
                }
                // Redirect to clear POST data and show message
                header('Location: story_detail.php?story_id=' . htmlspecialchars($story_id) . '&part_number=' . htmlspecialchars($requested_part_number));
                exit();
            }

            // --- Fetch Predictions for Display ---
            $prediction_order_by = '';
            if ($sort_predictions_by === 'most_liked') {
                $prediction_order_by = ' ORDER BY like_count DESC, p.created_at DESC';
            } else { // 'newest'
                $prediction_order_by = ' ORDER BY p.created_at DESC';
            }

            $stmt_predictions = $pdo->prepare("
                SELECT
                    p.prediction_id,
                    p.prediction_text,
                    p.created_at,
                    u.user_name,
                    u.first_name,
                    u.last_name,
                    COUNT(l.like_id) AS like_count,
                    MAX(CASE WHEN l.user_id = :current_user_id THEN 1 ELSE 0 END) AS liked_by_current_user
                FROM predictions p
                JOIN users u ON p.user_id = u.id
                LEFT JOIN likes l ON p.prediction_id = l.prediction_id
                WHERE p.story_id = :story_id AND p.prediction_part_no = :part_number
                GROUP BY p.prediction_id, p.prediction_text, p.created_at, u.user_name, u.first_name, u.last_name
                " . $prediction_order_by
            );
            $stmt_predictions->execute([
                'story_id' => $story_id,
                'part_number' => $requested_part_number,
                'current_user_id' => $current_user_id
            ]);
            $predictions = $stmt_predictions->fetchAll();

            // Fetch total prediction count for the current part
            $stmt_total_predictions = $pdo->prepare("SELECT COUNT(*) FROM predictions WHERE story_id = :story_id AND prediction_part_no = :part_number");
            $stmt_total_predictions->execute(['story_id' => $story_id, 'part_number' => $requested_part_number]);
            $total_predictions_count = $stmt_total_predictions->fetchColumn();

        } else {
            $error_message = "Story not found.";
        }
    } catch (PDOException $e) {
        error_log("Error fetching story details or predictions: " . $e->getMessage());
        $error_message = "An error occurred while loading the story. Please try again.";
    }
} else {
    $error_message = "No story ID provided.";
}

// Format prediction deadline for display
$deadline_display = 'N/A';
if ($story_part && $story_part['prediction_deadline']) {
    $deadline_timestamp = strtotime($story_part['prediction_deadline']);
    $time_diff = $deadline_timestamp - time();

    if ($time_diff > 0) {
        $days = floor($time_diff / (60 * 60 * 24));
        $hours = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($time_diff % (60 * 60)) / 60);
        $seconds = $time_diff % 60;
        $deadline_display = "Predictions close in: {$days}d {$hours}h {$minutes}m {$seconds}s";
    } else {
        $deadline_display = "Prediction deadline passed.";
    }
}

// Determine status display colors for the header
$header_status_text = ucfirst(str_replace('_', ' ', $dynamic_status));
$header_status_color_class = 'bg-green-100 text-green-700';
$header_ping_color_class = 'bg-green-500';
if ($dynamic_status === 'coming_soon') {
    $header_status_color_class = 'bg-yellow-100 text-yellow-700';
    $header_ping_color_class = 'bg-yellow-500';
} elseif ($dynamic_status === 'completed') {
    $header_status_color_class = 'bg-blue-100 text-blue-700';
    $header_ping_color_class = 'bg-blue-500';
}

// Get and clear prediction message from session
$prediction_message = $_SESSION['prediction_message'] ?? '';
$prediction_message_type = $_SESSION['prediction_message_type'] ?? '';
unset($_SESSION['prediction_message']);
unset($_SESSION['prediction_message_type']);

// Use provided cover image URL or a placeholder
$cover_image_src = !empty($story['cover_image_url'] ?? '') ? htmlspecialchars($story['cover_image_url']) : 'https://placehold.co/800x400/e0e7ff/6366f1?text=Story+Cover';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($story['title'] ?? 'Story Not Found'); ?> - StoryPulse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Light gray background */
            color: #334155; /* Dark slate gray text */
        }
        /* Custom scrollbar for better aesthetics */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .prediction-message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 500;
            text-align: center;
        }
        .prediction-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .prediction-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Navigation Bar -->
    <nav class="bg-white shadow-sm py-4 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center space-x-4">
            <h1 class="text-2xl font-bold text-indigo-600">StoryPulse</h1>
            <div class="hidden md:flex space-x-4">
                <a href="index.php" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors duration-200">Home</a>
                <a href="stories.php" class="text-indigo-600 font-medium transition-colors duration-200">Stories</a>
                <a href="#" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors duration-200">Leaderboard</a>
                <a href="#" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors duration-200">How It Works</a>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <!-- User ID/Profile Placeholder -->
            <div class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-semibold">
                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?>
            </div>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors duration-200">Logout</a>
            <!-- Mobile Menu Button (hidden on desktop) -->
            <button class="md:hidden text-gray-600 hover:text-indigo-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-4 py-8">
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php elseif ($story): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 md:p-8 mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($story['title']); ?></h2>
                        <p class="text-gray-500 text-sm">By <?php echo htmlspecialchars($story['created_by']); ?> • <?php echo htmlspecialchars(date('M j, Y', strtotime($story['created_at']))); ?></p>
                    </div>
                    <div class="flex items-center mt-4 md:mt-0">
                        <div class="px-3 py-1 <?php echo $header_status_color_class; ?> text-xs font-semibold rounded-full flex items-center mr-4">
                            <?php if ($dynamic_status === 'active'): ?>
                                <span class="relative flex h-2 w-2 mr-1">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full <?php echo str_replace('bg-', 'bg-', $header_ping_color_class); ?> opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 <?php echo $header_ping_color_class; ?>"></span>
                                </span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($header_status_text); ?>
                        </div>
                        <span class="text-gray-600 text-sm" id="deadline-timer"><?php echo htmlspecialchars($deadline_display); ?></span>
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Story Cover Image -->
                <div class="mb-6 rounded-lg overflow-hidden shadow-md">
                    <img src="<?php echo $cover_image_src; ?>" onerror="this.onerror=null;this.src='https://placehold.co/800x400/e0e7ff/6366f1?text=Story+Cover';" alt="Story Cover" class="w-full h-auto object-cover max-h-96">
                </div>

                <?php if ($dynamic_status === 'coming_soon'): ?>
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Coming Soon!</strong>
                        <span class="block sm:inline">This story part is not yet available for reading. Check back after <?php echo htmlspecialchars(date('M j, Y', strtotime($story_part['upload_date'] ?? ''))); ?>.</span>
                    </div>
                <?php else: ?>
                    <h3 class="text-2xl font-semibold text-gray-800 mb-4">Part <?php echo htmlspecialchars($story_part['part_number'] ?? 'N/A'); ?></h3>
                    <div class="prose max-w-none text-gray-700 leading-relaxed text-lg">
                        <?php echo nl2br(htmlspecialchars($story_part['content'] ?? 'No content available for this part.')); ?>
                    </div>

                    <!-- Part Navigation -->
                    <div class="flex justify-between items-center mt-8 mb-4">
                        <?php
                        $current_part_index = array_search($requested_part_number, array_column($all_parts, 'part_number'));
                        $prev_part_number = ($current_part_index !== false && $current_part_index > 0) ? $all_parts[$current_part_index - 1]['part_number'] : null;
                        $next_part_number = ($current_part_index !== false && $current_part_index < count($all_parts) - 1) ? $all_parts[$current_part_index + 1]['part_number'] : null;
                        ?>
                        <a href="<?php echo $prev_part_number ? 'story_detail.php?story_id=' . htmlspecialchars($story_id) . '&part_number=' . htmlspecialchars($prev_part_number) : '#'; ?>"
                           class="px-5 py-2 rounded-lg font-semibold transition-colors duration-200
                           <?php echo $prev_part_number ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-gray-100 text-gray-400 cursor-not-allowed'; ?>">
                            &larr; Previous Part
                        </a>
                        <span class="text-lg font-semibold text-gray-700">Part <?php echo htmlspecialchars($requested_part_number); ?> / <?php echo htmlspecialchars($story['total_parts']); ?></span>
                        <a href="<?php echo $next_part_number ? 'story_detail.php?story_id=' . htmlspecialchars($story_id) . '&part_number=' . htmlspecialchars($next_part_number) : '#'; ?>"
                           class="px-5 py-2 rounded-lg font-semibold transition-colors duration-200
                           <?php echo $next_part_number ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-gray-100 text-gray-400 cursor-not-allowed'; ?>">
                            Next Part &rarr;
                        </a>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <div class="mb-8">
                        <?php if ($prediction_message): ?>
                            <div class="prediction-message <?php echo htmlspecialchars($prediction_message_type); ?>">
                                <p><?php echo htmlspecialchars($prediction_message); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-xl font-semibold text-gray-800">Your Prediction</h4>
                            <span class="text-sm text-gray-500" id="total-predictions-count"><?php echo htmlspecialchars($total_predictions_count); ?> predictions</span>
                        </div>

                        <?php if ($dynamic_status === 'active'): ?>
                            <form action="story_detail.php?story_id=<?php echo htmlspecialchars($story_id); ?>&part_number=<?php echo htmlspecialchars($requested_part_number); ?>" method="POST">
                                <textarea name="prediction_text" class="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200 resize-y min-h-[120px]" placeholder="What do you think happens next? Share your prediction here..." required></textarea>
                                <input type="hidden" name="prediction_part_no" value="<?php echo htmlspecialchars($requested_part_number); ?>">
                                <button type="submit" name="submit_prediction" class="mt-4 px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition-colors duration-200 float-right">Submit Prediction</button>
                                <div class="clear-both"></div> <!-- Clear float -->
                            </form>
                        <?php else: /* Status is 'completed' */ ?>
                            <div class="bg-gray-100 border border-gray-300 text-gray-600 px-4 py-3 rounded relative mb-4 text-center">
                                <p>Prediction submission is closed for this part.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <div>
                        <h4 class="text-xl font-semibold text-gray-800 mb-4">Community Predictions</h4>
                        <div class="flex space-x-4 mb-6">
                            <a href="story_detail.php?story_id=<?php echo htmlspecialchars($story_id); ?>&part_number=<?php echo htmlspecialchars($requested_part_number); ?>&sort_predictions=newest" class="px-4 py-2 rounded-lg <?php echo ($sort_predictions_by === 'newest' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100'); ?> font-semibold transition-colors duration-200">Newest</a>
                            <a href="story_detail.php?story_id=<?php echo htmlspecialchars($story_id); ?>&part_number=<?php echo htmlspecialchars($requested_part_number); ?>&sort_predictions=most_liked" class="px-4 py-2 rounded-lg <?php echo ($sort_predictions_by === 'most_liked' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100'); ?> font-semibold transition-colors duration-200">Most Liked</a>
                        </div>

                        <?php if (empty($predictions)): ?>
                            <p class="text-gray-600 text-center">No predictions yet for this part. Be the first to predict!</p>
                        <?php else: ?>
                            <?php foreach ($predictions as $prediction): ?>
                                <div class="bg-gray-50 rounded-lg p-4 mb-4 shadow-sm">
                                    <div class="flex items-center mb-3">
                                        <div class="w-10 h-10 bg-blue-200 text-blue-800 font-bold flex items-center justify-center rounded-full mr-3">
                                            <?php echo htmlspecialchars(substr($prediction['first_name'], 0, 1) . substr($prediction['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($prediction['first_name'] . ' ' . $prediction['last_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($prediction['created_at']))); ?></p>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 mb-3"><?php echo nl2br(htmlspecialchars($prediction['prediction_text'])); ?></p>
                                    <div class="flex items-center text-gray-500 text-sm">
                                        <?php if ($dynamic_status === 'active'): ?>
                                            <button class="like-button flex items-center mr-2 focus:outline-none"
                                                    data-prediction-id="<?php echo htmlspecialchars($prediction['prediction_id']); ?>"
                                                    data-user-id="<?php echo htmlspecialchars($current_user_id); ?>"
                                                    data-liked="<?php echo htmlspecialchars($prediction['liked_by_current_user']); ?>">
                                                <svg class="w-4 h-4 mr-1 <?php echo ($prediction['liked_by_current_user'] ? 'text-red-500' : 'text-gray-400'); ?>" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg>
                                                <span class="like-count"><?php echo htmlspecialchars($prediction['like_count']); ?></span>
                                            </button>
                                        <?php else: /* Status is 'completed' */ ?>
                                            <span class="flex items-center mr-2 text-gray-400">
                                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg>
                                                <span class="like-count"><?php echo htmlspecialchars($prediction['like_count']); ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                <?php endif; /* End of if not coming_soon */ ?>
            </div>
        <?php endif; ?>
    </main>
    <script>
        // JavaScript for the countdown timer
        function updateCountdown() {
            const deadlineElement = document.getElementById('deadline-timer');
            if (!deadlineElement) return;

            // Get the deadline from the PHP variable, assuming it's in a format parsable by JavaScript Date
            const deadlineStr = "<?php echo htmlspecialchars($story_part['prediction_deadline'] ?? ''); ?>";
            if (!deadlineStr) {
                deadlineElement.innerHTML = "Prediction deadline not set.";
                return;
            }

            const deadline = new Date(deadlineStr).getTime();
            const now = new Date().getTime();
            const distance = deadline - now;

            if (distance < 0) {
                deadlineElement.innerHTML = "Prediction deadline passed.";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            deadlineElement.innerHTML = `Predictions close in: ${days}d ${hours}h ${minutes}m ${seconds}s`;

            setTimeout(updateCountdown, 1000); // Update every second
        }

        // JavaScript for Like/Unlike functionality
        document.addEventListener('DOMContentLoaded', () => {
            updateCountdown(); // Initialize countdown

            document.querySelectorAll('.like-button').forEach(button => {
                button.addEventListener('click', async function() {
                    const predictionId = this.dataset.predictionId;
                    const userId = this.dataset.userId; // Current logged-in user ID
                    let liked = this.dataset.liked === '1'; // Convert string to boolean
                    const likeCountSpan = this.querySelector('.like-count');
                    const heartIcon = this.querySelector('svg');

                    const action = liked ? 'unlike' : 'like';

                    try {
                        const response = await fetch('handle_like.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `prediction_id=${predictionId}&user_id=${userId}&action=${action}`
                        });

                        const data = await response.json();

                        if (data.success) {
                            likeCountSpan.textContent = data.new_like_count;
                            if (action === 'like') {
                                heartIcon.classList.remove('text-gray-400');
                                heartIcon.classList.add('text-red-500');
                                this.dataset.liked = '1';
                            } else {
                                heartIcon.classList.remove('text-red-500');
                                heartIcon.classList.add('text-gray-400');
                                this.dataset.liked = '0';
                            }
                        } else {
                            console.error('Failed to update like:', data.message);
                            // Optionally show a user-friendly error message
                        }
                    } catch (error) {
                        console.error('Error sending like request:', error);
                        // Optionally show a user-friendly error message
                    }
                });
            });
        });
    </script>
</body>
</html>
