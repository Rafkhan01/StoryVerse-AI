<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in (but allow guests to view)
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Helper function to determine story status based on dates
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

// Get filter and sort parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$author_filter = isset($_GET['author']) ? $_GET['author'] : '';

$results_to_display = [];
$all_authors = [];

try {
    // Fetch all unique authors for the dropdown
    $stmt = $pdo->query("SELECT DISTINCT created_by FROM stories ORDER BY created_by ASC");
    $all_authors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Determine the query based on the filter status
    if ($filter_status === 'my_predictions' && $is_logged_in) {
        // Query to fetch only the predictions the current user has made
        $sql = "
            SELECT
                p.prediction_id,
                p.prediction_text,
                p.created_at AS prediction_date,
                s.title,
                sp.part_number,
                sp.story_id,
                s.total_parts,
                s.cover_image_url,
                (SELECT COUNT(*) FROM likes WHERE prediction_id = p.prediction_id) AS prediction_likes
            FROM predictions p
            JOIN story_parts sp ON p.story_id = sp.story_id AND p.prediction_part_no = sp.part_number
            JOIN stories s ON sp.story_id = s.story_id
            WHERE p.user_id = :user_id
        ";
        
        if ($sort_by === 'newest') {
            $sql .= " ORDER BY p.created_at DESC";
        } elseif ($sort_by === 'oldest') {
            $sql .= " ORDER BY p.created_at ASC";
        } elseif ($sort_by === 'popular') {
            $sql .= " ORDER BY prediction_likes DESC, p.created_at DESC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $results_to_display = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($filter_status === 'my_author_stories' && $is_logged_in) {
        // Query to fetch stories from authors the user follows
        $sql = "
            SELECT
                sp.*,
                s.title,
                s.created_by,
                s.category,
                s.description,
                s.cover_image_url,
                s.total_parts,
                s.created_at AS story_created_at
            FROM story_parts sp
            JOIN stories s ON sp.story_id = s.story_id
            JOIN users u ON s.created_by = u.user_name
            JOIN follows f ON f.author_user_id = u.user_id
            WHERE f.follower_user_id = :user_id
        ";
        
        // Apply author filter if selected
        if (!empty($author_filter)) {
            $sql .= " AND s.created_by = :author_name";
        }
        
        if ($sort_by === 'popular') {
            $sql .= " ORDER BY s.view_count DESC, sp.upload_date DESC";
        } elseif ($sort_by === 'oldest') {
            $sql .= " ORDER BY sp.upload_date ASC";
        } else {
            $sql .= " ORDER BY sp.upload_date DESC";
        }
        
        $stmt = $pdo->prepare($sql);
        $params = [':user_id' => $user_id];
        if (!empty($author_filter)) {
            $params[':author_name'] = $author_filter;
        }
        $stmt->execute($params);
        $all_story_parts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_story_parts_raw as $part_data) {
            $part_data['dynamic_status'] = getStoryStatus($part_data['upload_date'], $part_data['prediction_deadline']);
            $results_to_display[] = $part_data;
        }
        
    } else {
        // Build the base query to fetch ALL story parts
        $sql = "
            SELECT
                sp.*,
                s.title,
                s.created_by,
                s.category,
                s.description,
                s.cover_image_url,
                s.total_parts,
                s.view_count,
                s.created_at AS story_created_at
            FROM story_parts sp
            JOIN stories s ON sp.story_id = s.story_id
        ";
        
        // Apply author filter if selected
        $where_clauses = [];
        $params = [];
        
        if (!empty($author_filter)) {
            $where_clauses[] = "s.created_by = :author_name";
            $params[':author_name'] = $author_filter;
        }
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Build the ORDER BY clause
        if ($sort_by === 'popular') {
            $sql .= " ORDER BY s.view_count DESC, sp.upload_date DESC";
        } elseif ($sort_by === 'oldest') {
            $sql .= " ORDER BY sp.upload_date ASC";
        } else {
            $sql .= " ORDER BY sp.upload_date DESC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_story_parts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter the results in PHP for status
        foreach ($all_story_parts_raw as $part_data) {
            $part_data['dynamic_status'] = getStoryStatus($part_data['upload_date'], $part_data['prediction_deadline']);
            if ($filter_status === 'all' || $filter_status === $part_data['dynamic_status']) {
                $results_to_display[] = $part_data;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $results_to_display = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Stories - StoryPulse</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-color: #6a4cff;
            --secondary-color: #4c6fff;
            --text-color: #2c3e50;
            --background-color: #f0f4f8;
            --card-background: #ffffff;
            --accent-color: #ff6b6b;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        nav {
            display: flex;
            width: 100%;
            background: var(--card-background);
            padding: 15px 5%;
            box-shadow: var(--shadow-light);
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navtitle {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 800;
            margin: 0;
        }

        .navcta {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .navlinks {
            border: none;
            background: transparent;
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px;
        }

        nav a {
            text-decoration: none;
            color: var(--text-color);
            transition: color 0.3s ease;
        }

        .navlinks:hover {
            background-color: var(--primary-color);
        }

        .navlinks:hover a {
            color: var(--card-background);
        }

        .cta {
            padding: 12px 30px;
            background: var(--primary-color);
            border-radius: 50px;
            color: white;
            transition: all 0.4s ease;
            outline: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(106, 76, 255, 0.4);
        }

        .cta:hover {
            background: var(--secondary-color);
            color: white;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 20px rgba(76, 111, 255, 0.6);
        }

        h2 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2em, 4vw, 3em);
            font-weight: 700;
            color: var(--primary-color);
        }

        .filter-tabs a {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: var(--shadow-light);
        }

        .filter-tabs .active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 5px 15px rgba(106, 76, 255, 0.4);
        }

        .filter-tabs a:not(.active) {
            background-color: var(--card-background);
            color: var(--text-color);
            border: 1px solid #e0e9ff;
        }

        .filter-tabs a:not(.active):hover {
            background-color: #f0f4f8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .story-card, .prediction-card {
            background-color: var(--card-background);
            border-radius: 20px;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            border: 1px solid #e0e9ff;
        }

        .story-card:hover, .prediction-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .story-card img {
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .story-card .status {
            background-color: #e6ffe6;
            color: darkgreen;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            box-shadow: inset 0 0 5px rgba(0, 128, 0, 0.2);
            display: flex;
            align-items: center;
        }

        .story-card .ping {
            background: #39b54a;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
            animation: pulse 1.5s infinite;
        }

        .story-card .progress-container {
            width: 100%;
            background: #e0e9ff;
            border-radius: 50px;
            height: 8px;
            margin-top: 10px;
        }

        .story-card .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #6a4cff, #4c6fff);
            border-radius: 50px;
            transition: width 0.5s ease;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(57, 181, 74, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(57, 181, 74, 0); }
            100% { box-shadow: 0 0 0 0 rgba(57, 181, 74, 0); }
        }
        
        .prediction-text {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
        }
        
        .disabled-filter {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav>
        <div class="navtitle">StoryPulse</div>
        <div class="navcta">
            <?php if ($is_logged_in): ?>
                <button class="navlinks"><a href="index.php">Home</a></button>
                <button class="navlinks"><a href="stories.php">Stories</a></button>
                <button class="navlinks"><a href="leaderboard.php">Leaderboard</a></button>
                <button class="navlinks"><a href="hiw.html">How it works</a></button>
                <button class="cta"><a href="logout.php">Logout</a></button>
            <?php else: ?>
                <button class="navlinks"><a href="stories.php">Explore More</a></button>
                <button class="navlinks"><a href="hiw.html">How it works</a></button>
                <button class="cta"><a href="signin.php">Sign In</a></button>
            <?php endif; ?>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-4 py-8">
        <div data-aos="fade-right">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Browse Stories</h2>
            <p class="text-gray-500 mb-8">Discover and join ongoing stories or explore completed ones.</p>
        </div>

        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4 mb-8 filter-tabs" data-aos="fade-up" data-aos-delay="100">
            <a href="stories.php?status=all&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="<?php echo ($filter_status === 'all' ? 'active' : ''); ?>">
                All Stories
            </a>
            <a href="stories.php?status=active&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="<?php echo ($filter_status === 'active' ? 'active' : ''); ?>">
                Active Stories
            </a>
            <a href="stories.php?status=coming_soon&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="<?php echo ($filter_status === 'coming_soon' ? 'active' : ''); ?>">
                Coming Soon
            </a>
            <a href="stories.php?status=completed&sort=<?php echo htmlspecialchars($sort_by); ?>" 
               class="<?php echo ($filter_status === 'completed' ? 'active' : ''); ?>">
                Completed
            </a>
            <a href="<?php echo $is_logged_in ? 'stories.php?status=my_predictions&sort=' . htmlspecialchars($sort_by) : 'javascript:void(0)'; ?>" 
               class="<?php echo ($filter_status === 'my_predictions' ? 'active' : '') . (!$is_logged_in ? ' disabled-filter' : ''); ?>"
               <?php if (!$is_logged_in): ?>title="Login to view your predictions"<?php endif; ?>>
                My Predictions
            </a>
            <a href="<?php echo $is_logged_in ? 'stories.php?status=my_author_stories&sort=' . htmlspecialchars($sort_by) : 'javascript:void(0)'; ?>" 
               class="<?php echo ($filter_status === 'my_author_stories' ? 'active' : '') . (!$is_logged_in ? ' disabled-filter' : ''); ?>"
               <?php if (!$is_logged_in): ?>title="Login to view followed authors"<?php endif; ?>>
                My Author Stories
            </a>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6" data-aos="fade-up" data-aos-delay="200">
            <!-- Author Filter Dropdown -->
            <?php if ($filter_status === 'my_author_stories' && !empty($all_authors)): ?>
            <div class="relative inline-block text-left">
                <select onchange="window.location.href='stories.php?status=my_author_stories&sort=<?php echo htmlspecialchars($sort_by); ?>&author=' + encodeURIComponent(this.value)" 
                    class="block appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded-lg shadow-sm leading-tight focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">All Followed Authors</option>
                    <?php foreach ($all_authors as $author): ?>
                        <option value="<?php echo htmlspecialchars($author); ?>" <?php echo ($author_filter === $author ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($author); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                </div>
            </div>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
            
            <!-- Sort Dropdown -->
            <div class="relative inline-block text-left">
                <select onchange="window.location.href='stories.php?status=<?php echo htmlspecialchars($filter_status); ?>&sort=' + this.value + '<?php echo !empty($author_filter) ? '&author=' . urlencode($author_filter) : ''; ?>'" 
                    class="block appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded-lg shadow-sm leading-tight focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="newest" <?php echo ($sort_by === 'newest' ? 'selected' : ''); ?>>Sort by: Newest</option>
                    <option value="oldest" <?php echo ($sort_by === 'oldest' ? 'selected' : ''); ?>>Sort by: Oldest</option>
                    <option value="popular" <?php echo ($sort_by === 'popular' ? 'selected' : ''); ?>>Sort by: Popular</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($results_to_display)): ?>
                <div class="col-span-full text-center py-12">
                    <svg class="mx-auto h-24 w-24 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="text-gray-600 text-lg">
                        <?php if ($filter_status === 'my_predictions'): ?>
                            You haven't made any predictions yet. Start predicting to appear here!
                        <?php elseif ($filter_status === 'my_author_stories'): ?>
                            <?php if (!empty($author_filter)): ?>
                                No stories found from <?php echo htmlspecialchars($author_filter); ?>.
                            <?php else: ?>
                                You're not following any authors yet. Follow authors to see their stories here!
                            <?php endif; ?>
                        <?php else: ?>
                            No story parts found in this category.
                        <?php endif; ?>
                    </p>
                    <?php if (!$is_logged_in && ($filter_status === 'my_predictions' || $filter_status === 'my_author_stories')): ?>
                        <a href="signin.php" class="inline-block mt-4 px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            Sign In to Continue
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($results_to_display as $index => $item): ?>
                    <?php if ($filter_status === 'my_predictions'): ?>
                        <div class="prediction-card p-6 flex flex-col justify-between" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">
                                    <a href="story_detail.php?story_id=<?php echo htmlspecialchars($item['story_id']); ?>&part_number=<?php echo htmlspecialchars($item['part_number']); ?>" 
                                       class="hover:text-primary-color transition-colors">
                                        <?php echo htmlspecialchars($item['title']); ?> - Part <?php echo htmlspecialchars($item['part_number']); ?>
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-500 mb-4">Predicted on <?php echo date('M d, Y', strtotime($item['prediction_date'])); ?></p>
                                <div class="prediction-text text-gray-700 mb-4">
                                    <p class="line-clamp-4"><?php echo htmlspecialchars($item['prediction_text']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-4 text-gray-500 text-sm">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg>
                                    <?php echo htmlspecialchars($item['prediction_likes']); ?> Likes
                                </span>
                                <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-1 px-3 rounded-full text-xs transition-colors" 
                                    onclick="location.href='story_detail.php?story_id=<?php echo htmlspecialchars($item['story_id']); ?>&part_number=<?php echo htmlspecialchars($item['part_number']); ?>'">
                                    View/Edit
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php
                        $dynamic_status = getStoryStatus($item['upload_date'], $item['prediction_deadline']);
                        $progress_percent = ($item['total_parts'] > 0) ? ($item['part_number'] / $item['total_parts']) * 100 : 0;
                        $status_text = ucfirst(str_replace('_', ' ', $dynamic_status));
                        $status_color_class = '';
                        if ($dynamic_status === 'active') {
                            $status_color_class = 'bg-green-100 text-green-700';
                        } elseif ($dynamic_status === 'coming_soon') {
                            $status_color_class = 'bg-yellow-100 text-yellow-700';
                        } elseif ($dynamic_status === 'completed') {
                            $status_color_class = 'bg-blue-100 text-blue-700';
                        }
                        
                        $story_url = 'track_view.php?story_id=' . htmlspecialchars($item['story_id']) . '&part_number=' . htmlspecialchars($item['part_number']);
                        $cover_image_src = !empty($item['cover_image_url']) ? htmlspecialchars($item['cover_image_url']) : 'https://placehold.co/400x200/e0e7ff/6366f1?text=Story+Cover';
                        ?>
                        
                        <!-- Story Card - NOT wrapped in <a> tag -->
                        <div class="story-card block" onclick="window.location.href='<?php echo $story_url; ?>'" style="cursor: pointer;">
                            <div class="relative h-48 flex items-center justify-center text-indigo-400 text-2xl font-semibold">
                                <img src="<?php echo $cover_image_src; ?>" 
                                    onerror="this.onerror=null;this.src='https://placehold.co/400x200/e0e7ff/6366f1?text=Story+Cover';" 
                                    alt="Story Cover" 
                                    class="w-full h-full object-cover">
                                <div class="absolute top-3 left-3 px-3 py-1 <?php echo $status_color_class; ?> text-xs font-semibold rounded-full flex items-center">
                                    <?php if ($dynamic_status === 'active'): ?>
                                        <span class="ping"></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($status_text); ?>
                                </div>
                            </div>
                            <div class="p-6">
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($item['title']); ?> - Part <?php echo htmlspecialchars($item['part_number']); ?>
                                </h3>
                                <p class="text-sm text-gray-500 mb-3">
                                    By <span 
                                        onclick="event.stopPropagation(); window.location.href='author_profile.php?author=<?php echo urlencode($item['created_by']); ?>'"
                                        class="text-purple-600 hover:text-purple-800 font-semibold hover:underline cursor-pointer transition">
                                        <?php echo htmlspecialchars($item['created_by']); ?>
                                    </span>
                                </p>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="flex justify-between items-center text-sm text-gray-500 mb-2">
                                    <span>Part <?php echo htmlspecialchars($item['part_number']); ?> of <?php echo htmlspecialchars($item['total_parts']); ?></span>
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <?php echo isset($item['view_count']) ? number_format($item['view_count']) : '0'; ?>
                                    </span>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init({
        once: true,
      });
    </script>
</body>
</html>