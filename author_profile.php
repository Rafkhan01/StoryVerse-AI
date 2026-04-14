<?php
session_start();
require_once 'db_connect.php';

// Must be logged in to view author profiles
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$author_username = isset($_GET['author']) ? trim($_GET['author']) : '';

if (empty($author_username)) {
    header('Location: stories.php');
    exit();
}

$author = null;
$author_stories = [];
$is_following = false;

try {
    // Fetch author information
    $stmt = $pdo->prepare("
        SELECT 
            user_id, user_name, first_name, last_name, profile_picture,
            bio, website, instagram, total_stories, total_views, 
            follower_count, total_likes, created_at
        FROM users 
        WHERE user_name = ? AND user_type = 'author'
    ");
    $stmt->execute([$author_username]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$author) {
        header('Location: stories.php');
        exit();
    }
    
    // Check if current user is following this author
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_user_id = ? AND author_user_id = ?");
    $stmt->execute([$current_user_id, $author['user_id']]);
    $is_following = $stmt->fetchColumn() > 0;
    
    // Fetch author's stories with parts
    $stmt = $pdo->prepare("
        SELECT 
            s.story_id,
            s.title,
            s.category,
            s.cover_image_url,
            s.description,
            sp.part_number,
            sp.upload_date,
            sp.prediction_deadline,
            s.view_count,
            s.like_count
        FROM stories s
        LEFT JOIN story_parts sp ON s.story_id = sp.story_id
        WHERE s.created_by = ?
        ORDER BY sp.upload_date DESC
    ");
    $stmt->execute([$author_username]);
    $author_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching author profile: " . $e->getMessage());
}

$profile_pic = $author['profile_picture'] ?: 
    "https://ui-avatars.com/api/?name=" . urlencode($author['first_name'] . ' ' . $author['last_name']) . "&background=667eea&color=fff&size=200";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($author['first_name'] . ' ' . $author['last_name']); ?> - Story Pulse</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-book-open text-3xl text-purple-600"></i>
                    <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="stories.php" class="text-gray-700 hover:text-purple-600 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Stories
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Author Profile Header -->
    <div class="gradient-bg text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                     alt="Author" 
                     class="w-32 h-32 rounded-full border-4 border-white shadow-xl object-cover">
                <div class="flex-1 text-center md:text-left">
                    <h1 class="text-4xl font-bold mb-2">
                        <?php echo htmlspecialchars($author['first_name'] . ' ' . $author['last_name']); ?>
                    </h1>
                    <p class="text-xl text-white text-opacity-90 mb-4">@<?php echo htmlspecialchars($author['user_name']); ?></p>
                    
                    <?php if (!empty($author['bio'])): ?>
                    <p class="text-white text-opacity-90 max-w-2xl mb-6">
                        <?php echo nl2br(htmlspecialchars($author['bio'])); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="flex flex-wrap gap-4 justify-center md:justify-start mb-6">
                        <?php if (!empty($author['website'])): ?>
                        <a href="<?php echo htmlspecialchars($author['website']); ?>" target="_blank" 
                           class="inline-flex items-center px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition">
                            <i class="fas fa-globe mr-2"></i> Website
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($author['instagram'])): ?>
                        <a href="https://instagram.com/<?php echo htmlspecialchars($author['instagram']); ?>" target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition">
                            <i class="fab fa-instagram mr-2"></i> @<?php echo htmlspecialchars($author['instagram']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <button onclick="toggleFollow()" id="follow-btn"
                        class="px-6 py-3 rounded-lg font-semibold transition <?php echo $is_following ? 'bg-white bg-opacity-20 hover:bg-opacity-30' : 'bg-white text-purple-600 hover:bg-opacity-90'; ?>">
                        <i class="fas fa-user-<?php echo $is_following ? 'check' : 'plus'; ?> mr-2"></i>
                        <span id="follow-text"><?php echo $is_following ? 'Following' : 'Follow'; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Author Statistics -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <i class="fas fa-book text-3xl text-purple-600 mb-2"></i>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($author['total_stories']); ?></p>
                <p class="text-sm text-gray-600">Stories</p>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <i class="fas fa-eye text-3xl text-blue-600 mb-2"></i>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($author['total_views']); ?></p>
                <p class="text-sm text-gray-600">Total Views</p>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <i class="fas fa-users text-3xl text-green-600 mb-2"></i>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($author['follower_count']); ?></p>
                <p class="text-sm text-gray-600">Followers</p>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <i class="fas fa-heart text-3xl text-pink-600 mb-2"></i>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($author['total_likes']); ?></p>
                <p class="text-sm text-gray-600">Likes</p>
            </div>
        </div>
    </div>

    <!-- Author Stories -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-3xl font-bold text-gray-900 mb-8">
            <i class="fas fa-books mr-2 text-purple-600"></i>Stories by <?php echo htmlspecialchars($author['first_name']); ?>
        </h2>

        <?php if (empty($author_stories)): ?>
            <div class="text-center py-12 bg-white rounded-xl shadow">
                <i class="fas fa-book-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No stories published yet</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Story</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Genre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stats</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($author_stories as $story): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <?php if ($story['cover_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($story['cover_image_url']); ?>" 
                                         alt="Cover" 
                                         class="w-12 h-12 rounded object-cover mr-3">
                                    <?php endif; ?>
                                    <div>
                                        <a href="story_detail.php?story_id=<?php echo $story['story_id']; ?>&part_number=<?php echo $story['part_number']; ?>"
                                           class="text-sm font-medium text-gray-900 hover:text-purple-600">
                                            <?php echo htmlspecialchars($story['title']); ?>
                                        </a>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($story['description']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    Part <?php echo $story['part_number']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($story['category']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($story['upload_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($story['prediction_deadline'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex space-x-3">
                                    <span><i class="fas fa-eye text-blue-500 mr-1"></i><?php echo number_format($story['view_count']); ?></span>
                                    <span><i class="fas fa-heart text-pink-500 mr-1"></i><?php echo number_format($story['like_count']); ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const authorUserId = <?php echo $author['user_id']; ?>;
        const currentUserId = <?php echo $current_user_id; ?>;
        
        function toggleFollow() {
            const btn = document.getElementById('follow-btn');
            const text = document.getElementById('follow-text');
            const isFollowing = text.textContent === 'Following';
            const action = isFollowing ? 'unfollow' : 'follow';
            
            const formData = new FormData();
            formData.append('author_user_id', authorUserId);
            formData.append('action', action);
            
            fetch('follow_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (action === 'follow') {
                        btn.className = 'px-6 py-3 rounded-lg font-semibold transition bg-white bg-opacity-20 hover:bg-opacity-30';
                        btn.innerHTML = '<i class="fas fa-user-check mr-2"></i><span id="follow-text">Following</span>';
                    } else {
                        btn.className = 'px-6 py-3 rounded-lg font-semibold transition bg-white text-purple-600 hover:bg-opacity-90';
                        btn.innerHTML = '<i class="fas fa-user-plus mr-2"></i><span id="follow-text">Follow</span>';
                    }
                    
                    // Update follower count
                    location.reload();
                } else {
                    alert(data.message || 'An error occurred');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>