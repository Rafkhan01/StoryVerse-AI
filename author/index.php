<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is an author
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: ../signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
// Fetch author statistics
try {
    // Get author basic info and stats
    $stmt = $pdo->prepare("
        SELECT 
            user_id,
            user_name,
            first_name,
            last_name,
            email,
            profile_picture,
            bio,
            total_stories,
            total_views,
            follower_count,
            following_count,
            total_likes,
            total_score
        FROM users 
        WHERE user_id = ? AND user_type = 'author'
    ");
    $stmt->execute([$user_id]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$author) {
        header('Location: ../signin.php');
        exit();
    }

    // Get recent stories
    $stmt = $pdo->prepare("
        SELECT 
            s.story_id,
            s.title,
            s.category,
            s.cover_image_url,
            s.current_part_no,
            s.total_parts,
            s.view_count,
            s.like_count,
            s.prediction_count,
            s.is_completed,
            s.created_at,
            s.last_updated,
            COUNT(DISTINCT c.comment_id) as comment_count
        FROM stories s
        LEFT JOIN comments c ON s.story_id = c.story_id
        WHERE s.created_by = ?
        GROUP BY s.story_id
        ORDER BY s.last_updated DESC
        LIMIT 5
    ");
    $stmt->execute([$user_name]);
    $recent_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent notifications
    $stmt = $pdo->prepare("
        SELECT 
            n.notification_id,
            n.notification_type,
            n.message,
            n.is_read,
            n.created_at,
            u.user_name,
            u.first_name,
            u.last_name,
            u.profile_picture
        FROM notifications n
        LEFT JOIN users u ON n.related_user_id = u.user_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread notification count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();

    // Get recent followers
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.user_name,
            u.first_name,
            u.last_name,
            u.profile_picture,
            f.followed_at
        FROM follows f
        JOIN users u ON f.follower_user_id = u.user_id
        WHERE f.author_user_id = ?
        ORDER BY f.followed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $stmt->execute();
    $total_authors = $stmt->fetchColumn();

    // Calculate engagement metrics
    $total_predictions = 0;
    $avg_accuracy = 0;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(p.prediction_id) as total_predictions,
            AVG(CASE WHEN p.manual_accuracy IS NOT NULL THEN p.manual_accuracy ELSE 0 END) as avg_accuracy
        FROM predictions p
        JOIN stories s ON p.story_id = s.story_id
        WHERE s.created_by = ?
    ");
    $stmt->execute([$user_name]);
    $engagement = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_predictions = $engagement['total_predictions'] ?? 0;
    $avg_accuracy = round($engagement['avg_accuracy'] ?? 0, 1);

} catch (PDOException $e) {
    error_log("Error fetching author data: " . $e->getMessage());
    $author = null;
}

// Helper function to format numbers
function formatNumber($num) {
    if ($num >= 1000000) {
        return number_format($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return number_format($num / 1000, 1) . 'K';
    }
    return number_format($num);
}

// Helper function to get time ago
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

// Get profile picture URL
$profile_pic = $author['profile_picture'] ?? 
    "https://ui-avatars.com/api/?name=" . urlencode($author['first_name'] . ' ' . $author['last_name']) . "&background=667eea&color=fff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Dashboard - Story Pulse</title>
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
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .notification-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50">
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
                        <a href="index.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
                        <a href="dashboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-chart-line mr-2"></i> Dashboard
                        </a>
                        <a href="author_chat.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Welcome back, <span class="gradient-bg bg-clip-text text-transparent"><?php echo htmlspecialchars($author['first_name']); ?></span>! 👋</h1>
            <p class="mt-2 text-gray-600">Here's what's happening with your stories today.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Stories -->
            <div class="stat-card rounded-xl shadow-lg p-6 text-white card-hover cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">Total Stories</p>
                        <p class="text-3xl font-bold mt-2"><?php echo $author['total_stories']; ?></p>
                        <p class="text-sm mt-2 text-white text-opacity-70">
                            <i class="fas fa-arrow-up"></i> <span>Active</span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-4">
                        <i class="fas fa-book text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Views -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white card-hover cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">Total Views</p>
                        <p class="text-3xl font-bold mt-2"><?php echo formatNumber($author['total_views']); ?></p>
                        <p class="text-sm mt-2 text-white text-opacity-70">
                            <i class="fas fa-eye"></i> <span>All time</span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-4">
                        <i class="fas fa-eye text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Followers -->
            <div class="bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl shadow-lg p-6 text-white card-hover cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">Followers</p>
                        <p class="text-3xl font-bold mt-2"><?php echo formatNumber($author['follower_count']); ?></p>
                        <p class="text-sm mt-2 text-white text-opacity-70">
                            <i class="fas fa-users"></i> <span>Growing</span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-4">
                        <i class="fas fa-users text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Likes -->
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg p-6 text-white card-hover cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">Total Likes</p>
                        <p class="text-3xl font-bold mt-2"><?php echo formatNumber($author['total_likes']); ?></p>
                        <p class="text-sm mt-2 text-white text-opacity-70">
                            <i class="fas fa-heart"></i> <span>All stories</span>
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-4">
                        <i class="fas fa-heart text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column (2/3) -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button onclick="window.location.href='add_story.php'" class="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition">
                            <i class="fas fa-plus-circle text-3xl text-purple-600 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">New Story</span>
                        </button>
                        <button onclick="window.location.href='manage_story.php'" class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                            <i class="fas fa-edit text-3xl text-blue-600 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">Edit Stories</span>
                        </button>
                        <button onclick="window.location.href='follows_likes.php'" class="flex flex-col items-center p-4 bg-pink-50 hover:bg-pink-100 rounded-lg transition">
                            <i class="fas fa-chart-line text-3xl text-pink-600 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">Analytics</span>
                        </button>
                        <button onclick="window.location.href='scoreboard.php'" class="flex flex-col items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition">
                            <i class="fas fa-trophy text-3xl text-yellow-600 mb-2"></i>
                            <span class="text-sm font-medium text-gray-700">Rankings</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Stories -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-900">Your Recent Stories</h2>
                        <a href="manage_story.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium">View All →</a>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($recent_stories)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-book-open text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">No stories yet. Create your first story!</p>
                                <button onclick="window.location.href='add_story.php'" class="mt-4 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                    Create Story
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_stories as $story): 
                                $cover_image = $story['cover_image_url'] ?? 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop';
                                $status = $story['is_completed'] ? 'Completed' : 'Active';
                                $status_class = $story['is_completed'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                            ?>
                            <div class="flex items-center space-x-4 p-4 hover:bg-gray-50 rounded-lg transition cursor-pointer">
                                <img src="<?php echo htmlspecialchars($cover_image); ?>" alt="Story" class="w-16 h-16 rounded-lg object-cover">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($story['title']); ?></h3>
                                    <p class="text-sm text-gray-500">Part <?php echo $story['current_part_no']; ?> of <?php echo $story['total_parts']; ?> • Updated <?php echo timeAgo($story['last_updated']); ?></p>
                                    <div class="flex items-center space-x-4 mt-1">
                                        <span class="text-xs text-gray-500"><i class="fas fa-eye mr-1"></i> <?php echo formatNumber($story['view_count']); ?></span>
                                        <span class="text-xs text-gray-500"><i class="fas fa-heart mr-1"></i> <?php echo formatNumber($story['like_count']); ?></span>
                                        <span class="text-xs text-gray-500"><i class="fas fa-lightbulb mr-1"></i> <?php echo formatNumber($story['prediction_count']); ?></span>
                                    </div>
                                </div>
                                <span class="px-3 py-1 <?php echo $status_class; ?> text-xs font-medium rounded-full"><?php echo $status; ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reader Engagement -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Reader Engagement</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="bg-purple-100 rounded-full p-3">
                                    <i class="fas fa-lightbulb text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Predictions Made</p>
                                    <p class="text-sm text-gray-500">Readers trying to guess your next part</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-gray-900"><?php echo formatNumber($total_predictions); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="bg-green-100 rounded-full p-3">
                                    <i class="fas fa-bullseye text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Avg. Prediction Accuracy</p>
                                    <p class="text-sm text-gray-500">How well readers predict your stories</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-gray-900"><?php echo $avg_accuracy; ?>%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="bg-blue-100 rounded-full p-3">
                                    <i class="fas fa-star text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Total Score</p>
                                    <p class="text-sm text-gray-500">Your overall performance score</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-gray-900"><?php echo formatNumber($author['total_score']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column (1/3) -->
            <div class="space-y-8">
                <!-- Your Ranking -->
                <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">Your Ranking</h2>
                        <i class="fas fa-medal text-3xl"></i>
                    </div>
                    <div class="text-center py-4">
                        <div class="text-6xl font-bold mb-2">#<?php echo $ranking; ?></div>
                        <p class="text-white text-opacity-90">Out of <?php echo formatNumber($total_authors); ?> authors</p>
                        <div class="mt-4 pt-4 border-t border-white border-opacity-30">
                            <p class="text-sm">Total Score</p>
                            <p class="text-3xl font-bold mt-1"><?php echo formatNumber($author['total_score']); ?></p>
                        </div>
                    </div>
                    <button onclick="window.location.href='scoreboard.php'" class="w-full mt-4 bg-white text-orange-600 font-medium py-2 rounded-lg hover:bg-opacity-90 transition">
                        View Full Rankings
                    </button>
                </div>

                <!-- Recent Notifications -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-900">Notifications</h2>
                        <?php if ($unread_count > 0): ?>
                            <span class="bg-red-100 text-red-600 text-xs font-medium px-2 py-1 rounded-full"><?php echo $unread_count; ?> New</span>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($notifications)): ?>
                            <p class="text-center text-gray-500 py-4">No notifications yet</p>
                        <?php else: ?>
                            <?php foreach (array_slice($notifications, 0, 5) as $notif): 
                                $icon_class = [
                                    'follow' => 'bg-blue-100 text-blue-600 fa-user-plus',
                                    'like' => 'bg-pink-100 text-pink-600 fa-heart',
                                    'prediction' => 'bg-purple-100 text-purple-600 fa-lightbulb',
                                    'comment' => 'bg-green-100 text-green-600 fa-comment',
                                ];
                                $icon = $icon_class[$notif['notification_type']] ?? 'bg-gray-100 text-gray-600 fa-bell';
                                list($bg_class, $icon_color, $icon_name) = explode(' ', $icon);
                            ?>
                            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg transition cursor-pointer <?php echo $notif['is_read'] ? '' : 'bg-blue-50'; ?>">
                                <div class="<?php echo $bg_class; ?> rounded-full p-2 flex-shrink-0">
                                    <i class="fas <?php echo $icon_name; ?> <?php echo $icon_color; ?>"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo timeAgo($notif['created_at']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button class="w-full mt-4 text-purple-600 font-medium text-sm hover:text-purple-700">
                        View All Notifications
                    </button>
                </div>
                                
                <!-- Recent Followers -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Followers</h2>
                    <div class="space-y-3">
                        <?php if (empty($recent_followers)): ?>
                            <p class="text-center text-gray-500 py-4">No followers yet</p>
                        <?php else: ?>
                            <?php foreach ($recent_followers as $follower): 
                                $follower_pic = $follower['profile_picture'] ?? 
                                    "https://ui-avatars.com/api/?name=" . urlencode($follower['first_name'] . ' ' . $follower['last_name']) . "&background=667eea&color=fff";
                            ?>
                            <div class="flex items-center space-x-3">
                                <img src="<?php echo htmlspecialchars($follower_pic); ?>" alt="Follower" class="w-10 h-10 rounded-full">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($follower['first_name'] . ' ' . $follower['last_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo timeAgo($follower['followed_at']); ?></p>
                                </div>
                                <button class="text-purple-600 hover:text-purple-700 text-sm font-medium">Follow</button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button onclick="window.location.href='follows_likes.php'" class="w-full mt-4 text-purple-600 font-medium text-sm hover:text-purple-700">
                        View All Followers
                    </button>
                </div>
            </div>
        </div>
        <div class="lg:col-span-3 mt-8">
            <div class="bg-gradient-to-r from-amber-400 via-orange-500 to-red-500 rounded-xl shadow-lg p-8 text-white">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <i class="fas fa-coins text-4xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold">Bonus Supply</h2>
                            <p class="text-white text-opacity-90">Reward your readers for accurate predictions</p>
                        </div>
                    </div>
                    <button onclick="window.location.href='bonus_supply.php'" class="bg-white text-orange-600 px-6 py-3 rounded-lg font-semibold hover:bg-opacity-90 transition shadow-lg">
                        <i class="fas fa-gift mr-2"></i> Supply Bonus
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                    <!-- Total Bonus Supplied -->
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-white text-opacity-80 text-sm font-medium">Total Bonuses Supplied</p>
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <p class="text-3xl font-bold" id="totalBonusSupplied">
                            <?php 
                            // Fetch total bonus supplied by this author
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT COALESCE(SUM(bonus_amount), 0) as total_bonus
                                    FROM bonus b
                                    JOIN predictions p ON b.prediction_id = p.prediction_id
                                    JOIN stories s ON p.story_id = s.story_id
                                    WHERE s.created_by = ?
                                ");
                                $stmt->execute([$user_id]);
                                $total_bonus = $stmt->fetchColumn();
                                echo number_format($total_bonus, 2);
                            } catch (PDOException $e) {
                                echo "0.00";
                            }
                            ?>
                        </p>
                        <p class="text-sm text-white text-opacity-70 mt-1">All time</p>
                    </div>

                    <!-- Bonuses This Month -->
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-white text-opacity-80 text-sm font-medium">This Month</p>
                            <i class="fas fa-calendar-alt text-xl"></i>
                        </div>
                        <p class="text-3xl font-bold" id="monthlyBonus">
                            <?php 
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT COALESCE(SUM(bonus_amount), 0) as monthly_bonus
                                    FROM bonus b
                                    JOIN predictions p ON b.prediction_id = p.prediction_id
                                    JOIN stories s ON p.story_id = s.story_id
                                    WHERE s.created_by = ? 
                                    AND MONTH(b.awarded_at) = MONTH(CURRENT_DATE())
                                    AND YEAR(b.awarded_at) = YEAR(CURRENT_DATE())
                                ");
                                $stmt->execute([$user_id]);
                                $monthly_bonus = $stmt->fetchColumn();
                                echo number_format($monthly_bonus, 2);
                            } catch (PDOException $e) {
                                echo "0.00";
                            }
                            ?>
                        </p>
                        <p class="text-sm text-white text-opacity-70 mt-1">Current month</p>
                    </div>

                    <!-- Readers Rewarded -->
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-white text-opacity-80 text-sm font-medium">Readers Rewarded</p>
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <p class="text-3xl font-bold" id="readersRewarded">
                            <?php 
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(DISTINCT b.user_id) as readers_rewarded
                                    FROM bonus b
                                    JOIN predictions p ON b.prediction_id = p.prediction_id
                                    JOIN stories s ON p.story_id = s.story_id
                                    WHERE s.created_by = ?
                                ");
                                $stmt->execute([$user_id]);
                                $readers_rewarded = $stmt->fetchColumn();
                                echo number_format($readers_rewarded);
                            } catch (PDOException $e) {
                                echo "0";
                            }
                            ?>
                        </p>
                        <p class="text-sm text-white text-opacity-70 mt-1">Unique readers</p>
                    </div>
                </div>

                <!-- Recent Bonus Activity -->
                <div class="mt-6 bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-history mr-2"></i> Recent Bonus Activity
                    </h3>
                    <div class="space-y-3 max-h-48 overflow-y-auto">
                        <?php 
                        try {
                            $stmt = $pdo->prepare("
                                SELECT 
                                    b.bonus_amount,
                                    b.awarded_at,
                                    u.first_name,
                                    u.last_name,
                                    s.title,
                                    p.prediction_part_no
                                FROM bonus b
                                JOIN predictions p ON b.prediction_id = p.prediction_id
                                JOIN stories s ON p.story_id = s.story_id
                                JOIN users u ON b.user_id = u.user_id
                                WHERE s.created_by = ?
                                ORDER BY b.awarded_at DESC
                                LIMIT 5
                            ");
                            $stmt->execute([$user_id]);
                            $recent_bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($recent_bonuses)) {
                                echo '<p class="text-center text-white text-opacity-70 py-4">No bonuses supplied yet</p>';
                            } else {
                                foreach ($recent_bonuses as $bonus) {
                                    echo '<div class="flex items-center justify-between p-2 hover:bg-white hover:bg-opacity-5 rounded transition">';
                                    echo '<div class="flex-1">';
                                    echo '<p class="text-sm font-medium">' . htmlspecialchars($bonus['first_name'] . ' ' . $bonus['last_name']) . '</p>';
                                    echo '<p class="text-xs text-white text-opacity-70">' . htmlspecialchars($bonus['title']) . ' - Part ' . $bonus['prediction_part_no'] . '</p>';
                                    echo '</div>';
                                    echo '<div class="text-right">';
                                    echo '<p class="text-sm font-bold">₹' . number_format($bonus['bonus_amount'], 2) . '</p>';
                                    echo '<p class="text-xs text-white text-opacity-70">' . timeAgo($bonus['awarded_at']) . '</p>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                        } catch (PDOException $e) {
                            echo '<p class="text-center text-white text-opacity-70 py-4">Unable to load recent activity</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu Button -->
    <button onclick="toggleMobileMenu()" class="md:hidden fixed bottom-6 right-6 bg-purple-600 text-white p-4 rounded-full shadow-lg hover:bg-purple-700 transition z-50">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.fa-chevron-down') && !event.target.closest('button')) {
                const dropdown = document.getElementById('profileDropdown');
                if (!dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            }
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            // Simple mobile menu implementation
            const nav = document.querySelector('nav .hidden.md\\:flex');
            if (nav) {
                nav.classList.toggle('hidden');
                nav.classList.toggle('flex');
                nav.classList.toggle('flex-col');
                nav.classList.toggle('absolute');
                nav.classList.toggle('top-16');
                nav.classList.toggle('left-0');
                nav.classList.toggle('right-0');
                nav.classList.toggle('bg-white');
                nav.classList.toggle('shadow-lg');
                nav.classList.toggle('p-4');
            }
        }

        // Optional: Auto-refresh stats every 30 seconds
        setInterval(function() {
            // You can implement AJAX refresh here if needed
            // fetch('get_author_stats.php')
            //     .then(response => response.json())
            //     .then(data => {
            //         // Update UI with new data
            //         console.log('Stats refreshed:', data);
            //     })
            //     .catch(error => console.error('Error refreshing stats:', error));
        }, 30000);

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add active state to navigation
        const currentLocation = window.location.pathname;
        const navLinks = document.querySelectorAll('nav a');
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentLocation.split('/').pop()) {
                link.classList.add('border-purple-500', 'text-gray-900');
                link.classList.remove('border-transparent', 'text-gray-500');
            }
        });
    </script>
</body>
</html>