<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is an author
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'author') {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get ranking category from URL (default: overall)
$category = isset($_GET['category']) ? $_GET['category'] : 'overall';

// Initialize variables
$top_authors = [];
$current_author_rank = 0;
$current_author_stats = null;

// Weighted formula for overall score
// (total_stories * 10) + (follower_count * 5) + (total_views / 100) + (total_likes * 3)

try {
    // Build query based on category
    $order_by = '';
    $score_formula = '';
    
    switch ($category) {
        case 'stories':
            $order_by = 'total_stories DESC';
            $score_formula = 'total_stories';
            break;
        case 'views':
            $order_by = 'total_views DESC';
            $score_formula = 'total_views';
            break;
        case 'likes':
            $order_by = 'total_likes DESC';
            $score_formula = 'total_likes';
            break;
        case 'followers':
            $order_by = 'follower_count DESC';
            $score_formula = 'follower_count';
            break;
        default: // overall
            $score_formula = '(total_stories * 10) + (follower_count * 5) + (COALESCE(total_views, 0) / 100) + (total_likes * 3)';
            $order_by = 'overall_score DESC';
            break;
    }
    
    // Fetch top authors
    $sql = "
        SELECT 
            user_id,
            user_name,
            first_name,
            last_name,
            profile_picture,
            total_stories,
            total_views,
            follower_count,
            total_likes,
            created_at,
            $score_formula as score
        FROM users
        WHERE user_type = 'author'
        ORDER BY $order_by
    ";
    
    $stmt = $pdo->query($sql);
    $all_authors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add rank to each author
    $rank = 1;
    foreach ($all_authors as &$author) {
        $author['rank'] = $rank;
        if ($author['user_id'] == $user_id) {
            $current_author_rank = $rank;
            $current_author_stats = $author;
        }
        $rank++;
    }
    
    $top_authors = $all_authors;
    
} catch (PDOException $e) {
    error_log("Scoreboard error: " . $e->getMessage());
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

function getScoreLabel($category) {
    $labels = [
        'overall' => 'Overall Score',
        'stories' => 'Total Stories',
        'views' => 'Total Views',
        'likes' => 'Total Likes',
        'followers' => 'Total Followers'
    ];
    return $labels[$category] ?? 'Score';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Scoreboard - Story Pulse</title>
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
        .gold-gradient {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        .silver-gradient {
            background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%);
        }
        .bronze-gradient {
            background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
        }
        .podium-card {
            transition: all 0.3s ease;
        }
        .podium-card:hover {
            transform: translateY(-10px);
        }
        .tab-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-book-open text-3xl text-purple-600"></i>
                        <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
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
                            <i class="fas fa-gift mr-2"></i> Bonus Supply
                        </a>
                        <a href="follows_likes.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-heart mr-2"></i> Follows & Likes
                        </a>
                        <a href="scoreboard.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-trophy mr-2"></i> Scoreboard
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
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-trophy text-yellow-500 mr-3"></i>Author Scoreboard
            </h1>
            <p class="text-gray-600 text-lg">Compete with fellow authors and climb the ranks!</p>
        </div>

        <!-- Category Tabs -->
        <div class="flex flex-wrap justify-center gap-3 mb-8">
            <a href="scoreboard.php?category=overall" 
               class="px-6 py-3 rounded-lg font-semibold transition shadow-md <?php echo $category === 'overall' ? 'tab-active' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-star mr-2"></i>Overall Score
            </a>
            <a href="scoreboard.php?category=stories" 
               class="px-6 py-3 rounded-lg font-semibold transition shadow-md <?php echo $category === 'stories' ? 'tab-active' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-book mr-2"></i>Most Stories
            </a>
            <a href="scoreboard.php?category=views" 
               class="px-6 py-3 rounded-lg font-semibold transition shadow-md <?php echo $category === 'views' ? 'tab-active' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-eye mr-2"></i>Most Views
            </a>
            <a href="scoreboard.php?category=likes" 
               class="px-6 py-3 rounded-lg font-semibold transition shadow-md <?php echo $category === 'likes' ? 'tab-active' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-heart mr-2"></i>Most Liked
            </a>
            <a href="scoreboard.php?category=followers" 
               class="px-6 py-3 rounded-lg font-semibold transition shadow-md <?php echo $category === 'followers' ? 'tab-active' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-users mr-2"></i>Most Followed
            </a>
        </div>

        <?php if (!empty($top_authors)): ?>
            <!-- Top 3 Podium -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php 
                $podium_order = [1 => 1, 0 => 0, 2 => 2]; // Second place, First place, Third place
                $colors = [
                    0 => ['gradient' => 'gold-gradient', 'icon' => 'fa-crown', 'text' => 'text-yellow-600'],
                    1 => ['gradient' => 'silver-gradient', 'icon' => 'fa-medal', 'text' => 'text-gray-600'],
                    2 => ['gradient' => 'bronze-gradient', 'icon' => 'fa-medal', 'text' => 'text-orange-800']
                ];
                
                foreach ($podium_order as $display_pos => $actual_pos):
                    if (!isset($top_authors[$actual_pos])) continue;
                    $author = $top_authors[$actual_pos];
                    $color = $colors[$actual_pos];
                    $profile_pic = $author['profile_picture'] ?: 
                        "https://ui-avatars.com/api/?name=" . urlencode($author['first_name'] . ' ' . $author['last_name']) . "&background=667eea&color=fff&size=200";
                ?>
                <div class="<?php echo $display_pos === 0 ? 'md:order-2' : ($display_pos === 1 ? 'md:order-1 md:mt-8' : 'md:order-3 md:mt-8'); ?>">
                    <div class="podium-card bg-white rounded-xl shadow-xl overflow-hidden">
                        <div class="<?php echo $color['gradient']; ?> p-6 text-white text-center">
                            <i class="fas <?php echo $color['icon']; ?> text-4xl mb-2"></i>
                            <p class="text-2xl font-bold">#<?php echo $author['rank']; ?></p>
                        </div>
                        <div class="p-6 text-center">
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                 alt="Profile" 
                                 class="w-24 h-24 rounded-full mx-auto mb-4 border-4 <?php echo $actual_pos === 0 ? 'border-yellow-400' : ($actual_pos === 1 ? 'border-gray-400' : 'border-orange-600'); ?> object-cover">
                            <h3 class="text-xl font-bold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($author['first_name'] . ' ' . $author['last_name']); ?>
                            </h3>
                            <p class="text-sm text-gray-500 mb-4">@<?php echo htmlspecialchars($author['user_name']); ?></p>
                            <div class="<?php echo $color['gradient']; ?> text-white rounded-lg p-4 mb-4">
                                <p class="text-sm opacity-90"><?php echo getScoreLabel($category); ?></p>
                                <p class="text-3xl font-bold"><?php echo formatNumber($author['score']); ?></p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-xs text-gray-600">
                                <div>
                                    <i class="fas fa-book text-purple-600 mr-1"></i>
                                    <?php echo formatNumber($author['total_stories']); ?> Stories
                                </div>
                                <div>
                                    <i class="fas fa-users text-green-600 mr-1"></i>
                                    <?php echo formatNumber($author['follower_count']); ?> Followers
                                </div>
                                <div>
                                    <i class="fas fa-eye text-blue-600 mr-1"></i>
                                    <?php echo formatNumber($author['total_views']); ?> Views
                                </div>
                                <div>
                                    <i class="fas fa-heart text-pink-600 mr-1"></i>
                                    <?php echo formatNumber($author['total_likes']); ?> Likes
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Rest of the Rankings (Table) -->
            <?php if (count($top_authors) > 3): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-list-ol mr-2 text-purple-600"></i>Full Rankings
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo getScoreLabel($category); ?></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stories</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Followers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Likes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach (array_slice($top_authors, 3) as $author): 
                                $profile_pic = $author['profile_picture'] ?: 
                                    "https://ui-avatars.com/api/?name=" . urlencode($author['first_name'] . ' ' . $author['last_name']) . "&background=667eea&color=fff&size=80";
                                $is_current_user = $author['user_id'] == $user_id;
                            ?>
                            <tr class="hover:bg-gray-50 transition <?php echo $is_current_user ? 'bg-purple-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-2xl font-bold <?php echo $is_current_user ? 'text-purple-600' : 'text-gray-400'; ?>">
                                        #<?php echo $author['rank']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                             alt="Profile" 
                                             class="w-10 h-10 rounded-full mr-3 object-cover">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($author['first_name'] . ' ' . $author['last_name']); ?>
                                                <?php if ($is_current_user): ?>
                                                    <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($author['user_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-lg font-bold text-purple-600">
                                        <?php echo formatNumber($author['score']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-book text-purple-600 mr-1"></i>
                                    <?php echo formatNumber($author['total_stories']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-eye text-blue-600 mr-1"></i>
                                    <?php echo formatNumber($author['total_views']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-users text-green-600 mr-1"></i>
                                    <?php echo formatNumber($author['follower_count']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-heart text-pink-600 mr-1"></i>
                                    <?php echo formatNumber($author['total_likes']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-xl shadow">
                <i class="fas fa-trophy text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No authors found</p>
            </div>
        <?php endif; ?>

        <!-- Current User Stats -->
        <?php if ($current_author_stats): ?>
        <div class="bg-gradient-to-r from-purple-500 to-indigo-600 rounded-xl shadow-xl p-8 text-white">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold">
                    <i class="fas fa-user-circle mr-2"></i>Your Performance
                </h2>
                <span class="px-4 py-2 bg-white bg-opacity-20 rounded-full text-lg font-bold">
                    Rank #<?php echo $current_author_rank; ?>
                </span>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 text-center">
                    <i class="fas fa-star text-3xl mb-2"></i>
                    <p class="text-2xl font-bold"><?php echo formatNumber($current_author_stats['score']); ?></p>
                    <p class="text-sm opacity-90"><?php echo getScoreLabel($category); ?></p>
                </div>
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 text-center">
                    <i class="fas fa-book text-3xl mb-2"></i>
                    <p class="text-2xl font-bold"><?php echo formatNumber($current_author_stats['total_stories']); ?></p>
                    <p class="text-sm opacity-90">Stories</p>
                </div>
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 text-center">
                    <i class="fas fa-eye text-3xl mb-2"></i>
                    <p class="text-2xl font-bold"><?php echo formatNumber($current_author_stats['total_views']); ?></p>
                    <p class="text-sm opacity-90">Views</p>
                </div>
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 text-center">
                    <i class="fas fa-users text-3xl mb-2"></i>
                    <p class="text-2xl font-bold"><?php echo formatNumber($current_author_stats['follower_count']); ?></p>
                    <p class="text-sm opacity-90">Followers</p>
                </div>
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-4 text-center">
                    <i class="fas fa-heart text-3xl mb-2"></i>
                    <p class="text-2xl font-bold"><?php echo formatNumber($current_author_stats['total_likes']); ?></p>
                    <p class="text-sm opacity-90">Likes</p>
                </div>
            </div>
            
            <div class="mt-6 text-center">
                <p class="text-white text-opacity-90">
                    <?php if ($current_author_rank <= 3): ?>
                        🎉 Congratulations! You're in the top 3!
                    <?php elseif ($current_author_rank <= 10): ?>
                        🔥 Great work! You're in the top 10!
                    <?php else: ?>
                        💪 Keep writing to climb the ranks!
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>