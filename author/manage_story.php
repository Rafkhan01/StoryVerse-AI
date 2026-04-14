<?php
session_start();
require_once 'db_connect.php';

// Ensure only authors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: ../signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Initialize variables
$stories = [];
$total_bonus_supplied = 0;
$monthly_bonus = 0;
$readers_rewarded = 0;

try {
    // Fetch ONLY stories created by this author
    $sql_stories = "SELECT story_id, title, created_by, category, cover_image_url, current_part_no, description, total_parts, view_count, like_count, is_completed 
                    FROM stories 
                    WHERE created_by = :user_name 
                    ORDER BY created_at DESC";
    $stmt_stories = $pdo->prepare($sql_stories);
    $stmt_stories->execute(['user_name' => $user_name]);
    $stories = $stmt_stories->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch parts for each story
    foreach ($stories as &$story) {
        $sql_parts = "
            SELECT
                sp.part_id,
                sp.part_number,
                sp.content,
                sp.upload_date,
                sp.prediction_deadline,
                COUNT(p.prediction_id) AS total_predictions
            FROM story_parts AS sp
            LEFT JOIN predictions AS p ON sp.story_id = p.story_id AND sp.part_number = p.prediction_part_no
            WHERE sp.story_id = :story_id
            GROUP BY sp.part_id, sp.part_number, sp.content, sp.upload_date, sp.prediction_deadline
            ORDER BY sp.part_number ASC
        ";
        $stmt_parts = $pdo->prepare($sql_parts);
        $stmt_parts->execute(['story_id' => $story['story_id']]);
        $story['parts'] = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fetch bonus statistics
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(bonus_amount), 0) as total_bonus
        FROM bonus b
        JOIN predictions p ON b.prediction_id = p.prediction_id
        JOIN stories s ON p.story_id = s.story_id
        WHERE s.created_by = ?
    ");
    $stmt->execute([$user_name]);
    $total_bonus_supplied = $stmt->fetchColumn();
    
    // Monthly bonus
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(bonus_amount), 0) as monthly_bonus
        FROM bonus b
        JOIN predictions p ON b.prediction_id = p.prediction_id
        JOIN stories s ON p.story_id = s.story_id
        WHERE s.created_by = ? 
        AND MONTH(b.awarded_at) = MONTH(CURRENT_DATE())
        AND YEAR(b.awarded_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_name]);
    $monthly_bonus = $stmt->fetchColumn();
    
    // Readers rewarded
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT b.user_id) as readers_rewarded
        FROM bonus b
        JOIN predictions p ON b.prediction_id = p.prediction_id
        JOIN stories s ON p.story_id = s.story_id
        WHERE s.created_by = ?
    ");
    $stmt->execute([$user_name]);
    $readers_rewarded = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Query Error in manage_story.php: " . $e->getMessage());
}

