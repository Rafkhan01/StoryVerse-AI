<?php
session_start();
require_once 'db_connect.php'; // Your database connection file

// Check if user is logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// Function to generate user initials for display
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

// --- Leaderboard Logic ---
// Get filter for time period
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$time_filter_clause = '';
switch ($period) {
    case 'week':
        $time_filter_clause = "AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $time_filter_clause = "AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'all':
    default:
        // No filter for all time
        break;
}

try {
    // This query now directly uses the total_score from the users table for ranking
    // and calculates accuracy based on predictions made.
    $query = "
        SELECT
            u.user_id,
            u.user_name,
            u.total_score,
            COUNT(p.prediction_id) AS total_predictions,
            SUM(p.manual_accuracy) AS total_accuracy_points,
            COUNT(DISTINCT l.like_id) AS total_likes
        FROM users u
        LEFT JOIN predictions p ON u.user_id = p.user_id {$time_filter_clause}
        LEFT JOIN likes l ON p.prediction_id = l.like_id
        GROUP BY u.user_id, u.user_name, u.total_score
        ORDER BY u.total_score DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $leaderboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Find the current user's stats
    $currentUserStats = null;
    $rankCounter = 0;
    foreach ($leaderboard_data as $index => $row) {
        $rankCounter++;

        // Ensure values are not null
        $row['total_predictions'] = $row['total_predictions'] ?? 0;
        $row['total_accuracy_points'] = $row['total_accuracy_points'] ?? 0;
        $row['total_score'] = $row['total_score'] ?? 0;
        $row['total_likes'] = $row['total_likes'] ?? 0;

        // Calculate accuracy and add the rank to the row first
        $row['accuracy'] = ($row['total_predictions'] > 0) ? round(($row['total_accuracy_points'] / $row['total_predictions']), 2) : 0;
        $row['rank'] = $rankCounter;
        
        // Now, check if this is the current user. If so, assign the *complete* row to currentUserStats.
        if (isset($_SESSION['user_id']) && $row['user_id'] === $_SESSION['user_id']) {
            $currentUserStats = $row;
        }

        // Update the leaderboard data array
        $leaderboard_data[$index] = $row;
    }

} catch (PDOException $e) {
    error_log("Error fetching leaderboard data: " . $e->getMessage());
    $leaderboard_data = [];
    $currentUserStats = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - StoryPulse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb; /* Light gray background */
            color: #1f2937; /* Dark gray text */
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
                <a href="stories.php" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors duration-200">Stories</a>
                <a href="leaderboard.php" class="text-indigo-600 font-medium transition-colors duration-200">Leaderboard</a>
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
        <div class="text-center px-4 py-8 md:py-16">
            <h2 class="text-4xl md:text-5xl font-extrabold text-gray-800">Leaderboard</h2>
            <p class="mt-4 text-gray-500 max-w-xl mx-auto">Top predictors ranked by score, based on correct predictions and bonus rewards.</p>
        </div>

        <!-- Filter Buttons -->
        <div class="flex justify-center gap-4 mb-8 flex-wrap">
            <a href="leaderboard.php?period=all" class="px-6 py-2 rounded-full font-semibold transition-colors duration-200 <?php echo ($period === 'all' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'); ?>">All Time</a>
            <a href="leaderboard.php?period=month" class="px-6 py-2 rounded-full font-semibold transition-colors duration-200 <?php echo ($period === 'month' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'); ?>">This Month</a>
            <a href="leaderboard.php?period=week" class="px-6 py-2 rounded-full font-semibold transition-colors duration-200 <?php echo ($period === 'week' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'); ?>">This Week</a>
        </div>
        
        <!-- Top 3 Ranks -->
        <div class="max-w-4xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php
                // Display top 3
                $topRanks = [1 => 'first', 2 => 'second', 3 => 'third'];
                for ($i = 0; $i < min(count($leaderboard_data), 3); $i++) {
                    $ranker = $leaderboard_data[$i];
                    $rankClass = $topRanks[$i + 1];
                    $score = number_format($ranker['total_score']);
                    $accuracy = $ranker['accuracy'];
                    $predictions = $ranker['total_predictions'];
                    $likes = $ranker['total_likes'];
                    $initials = getInitials(htmlspecialchars($ranker['user_name']));
                    
                    echo "
                    <div class='flex flex-col items-center p-6 bg-white rounded-2xl shadow-lg border-2 border-transparent transition-all duration-300 hover:shadow-xl'>
                        <div class='w-16 h-16 rounded-full font-bold text-lg text-white flex items-center justify-center mb-4 rank-n {$rankClass}'>{$ranker['rank']}</div>
                        <div class='w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 font-bold flex items-center justify-center text-sm mb-2'>{$initials}</div>
                        <div class='text-center'>
                            <strong class='block text-lg font-bold text-gray-800'>".htmlspecialchars($ranker['user_name'])."</strong>
                            <span class='text-sm text-gray-500'>{$predictions} predictions</span>
                        </div>
                        <div class='flex gap-4 mt-4 w-full justify-center'>
                            <span class='block bg-gray-100 text-gray-700 text-xs font-semibold p-2 rounded-lg'>ACCURACY<br>{$accuracy}%</span>
                            <span class='block bg-gray-100 text-gray-700 text-xs font-semibold p-2 rounded-lg'>SCORE<br>{$score}</span>
                            <span class='block bg-gray-100 text-gray-700 text-xs font-semibold p-2 rounded-lg'>LIKES<br>{$likes}</span>
                        </div>
                    </div>";
                }
                ?>
            </div>
        </div>

        <!-- Other Ranks Table -->
        <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-lg overflow-hidden mt-8">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Predictions</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Likes</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Accuracy</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    // Display ranks 4 onwards
                    $startRank = 4;
                    for ($i = $startRank - 1; $i < count($leaderboard_data); $i++) {
                        $ranker = $leaderboard_data[$i];
                        $score = number_format($ranker['total_score']);
                        $accuracy = $ranker['accuracy'];
                        $predictions = $ranker['total_predictions'];
                        $likes = $ranker['total_likes'];
                        $initials = getInitials(htmlspecialchars($ranker['user_name']));

                        echo "
                        <tr>
                            <td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$ranker['rank']}</td>
                            <td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>
                                <div class='flex items-center'>
                                    <div class='flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 font-bold flex items-center justify-center text-xs'>{$initials}</div>
                                    <div class='ml-4'>
                                        <div class='text-sm font-medium text-gray-900'>".htmlspecialchars($ranker['user_name'])."</div>
                                    </div>
                                </div>
                            </td>
                            <td class='px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500'>{$predictions}</td>
                            <td class='px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500'>{$likes}</td>
                            <td class='px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500'>{$accuracy}%</td>
                            <td class='px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500'>{$score}</td>
                        </tr>
                        ";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Your Stats Section -->
        <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-lg border-2 border-indigo-400 p-6 md:p-8 mt-8">
            <h3 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Your Statistics</h3>
            <?php if ($currentUserStats): ?>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-center">
                    <div class="p-4 bg-gray-100 rounded-xl shadow-inner">
                        <div class="text-sm font-semibold text-gray-500">Rank</div>
                        <div class="mt-1 text-2xl font-extrabold text-indigo-600"><?php echo htmlspecialchars($currentUserStats['rank']); ?></div>
                    </div>
                    <div class="p-4 bg-gray-100 rounded-xl shadow-inner">
                        <div class="text-sm font-semibold text-gray-500">Total Predictions</div>
                        <div class="mt-1 text-2xl font-extrabold text-indigo-600"><?php echo htmlspecialchars($currentUserStats['total_predictions']); ?></div>
                    </div>
                    <div class="p-4 bg-gray-100 rounded-xl shadow-inner">
                        <div class="text-sm font-semibold text-gray-500">Likes</div>
                        <div class="mt-1 text-2xl font-extrabold text-indigo-600"><?php echo htmlspecialchars($currentUserStats['total_likes']); ?></div>
                    </div>
                    <div class="p-4 bg-gray-100 rounded-xl shadow-inner">
                        <div class="text-sm font-semibold text-gray-500">Accuracy</div>
                        <div class="mt-1 text-2xl font-extrabold text-indigo-600"><?php echo htmlspecialchars($currentUserStats['accuracy']); ?>%</div>
                    </div>
                    <div class="p-4 bg-gray-100 rounded-xl shadow-inner">
                        <div class="text-sm font-semibold text-gray-500">Score</div>
                        <div class="mt-1 text-2xl font-extrabold text-indigo-600"><?php echo htmlspecialchars(number_format($currentUserStats['total_score'])); ?></div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-600">You have not made any predictions yet.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
