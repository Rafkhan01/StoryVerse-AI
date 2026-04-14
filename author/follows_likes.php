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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Follows & Likes Analytics - Story Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
                        <a href="follows_likes.php" class="border-purple-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-heart mr-2"></i> Follows & Likes
                        </a>
                        <a href="scoreboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-trophy mr-2"></i> Scoreboard
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="../logout.php" class="ml-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
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
            <h1 class="text-3xl font-bold text-gray-900">Follows & Likes Analytics</h1>
            <p class="mt-2 text-gray-600">Track your followers and see how your stories are performing</p>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Total Followers Card -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-8 text-white card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium uppercase tracking-wide">Total Followers</p>
                        <p class="text-5xl font-bold mt-2"><?php echo number_format($total_followers); ?></p>
                        <p class="text-sm mt-2 text-white text-opacity-70">
                            <i class="fas fa-users mr-1"></i> People following your stories
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-6">
                        <i class="fas fa-user-friends text-5xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Likes Card -->
            <div class="bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl shadow-lg p-8 text-white card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium uppercase tracking-wide">Total Story Likes</p>
                        <p class="text-5xl font-bold mt-2"><?php echo number_format($total_likes); ?></p>
                        <p class="text-sm mt-2 text-white text-opacity-70">
                            <i class="fas fa-heart mr-1"></i> Likes on your stories
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-6">
                        <i class="fas fa-heart text-5xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Followers List Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    <i class="fas fa-users mr-2 text-blue-600"></i>Your Followers
                </h2>
                <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full font-semibold">
                    <?php echo number_format($total_followers); ?> Followers
                </span>
            </div>

            <?php if (empty($followers_list)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-user-slash text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No followers yet</p>
                    <p class="text-gray-400 text-sm mt-2">Share your stories to gain followers!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($followers_list as $follower): 
                        $profile_pic = $follower['profile_picture'] ?? 
                            "https://ui-avatars.com/api/?name=" . urlencode($follower['first_name'] . ' ' . $follower['last_name']) . "&background=667eea&color=fff";
                    ?>
                    <div class="flex items-center space-x-4 p-4 border border-gray-200 rounded-lg hover:border-blue-400 hover:shadow-md transition">
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                             alt="Profile" 
                             class="w-14 h-14 rounded-full border-2 border-blue-500">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">
                                <?php echo htmlspecialchars($follower['first_name'] . ' ' . $follower['last_name']); ?>
                            </p>
                            <p class="text-xs text-gray-500 truncate">@<?php echo htmlspecialchars($follower['user_name']); ?></p>
                            <div class="flex items-center mt-1 space-x-3 text-xs text-gray-500">
                                <span><i class="fas fa-star text-yellow-500 mr-1"></i><?php echo number_format($follower['total_score']); ?></span>
                                <span><i class="fas fa-clock mr-1"></i><?php echo timeAgo($follower['followed_at']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Story Likes Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    <i class="fas fa-heart mr-2 text-pink-600"></i>Story Likes Analytics
                </h2>
                <span class="px-4 py-2 bg-pink-100 text-pink-800 rounded-full font-semibold">
                    <?php echo number_format($total_likes); ?> Total Likes
                </span>
            </div>

            <?php if (empty($story_likes_data)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-chart-bar text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No story data available</p>
                    <p class="text-gray-400 text-sm mt-2">Create stories to see analytics!</p>
                </div>
            <?php else: ?>
                <!-- Chart -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Likes Distribution by Story Parts</h3>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <canvas id="likesChart" height="100"></canvas>
                    </div>
                </div>

                <!-- Data Table -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Detailed Overview</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Story Title
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Part Number
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Release Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Likes Count
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Performance
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $max_likes = !empty($story_likes_data) ? max(array_column($story_likes_data, 'like_count')) : 1;
                                foreach ($story_likes_data as $data): 
                                    $like_percentage = $max_likes > 0 ? ($data['like_count'] / $max_likes) * 100 : 0;
                                    $bar_color = 'bg-pink-500';
                                    if ($like_percentage >= 75) {
                                        $bar_color = 'bg-green-500';
                                    } elseif ($like_percentage >= 50) {
                                        $bar_color = 'bg-yellow-500';
                                    } elseif ($like_percentage >= 25) {
                                        $bar_color = 'bg-orange-500';
                                    } else {
                                        $bar_color = 'bg-red-500';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($data['title']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Part <?php echo htmlspecialchars($data['part_number']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($data['upload_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-heart text-pink-500 mr-2"></i>
                                            <span class="text-sm font-semibold text-gray-900">
                                                <?php echo number_format($data['like_count']); ?>
                                            </span>
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
        </div>

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
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">
                <i class="fas fa-chart-line mr-2 text-purple-600"></i>Key Insights
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-6 rounded-lg border border-green-200">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-trophy text-2xl text-green-600 mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Most Liked</h3>
                    </div>
                    <p class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($most_liked['title'] ?? 'N/A'); ?> - Part <?php echo $most_liked['part_number'] ?? 'N/A'; ?></p>
                    <p class="text-2xl font-bold text-green-600 mt-2">
                        <?php echo number_format($most_liked['like_count'] ?? 0); ?> likes
                    </p>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-chart-bar text-2xl text-blue-600 mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Average Likes</h3>
                    </div>
                    <p class="text-sm text-gray-700">Per story part</p>
                    <p class="text-2xl font-bold text-blue-600 mt-2">
                        <?php echo number_format($avg_likes, 1); ?> likes
                    </p>
                </div>

                <div class="bg-gradient-to-br from-orange-50 to-red-50 p-6 rounded-lg border border-orange-200">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-arrow-up text-2xl text-orange-600 mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Needs Boost</h3>
                    </div>
                    <p class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($least_liked['title'] ?? 'N/A'); ?> - Part <?php echo $least_liked['part_number'] ?? 'N/A'; ?></p>
                    <p class="text-2xl font-bold text-orange-600 mt-2">
                        <?php echo number_format($least_liked['like_count'] ?? 0); ?> likes
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if (!empty($story_likes_data)): ?>
        // Chart.js configuration
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
    </script>
</body>
</html>