// Helper function
function formatNumber($num) {
    if ($num >= 1000000) {
        return number_format($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return number_format($num / 1000, 1) . 'K';
    }
    return number_format($num);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stories - Story Pulse</title>
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
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Manage Your Stories</h1>
            <p class="mt-2 text-gray-600">View, edit, and manage all your stories and parts</p>
        </div>

        <!-- Bonus Supply Section -->
        <div class="mb-8">
            <div class="bg-gradient-to-r from-amber-400 via-orange-500 to-red-500 rounded-xl shadow-lg p-6 text-white card-hover">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 rounded-full p-3">
                            <i class="fas fa-coins text-3xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">Bonus Supply</h2>
                            <p class="text-white text-opacity-90 text-sm">Reward your readers for accurate predictions</p>
                        </div>
                    </div>
                    <button onclick="window.location.href='bonus_supply.php'" class="bg-white text-orange-600 px-5 py-2 rounded-lg font-semibold hover:bg-opacity-90 transition shadow-lg">
                        <i class="fas fa-gift mr-2"></i> Supply Bonus
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-white text-opacity-80 text-xs font-medium">Total Supplied</p>
                            <i class="fas fa-chart-line text-sm"></i>
                        </div>
                        <p class="text-2xl font-bold">₹<?php echo number_format($total_bonus_supplied, 2); ?></p>
                        <p class="text-xs text-white text-opacity-70 mt-1">All time</p>
                    </div>

                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-white text-opacity-80 text-xs font-medium">This Month</p>
                            <i class="fas fa-calendar-alt text-sm"></i>
                        </div>
                        <p class="text-2xl font-bold">₹<?php echo number_format($monthly_bonus, 2); ?></p>
                        <p class="text-xs text-white text-opacity-70 mt-1">Current month</p>
                    </div>

                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-white text-opacity-80 text-xs font-medium">Readers Rewarded</p>
                            <i class="fas fa-users text-sm"></i>
                        </div>
                        <p class="text-2xl font-bold"><?php echo formatNumber($readers_rewarded); ?></p>
                        <p class="text-xs text-white text-opacity-70 mt-1">Unique readers</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stories Container -->
        <div class="space-y-6">
            <?php if (!empty($stories)): ?>
                <?php foreach ($stories as $story): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover">
                        <!-- Story Header -->
                        <div class="border-l-4 border-purple-600 p-6">
                            <div class="flex items-start space-x-4">
                                <img src="<?php echo htmlspecialchars($story['cover_image_url'] ?: 'https://via.placeholder.com/150'); ?>" 
                                     alt="Cover" 
                                     class="w-24 h-24 rounded-lg object-cover shadow-md">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($story['title']); ?></h2>
                                            <div class="flex items-center space-x-4 mt-2 text-sm text-gray-600">
                                                <span><i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($story['category']); ?></span>
                                                <span><i class="fas fa-list mr-1"></i> Part <?php echo $story['current_part_no']; ?>/<?php echo $story['total_parts']; ?></span>
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $story['is_completed'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo $story['is_completed'] ? 'Completed' : 'Active'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="flex space-x-3 text-sm text-gray-600">
                                                <span><i class="fas fa-eye mr-1"></i> <?php echo formatNumber($story['view_count'] ?? 0); ?></span>
                                                <span><i class="fas fa-heart mr-1"></i> <?php echo formatNumber($story['like_count'] ?? 0); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-gray-600"><?php echo htmlspecialchars($story['description']); ?></p>
                                    
                                    <!-- Story Actions -->
                                    <div class="flex space-x-3 mt-4">
                                        <a href="edit_story.php?id=<?php echo $story['story_id']; ?>" 
                                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                                            <i class="fas fa-edit mr-1"></i> Edit Story
                                        </a>
                                        <a href="delete_story.php?id=<?php echo $story['story_id']; ?>" 
                                           class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium"
                                           onclick="return confirm('Are you sure you want to delete this story? This will also delete all its parts.');">
                                            <i class="fas fa-trash mr-1"></i> Delete Story
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Story Parts -->
                        <div class="bg-gray-50 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-book-open mr-2 text-purple-600"></i> Story Parts
                            </h3>
                            
                            <?php if (!empty($story['parts'])): ?>
                                <div class="space-y-3">
                                    <?php foreach ($story['parts'] as $part): ?>
                                        <div class="bg-white rounded-lg p-4 border-l-3 border-green-500 shadow-sm hover:shadow-md transition">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <h4 class="text-lg font-semibold text-gray-900">
                                                        <i class="fas fa-file-alt text-green-600 mr-2"></i>
                                                        Part #<?php echo htmlspecialchars($part['part_number']); ?>
                                                    </h4>
                                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 text-sm text-gray-600">
                                                        <span><i class="fas fa-calendar mr-1"></i> <?php echo date('M d, Y', strtotime($part['upload_date'])); ?></span>
                                                        <span><i class="fas fa-clock mr-1"></i> Deadline: <?php echo date('M d, Y', strtotime($part['prediction_deadline'])); ?></span>
                                                        <span><i class="fas fa-lightbulb mr-1"></i> <?php echo $part['total_predictions']; ?> predictions</span>
                                                        <span><i class="fas fa-hashtag mr-1"></i> ID: <?php echo $part['part_id']; ?></span>
                                                    </div>
                                                    <p class="mt-2 text-gray-500 italic text-sm">
                                                        "<?php echo htmlspecialchars(substr($part['content'], 0, 150)); ?>..."
                                                    </p>
                                                </div>
                                                <div class="flex space-x-2 ml-4">
                                                    <a href="edit_story_part.php?id=<?php echo $part['part_id']; ?>" 
                                                       class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_story_part.php?id=<?php echo $part['part_id']; ?>" 
                                                       class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm"
                                                       onclick="return confirm('Are you sure you want to delete this part?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                                    <p class="text-gray-500">No parts found for this story.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-16">
                    <i class="fas fa-book-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Stories Yet</h3>
                    <p class="text-gray-500 mb-6">You haven't created any stories. Start writing your first story!</p>
                    <a href="add_story.php" class="inline-block px-6 py-3 gradient-bg text-white rounded-lg font-semibold hover:opacity-90 transition">
                        <i class="fas fa-plus-circle mr-2"></i> Create Your First Story
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>