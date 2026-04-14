<?php
session_start();
require_once 'db_connect.php';

// Ensure only authors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Initialize variables
$total_followers = 0;
$total_likes = 0;
$followers_list = [];
$story_likes_data = [];
$sentiment_data = [];
$total_comments = 0;

try {
    // Get total followers count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as follower_count
        FROM follows f
        JOIN users u ON f.author_user_id = u.user_id
        WHERE u.user_name = ?
    ");
    $stmt->execute([$user_name]);
    $total_followers = $stmt->fetchColumn();
    
    // Get total story likes count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_likes
        FROM story_likes sl
        JOIN stories s ON sl.story_id = s.story_id
        WHERE s.created_by = ?
    ");
    $stmt->execute([$user_name]);
    $total_likes = $stmt->fetchColumn();
    
    // Get total comments count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_comments
        FROM comments c
        JOIN stories s ON c.story_id = s.story_id
        WHERE s.created_by = ?
    ");
    $stmt->execute([$user_name]);
    $total_comments = $stmt->fetchColumn();
    
    // Get detailed followers list
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_picture,
            u.total_score,
            f.followed_at
        FROM follows f
        JOIN users author ON f.author_user_id = author.user_id
        JOIN users u ON f.follower_user_id = u.user_id
        WHERE author.user_name = ?
        ORDER BY f.followed_at DESC
    ");
    $stmt->execute([$user_name]);
    $followers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get story likes data for chart (by story part, ordered by release date)
    $stmt = $pdo->prepare("
        SELECT 
            s.title,
            sp.part_number,
            sp.upload_date,
            COUNT(sl.story_like_id) as like_count,
            CONCAT(s.title, ' - Part ', sp.part_number) as chart_label
        FROM story_parts sp
        JOIN stories s ON sp.story_id = s.story_id
        LEFT JOIN story_likes sl ON s.story_id = sl.story_id
        WHERE s.created_by = ?
        GROUP BY s.story_id, sp.part_id, s.title, sp.part_number, sp.upload_date
        ORDER BY sp.upload_date ASC
    ");
    $stmt->execute([$user_name]);
    $story_likes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sentiment analytics data for story parts
    $stmt = $pdo->prepare("
        SELECT 
            s.story_id,
            s.title,
            sp.part_id,
            sp.part_number,
            sp.upload_date,
            COUNT(c.comment_id) as total_comments,
            SUM(CASE WHEN c.sentiment = 'positive' THEN 1 ELSE 0 END) as positive_count,
            SUM(CASE WHEN c.sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral_count,
            SUM(CASE WHEN c.sentiment = 'negative' THEN 1 ELSE 0 END) as negative_count,
            AVG(CASE WHEN c.sentiment = 'positive' THEN c.sentiment_score ELSE 0 END) as avg_positive_score,
            AVG(CASE WHEN c.sentiment = 'neutral' THEN c.sentiment_score ELSE 0 END) as avg_neutral_score,
            AVG(CASE WHEN c.sentiment = 'negative' THEN c.sentiment_score ELSE 0 END) as avg_negative_score,
            AVG(c.sentiment_score) as overall_confidence
        FROM story_parts sp
        JOIN stories s ON sp.story_id = s.story_id
        LEFT JOIN comments c ON c.story_id = s.story_id AND c.part_number = sp.part_number
        WHERE s.created_by = ?
        GROUP BY s.story_id, sp.part_id, s.title, sp.part_number, sp.upload_date
        HAVING COUNT(c.comment_id) > 0
        ORDER BY sp.upload_date DESC
    ");
    $stmt->execute([$user_name]);
    $sentiment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching analytics data: " . $e->getMessage());
}

// Prepare data for Chart.js
$chart_labels = [];
$chart_data = [];
foreach ($story_likes_data as $data) {
    $chart_labels[] = $data['chart_label'];
    $chart_data[] = $data['like_count'];
}

// Calculate overall sentiment statistics
$overall_positive = 0;
$overall_neutral = 0;
$overall_negative = 0;
foreach ($sentiment_data as $data) {
    $overall_positive += $data['positive_count'];
    $overall_neutral += $data['neutral_count'];
    $overall_negative += $data['negative_count'];
}

// Helper function
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    return date('M d, Y', $timestamp);
}

// Function to get sentiment color
function getSentimentColor($sentiment) {
    switch($sentiment) {
        case 'positive': return 'text-green-600';
        case 'negative': return 'text-red-600';
        case 'neutral': return 'text-gray-600';
        default: return 'text-gray-600';
    }
}

// Function to get sentiment background color
function getSentimentBgColor($sentiment) {
    switch($sentiment) {
        case 'positive': return 'bg-green-100';
        case 'negative': return 'bg-red-100';
        case 'neutral': return 'bg-gray-100';
        default: return 'bg-gray-100';
    }
}

// Function to get sentiment icon
function getSentimentIcon($sentiment) {
    switch($sentiment) {
        case 'positive': return 'fa-smile';
        case 'negative': return 'fa-frown';
        case 'neutral': return 'fa-meh';
        default: return 'fa-meh';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Dashboard - Story Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .sentiment-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sentiment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
        }
        .comment-item {
            transition: all 0.2s ease;
        }
        .comment-item:hover {
            background-color: rgba(249, 250, 251, 0.8);
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%);
            backdrop-filter: blur(10px);
        }
        .progress-bar {
            transition: width 1s ease-in-out;
        }
        .comments-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
        }
        .comments-section.expanded {
            max-height: 2000px;
        }
        .rotate-icon {
            transition: transform 0.3s ease;
        }
        .rotate-icon.rotated {
            transform: rotate(180deg);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-purple-50 to-pink-50 min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg sticky top-0 z-50" style="height:64px">
        <div class="max-w-full px-4 sm:px-6 lg:px-8 h-full">
            <div class="flex justify-between h-full">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-book-open text-3xl text-purple-600"></i>
                        <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-6">
                        <a href="index.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-home mr-2"></i> Home
                        </a>
                        <a href="add_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-plus-circle mr-2"></i> Add Story
                        </a>
                        <a href="manage_story.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-tasks mr-2"></i> Manage Stories
                        </a>
                        <a href="bonus_supply.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-clipboard-check mr-2"></i> Accuracy Review
                        </a>
                        <a href="dashboard.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-chart-line mr-2"></i> Dashboard
                        </a>
                        <a href="author_chat.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-comments mr-2"></i> Chat
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="logout.php" class="ml-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>


    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8 fade-in">
            <h1 class="text-4xl font-extrabold text-gray-900 flex items-center">
                <i class="fas fa-chart-line mr-3 text-purple-600"></i>
                Author Dashboard
            </h1>
            <p class="mt-2 text-lg text-gray-600">Comprehensive analytics for your stories, audience engagement, and sentiment insights</p>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 fade-in">
            <!-- Followers Card -->
            <div class="stat-card rounded-xl shadow-lg p-6 border border-purple-100 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-purple-100">
                        <i class="fas fa-users text-2xl text-purple-600"></i>
                    </div>
                    <span class="text-sm font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">
                        Total
                    </span>
                </div>
                <h3 class="text-gray-600 text-sm font-medium mb-1">Followers</h3>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_followers); ?></p>
                <p class="text-xs text-gray-500 mt-2">People following your work</p>
            </div>

            <!-- Likes Card -->
            <div class="stat-card rounded-xl shadow-lg p-6 border border-pink-100 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-pink-100">
                        <i class="fas fa-heart text-2xl text-pink-600"></i>
                    </div>
                    <span class="text-sm font-semibold text-pink-600 bg-pink-50 px-3 py-1 rounded-full">
                        Total
                    </span>
                </div>
                <h3 class="text-gray-600 text-sm font-medium mb-1">Story Likes</h3>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_likes); ?></p>
                <p class="text-xs text-gray-500 mt-2">Across all story parts</p>
            </div>

            <!-- Comments Card -->
            <div class="stat-card rounded-xl shadow-lg p-6 border border-blue-100 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-blue-100">
                        <i class="fas fa-comments text-2xl text-blue-600"></i>
                    </div>
                    <span class="text-sm font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                        Total
                    </span>
                </div>
                <h3 class="text-gray-600 text-sm font-medium mb-1">Comments</h3>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_comments); ?></p>
                <p class="text-xs text-gray-500 mt-2">Reader feedback received</p>
            </div>

            <!-- Engagement Score Card -->
            <div class="stat-card rounded-xl shadow-lg p-6 border border-green-100 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 rounded-lg bg-green-100">
                        <i class="fas fa-chart-bar text-2xl text-green-600"></i>
                    </div>
                    <span class="text-sm font-semibold text-green-600 bg-green-50 px-3 py-1 rounded-full">
                        Score
                    </span>
                </div>
                <h3 class="text-gray-600 text-sm font-medium mb-1">Engagement</h3>
                <p class="text-3xl font-bold text-gray-900"><?php echo $total_comments > 0 ? number_format(($total_likes + $total_comments * 2) / max(count($story_likes_data), 1), 1) : '0'; ?></p>
                <p class="text-xs text-gray-500 mt-2">Average per story part</p>
            </div>
        </div>

        <!-- Sentiment Analytics Section -->
        <?php if (!empty($sentiment_data)): ?>
        <div class="bg-white rounded-xl shadow-xl p-8 mb-8 border border-gray-200 fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-brain mr-3 text-purple-600"></i>
                        Sentiment Analytics
                    </h2>
                    <p class="text-gray-600 mt-2">AI-powered analysis of reader comments and emotional responses</p>
                </div>
                <button onclick="toggleAllSentiment()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium">
                    <i class="fas fa-expand-alt mr-2"></i>
                    <span id="toggleAllText">See All</span>
                </button>
            </div>

            <!-- Overall Sentiment Distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Pie Chart -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-chart-pie mr-2 text-purple-600"></i>
                        Overall Sentiment Distribution
                    </h3>
                    <div class="flex justify-center items-center" style="height: 300px;">
                        <canvas id="sentimentPieChart"></canvas>
                    </div>
                </div>

                <!-- Sentiment Breakdown Stats -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-list-check mr-2 text-purple-600"></i>
                        Sentiment Breakdown
                    </h3>
                    
                    <!-- Positive -->
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg p-4 border border-green-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <i class="fas fa-smile text-2xl text-green-600 mr-3"></i>
                                <span class="font-semibold text-gray-900">Positive Comments</span>
                            </div>
                            <span class="text-2xl font-bold text-green-600"><?php echo $overall_positive; ?></span>
                        </div>
                        <div class="w-full bg-green-200 rounded-full h-3 overflow-hidden">
                            <div class="progress-bar bg-green-600 h-3 rounded-full" style="width: <?php echo $total_comments > 0 ? ($overall_positive / $total_comments * 100) : 0; ?>%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">
                            <?php echo $total_comments > 0 ? number_format($overall_positive / $total_comments * 100, 1) : 0; ?>% of total comments
                        </p>
                    </div>

                    <!-- Neutral -->
                    <div class="bg-gradient-to-r from-gray-50 to-slate-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <i class="fas fa-meh text-2xl text-gray-600 mr-3"></i>
                                <span class="font-semibold text-gray-900">Neutral Comments</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-600"><?php echo $overall_neutral; ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="progress-bar bg-gray-600 h-3 rounded-full" style="width: <?php echo $total_comments > 0 ? ($overall_neutral / $total_comments * 100) : 0; ?>%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">
                            <?php echo $total_comments > 0 ? number_format($overall_neutral / $total_comments * 100, 1) : 0; ?>% of total comments
                        </p>
                    </div>

                    <!-- Negative -->
                    <div class="bg-gradient-to-r from-red-50 to-rose-50 rounded-lg p-4 border border-red-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <i class="fas fa-frown text-2xl text-red-600 mr-3"></i>
                                <span class="font-semibold text-gray-900">Negative Comments</span>
                            </div>
                            <span class="text-2xl font-bold text-red-600"><?php echo $overall_negative; ?></span>
                        </div>
                        <div class="w-full bg-red-200 rounded-full h-3 overflow-hidden">
                            <div class="progress-bar bg-red-600 h-3 rounded-full" style="width: <?php echo $total_comments > 0 ? ($overall_negative / $total_comments * 100) : 0; ?>%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">
                            <?php echo $total_comments > 0 ? number_format($overall_negative / $total_comments * 100, 1) : 0; ?>% of total comments
                        </p>
                    </div>
                </div>
            </div>

            <!-- Story Parts Sentiment Details -->
            <div class="mt-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-book-reader mr-3 text-purple-600"></i>
                    Story Parts Performance
                    <span class="ml-3 text-sm font-normal text-gray-500">(Sorted by most recent)</span>
                </h3>

                <div id="sentimentStoryList" class="space-y-4">
                    <?php 
                    $display_limit = 5;
                    $story_count = 0;
                    foreach ($sentiment_data as $story): 
                        $story_count++;
                        $is_hidden = $story_count > $display_limit;
                        $positive_percent = $story['total_comments'] > 0 ? ($story['positive_count'] / $story['total_comments'] * 100) : 0;
                        $neutral_percent = $story['total_comments'] > 0 ? ($story['neutral_count'] / $story['total_comments'] * 100) : 0;
                        $negative_percent = $story['total_comments'] > 0 ? ($story['negative_count'] / $story['total_comments'] * 100) : 0;
                    ?>
                    <div class="sentiment-card bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden <?php echo $is_hidden ? 'hidden expandable-story' : ''; ?>">
                        <!-- Story Header -->
                        <div class="bg-gradient-to-r from-purple-500 to-pink-500 p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="text-white font-bold text-lg flex items-center">
                                        <i class="fas fa-book mr-2"></i>
                                        <?php echo htmlspecialchars($story['title']); ?> - Part <?php echo $story['part_number']; ?>
                                    </h4>
                                    <p class="text-purple-100 text-sm mt-1">
                                        <i class="far fa-calendar mr-1"></i>
                                        Released: <?php echo date('M d, Y', strtotime($story['upload_date'])); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                                        <p class="text-white text-sm font-medium">Total Comments</p>
                                        <p class="text-white text-2xl font-bold"><?php echo $story['total_comments']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sentiment Stats -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <!-- Positive -->
                                <div class="bg-green-50 rounded-lg p-4 border-2 border-green-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="fas fa-smile text-xl text-green-600"></i>
                                        <span class="text-2xl font-bold text-green-600"><?php echo $story['positive_count']; ?></span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-700">Positive</p>
                                    <p class="text-xs text-gray-600"><?php echo number_format($positive_percent, 1); ?>% • Confidence: <?php echo number_format($story['avg_positive_score'] * 100, 1); ?>%</p>
                                    <div class="w-full bg-green-200 rounded-full h-2 mt-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $positive_percent; ?>%"></div>
                                    </div>
                                </div>

                                <!-- Neutral -->
                                <div class="bg-gray-50 rounded-lg p-4 border-2 border-gray-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="fas fa-meh text-xl text-gray-600"></i>
                                        <span class="text-2xl font-bold text-gray-600"><?php echo $story['neutral_count']; ?></span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-700">Neutral</p>
                                    <p class="text-xs text-gray-600"><?php echo number_format($neutral_percent, 1); ?>% • Confidence: <?php echo number_format($story['avg_neutral_score'] * 100, 1); ?>%</p>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                        <div class="bg-gray-600 h-2 rounded-full" style="width: <?php echo $neutral_percent; ?>%"></div>
                                    </div>
                                </div>

                                <!-- Negative -->
                                <div class="bg-red-50 rounded-lg p-4 border-2 border-red-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="fas fa-frown text-xl text-red-600"></i>
                                        <span class="text-2xl font-bold text-red-600"><?php echo $story['negative_count']; ?></span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-700">Negative</p>
                                    <p class="text-xs text-gray-600"><?php echo number_format($negative_percent, 1); ?>% • Confidence: <?php echo number_format($story['avg_negative_score'] * 100, 1); ?>%</p>
                                    <div class="w-full bg-red-200 rounded-full h-2 mt-2">
                                        <div class="bg-red-600 h-2 rounded-full" style="width: <?php echo $negative_percent; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- See Comments Button -->
                            <button onclick="toggleComments(<?php echo $story['story_id']; ?>, <?php echo $story['part_number']; ?>)" 
                                    class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fas fa-comments mr-2"></i>
                                See Comments
                                <i class="fas fa-chevron-down ml-2 rotate-icon" id="icon-<?php echo $story['story_id']; ?>-<?php echo $story['part_number']; ?>"></i>
                            </button>

                            <!-- Comments Section (Hidden by default) -->
                            <div id="comments-<?php echo $story['story_id']; ?>-<?php echo $story['part_number']; ?>" class="comments-section mt-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h5 class="font-bold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-comment-dots mr-2 text-purple-600"></i>
                                        Reader Comments
                                    </h5>
                                    <div id="comments-content-<?php echo $story['story_id']; ?>-<?php echo $story['part_number']; ?>" class="space-y-3">
                                        <!-- Comments will be loaded here via JavaScript -->
                                        <div class="text-center py-4">
                                            <i class="fas fa-spinner fa-spin text-2xl text-purple-600"></i>
                                            <p class="text-gray-600 mt-2">Loading comments...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Story Likes Chart -->
        <?php if (!empty($story_likes_data)): ?>
        <div class="bg-white rounded-xl shadow-xl p-8 mb-8 border border-gray-200 fade-in">
            <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-heart mr-3 text-pink-600"></i>
                Story Likes Analytics
            </h2>
            <div class="bg-gradient-to-br from-pink-50 to-purple-50 rounded-xl p-6 border border-pink-200">
                <canvas id="likesChart" style="max-height: 400px;"></canvas>
            </div>
        </div>

        <!-- Detailed Likes Table -->
        <div class="bg-white rounded-xl shadow-xl overflow-hidden mb-8 border border-gray-200">
            <div class="bg-gradient-to-r from-pink-500 to-purple-500 px-6 py-4">
                <h2 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-list mr-3"></i>
                    Detailed Story Likes
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Story Title</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Part</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Release Date</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Likes</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $max_likes = max(array_column($story_likes_data, 'like_count'));
                        foreach ($story_likes_data as $data): 
                            $like_percentage = $max_likes > 0 ? ($data['like_count'] / $max_likes * 100) : 0;
                            $bar_color = $like_percentage >= 75 ? 'bg-green-500' : ($like_percentage >= 50 ? 'bg-blue-500' : ($like_percentage >= 25 ? 'bg-yellow-500' : 'bg-red-500'));
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fas fa-book text-purple-600 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($data['title']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                    Part <?php echo $data['part_number']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo date('M d, Y', strtotime($data['upload_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fas fa-heart text-pink-500 mr-2"></i>
                                    <span class="text-sm font-bold text-gray-900"><?php echo number_format($data['like_count']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?php echo $bar_color; ?> h-2.5 rounded-full" 
                                         style="width: <?php echo $like_percentage; ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500 mt-1">
                                    <?php echo number_format($like_percentage, 1); ?>% of top
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-sm font-bold text-gray-900 text-right">
                                Total:
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fas fa-heart text-pink-500 mr-2"></i>
                                    <span class="text-sm font-bold text-gray-900">
                                        <?php echo number_format($total_likes); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Insights Section -->
        <?php if (!empty($story_likes_data)): 
            $avg_likes = $total_likes / count($story_likes_data);
            $most_liked = array_reduce($story_likes_data, function($carry, $item) {
                return (!$carry || $item['like_count'] > $carry['like_count']) ? $item : $carry;
            });
            $least_liked = array_reduce($story_likes_data, function($carry, $item) {
                return (!$carry || $item['like_count'] < $carry['like_count']) ? $item : $carry;
            });
        ?>
        <div class="bg-white rounded-xl shadow-xl p-8 mb-8 border border-gray-200 fade-in">
            <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-lightbulb mr-3 text-yellow-600"></i>
                Key Insights
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-6 rounded-xl border-2 border-green-200 card-hover">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-trophy text-3xl text-green-600 mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Most Liked</h3>
                    </div>
                    <p class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($most_liked['title'] ?? 'N/A'); ?> - Part <?php echo $most_liked['part_number'] ?? 'N/A'; ?></p>
                    <p class="text-3xl font-bold text-green-600 mt-2">
                        <?php echo number_format($most_liked['like_count'] ?? 0); ?> likes
                    </p>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-xl border-2 border-blue-200 card-hover">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-chart-bar text-3xl text-blue-600 mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Average Likes</h3>
                    </div>
                    <p class="text-sm text-gray-700">Per story part</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2">
                        <?php echo number_format($avg_likes, 1); ?> likes
                    </p>
                </div>

                <div class="bg-gradient-to-br from-orange-50 to-red-50 p-6 rounded-xl border-2 border-orange-200 card-hover">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-arrow-up text-3xl text-orange-600 mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Needs Boost</h3>
                    </div>
                    <p class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($least_liked['title'] ?? 'N/A'); ?> - Part <?php echo $least_liked['part_number'] ?? 'N/A'; ?></p>
                    <p class="text-3xl font-bold text-orange-600 mt-2">
                        <?php echo number_format($least_liked['like_count'] ?? 0); ?> likes
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Followers Section -->
        <div class="bg-white rounded-xl shadow-xl overflow-hidden border border-gray-200 fade-in">
            <div class="bg-gradient-to-r from-purple-500 to-indigo-500 px-6 py-4">
                <h2 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-users mr-3"></i>
                    Your Followers
                </h2>
            </div>
            <?php if (empty($followers_list)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-user-plus text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No followers yet. Keep creating amazing content!</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Follower</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Score</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Followed</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($followers_list as $follower): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <?php if ($follower['profile_picture']): ?>
                                            <img class="h-10 w-10 rounded-full object-cover border-2 border-purple-200" 
                                                 src="<?php echo htmlspecialchars($follower['profile_picture']); ?>" 
                                                 alt="Profile">
                                        <?php else: ?>
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center">
                                                <span class="text-white font-bold text-lg">
                                                    <?php echo strtoupper(substr($follower['first_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($follower['first_name'] . ' ' . $follower['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            @<?php echo htmlspecialchars($follower['user_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($follower['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-star mr-1"></i>
                                    <?php echo number_format($follower['total_score']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <i class="far fa-clock mr-1"></i>
                                <?php echo timeAgo($follower['followed_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sentiment Pie Chart
        <?php if (!empty($sentiment_data) && $total_comments > 0): ?>
        const sentimentCtx = document.getElementById('sentimentPieChart').getContext('2d');
        const sentimentPieChart = new Chart(sentimentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Positive', 'Neutral', 'Negative'],
                datasets: [{
                    data: [<?php echo $overall_positive; ?>, <?php echo $overall_neutral; ?>, <?php echo $overall_negative; ?>],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(156, 163, 175, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(156, 163, 175, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = <?php echo $total_comments; ?>;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Likes Chart
        <?php if (!empty($story_likes_data)): ?>
        const ctx = document.getElementById('likesChart').getContext('2d');
        const likesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Number of Likes',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(236, 72, 153, 0.8)',
                    borderColor: 'rgba(236, 72, 153, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBackgroundColor: 'rgba(236, 72, 153, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Story Likes by Release Order',
                        font: {
                            size: 18,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Likes: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12
                            }
                        },
                        title: {
                            display: true,
                            text: 'Number of Likes',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11
                            },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        title: {
                            display: true,
                            text: 'Stories (By Release Date)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Toggle comments visibility
        function toggleComments(storyId, partNumber) {
            const commentsDiv = document.getElementById(`comments-${storyId}-${partNumber}`);
            const contentDiv = document.getElementById(`comments-content-${storyId}-${partNumber}`);
            const icon = document.getElementById(`icon-${storyId}-${partNumber}`);
            
            if (commentsDiv.classList.contains('expanded')) {
                commentsDiv.classList.remove('expanded');
                icon.classList.remove('rotated');
            } else {
                commentsDiv.classList.add('expanded');
                icon.classList.add('rotated');
                
                // Load comments if not already loaded
                if (contentDiv.innerHTML.includes('Loading comments')) {
                    loadComments(storyId, partNumber);
                }
            }
        }

        // Load comments via AJAX
        function loadComments(storyId, partNumber) {
            const contentDiv = document.getElementById(`comments-content-${storyId}-${partNumber}`);
            
            fetch(`get_comments.php?story_id=${storyId}&part_number=${partNumber}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.comments.length > 0) {
                        let html = '';
                        data.comments.forEach(comment => {
                            const sentimentIcon = getSentimentIcon(comment.sentiment);
                            const sentimentColor = getSentimentColor(comment.sentiment);
                            const sentimentBg = getSentimentBgColor(comment.sentiment);
                            
                            html += `
                                <div class="comment-item bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center">
                                                <span class="text-white font-bold text-sm">${comment.user_name.charAt(0).toUpperCase()}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-semibold text-gray-900">${comment.user_name}</p>
                                                <p class="text-xs text-gray-500">${comment.created_at}</p>
                                            </div>
                                        </div>
                                        <div class="${sentimentBg} px-3 py-1 rounded-full flex items-center">
                                            <i class="fas ${sentimentIcon} ${sentimentColor} mr-1"></i>
                                            <span class="text-xs font-semibold ${sentimentColor}">${comment.sentiment}</span>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 text-sm mb-2">${comment.comment_text}</p>
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span><i class="fas fa-chart-line mr-1"></i>Confidence: ${(comment.sentiment_score * 100).toFixed(1)}%</span>
                                    </div>
                                </div>
                            `;
                        });
                        contentDiv.innerHTML = html;
                    } else {
                        contentDiv.innerHTML = '<p class="text-center text-gray-500 py-4">No comments yet for this story part.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    contentDiv.innerHTML = '<p class="text-center text-red-500 py-4">Error loading comments. Please try again.</p>';
                });
        }

        // Helper functions for sentiment display
        function getSentimentIcon(sentiment) {
            switch(sentiment.toLowerCase()) {
                case 'positive': return 'fa-smile';
                case 'negative': return 'fa-frown';
                default: return 'fa-meh';
            }
        }

        function getSentimentColor(sentiment) {
            switch(sentiment.toLowerCase()) {
                case 'positive': return 'text-green-600';
                case 'negative': return 'text-red-600';
                default: return 'text-gray-600';
            }
        }

        function getSentimentBgColor(sentiment) {
            switch(sentiment.toLowerCase()) {
                case 'positive': return 'bg-green-100';
                case 'negative': return 'bg-red-100';
                default: return 'bg-gray-100';
            }
        }

        // Toggle all sentiment stories
        let allExpanded = false;
        function toggleAllSentiment() {
            const expandableStories = document.querySelectorAll('.expandable-story');
            const toggleText = document.getElementById('toggleAllText');
            
            allExpanded = !allExpanded;
            
            expandableStories.forEach(story => {
                if (allExpanded) {
                    story.classList.remove('hidden');
                } else {
                    story.classList.add('hidden');
                }
            });
            
            toggleText.textContent = allExpanded ? 'Show Less' : 'See All';
        }
    </script>
</body>
</html>
