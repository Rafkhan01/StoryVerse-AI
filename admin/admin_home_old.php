<?php
require_once 'db_connect.php'; // Make sure this sets up the $pdo PDO connection

try {
    // Recent Stories Added
    $stmtStories = $pdo->prepare("
        SELECT story_id, title, created_by, category, created_at 
        FROM Stories 
        ORDER BY story_id DESC 
        LIMIT 5
    ");
    $stmtStories->execute();
    $recentStories = $stmtStories->fetchAll(PDO::FETCH_ASSOC);

    // Recent Users Registered
    $stmtUsers = $pdo->prepare("
        SELECT user_id, user_name, email, created_at 
        FROM Users 
        ORDER BY user_id DESC 
        LIMIT 5
    ");
    $stmtUsers->execute();
    $recentUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Recent Predictions Made
    $stmtPredictions = $pdo->prepare("
        SELECT p.prediction_id, p.prediction_text, u.user_name, s.title, p.created_at 
        FROM Predictions p
        JOIN Users u ON p.user_id = u.user_id
        JOIN Stories s ON p.story_id = s.story_id
        ORDER BY p.prediction_id DESC 
        LIMIT 5
    ");
    $stmtPredictions->execute();
    $recentPredictions = $stmtPredictions->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit();
}
// Initialize variables to hold the fetched data.
$total_users = 0;
$total_stories = 0;
$active_predictions = 0;
$total_bonuses = 0.00; // Use a float for currency

try {
    // --- SQL Query 1: Get Total Users ---
    // The query is executed directly on the $pdo object.
    $sql_users = "SELECT COUNT(user_id) AS total_users FROM Users";
    $stmt_users = $pdo->query($sql_users);
    $result_users = $stmt_users->fetch(PDO::FETCH_ASSOC);
    if ($result_users) {
        $total_users = $result_users['total_users'];
    }

    // --- SQL Query 2: Get Total Stories ---
    $sql_stories = "SELECT COUNT(story_id) AS total_stories FROM stories";
    $stmt_stories = $pdo->query($sql_stories);
    $result_stories = $stmt_stories->fetch(PDO::FETCH_ASSOC);
    if ($result_stories) {
        $total_stories = $result_stories['total_stories'];
    }

    // --- SQL Query 3: Get Active Predictions ---
    $sql_predictions = "SELECT COUNT(prediction_id) AS active_predictions FROM predictions";
    $stmt_predictions = $pdo->query($sql_predictions);
    $result_predictions = $stmt_predictions->fetch(PDO::FETCH_ASSOC);
    if ($result_predictions) {
        $active_predictions = $result_predictions['active_predictions'];
    }

    // --- SQL Query 4: Get Total Bonuses Paid ---
    $sql_bonuses = "SELECT SUM(bonus_amount) AS total_bonuses FROM Bonus";
    $stmt_bonuses = $pdo->query($sql_bonuses);
    $result_bonuses = $stmt_bonuses->fetch(PDO::FETCH_ASSOC);
    // Check for a non-null result and set the value.
    if ($result_bonuses && $result_bonuses['total_bonuses'] !== null) {
        $total_bonuses = $result_bonuses['total_bonuses'];
    }

} catch (PDOException $e) {
    // Catch any PDO exceptions from the queries themselves.
    // This is good practice to prevent the entire page from failing silently.
    error_log("Query Error in admin_dashboard.php: " . $e->getMessage());
}

// Close the database connection by setting the object to null.
$pdo = null;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Home</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; padding:20px; }
        h2 { margin-top: 40px; }
        
        table { border-collapse: collapse; width: 100%; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #2c3e50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        /* Navigation Bar */
        nav {
            display: flex;
            width: 93%;
            /*background: #daf8ffff;*/
            background:white;
            padding: 15px 5%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            align-items: center;
            justify-content: space-between;
            margin:-25px -20px 25px -20px;
        }

        .navtitle {
            color: #463fff;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .navcta {
            display: flex;
            gap: 15px;
            align-items: center; /* Align items vertically */
        }

        .navlinks {
            border: none;
            background: transparent;
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 600;
        }
        nav a {
            text-decoration: none;
            color: #ff8a7f;
            transition: color 0.3s ease;
        }

        .navlinks:hover {
            background-color: #c0d1ff;
            border-radius: 5px;
        }

        .navlinks:hover a {
            color: #463fff;
        }
        /* Main container for the dashboard cards */
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        /* Styling for each card */
        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: #333;
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        /* Hover effect for the cards */
        .card:hover {
            transform: translateY(-5px);
        }
        
        /* Header section of the card */
        .card-header {
            display: flex;
            align-items: center;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        /* Icon inside the card header */
        .card-header .icon {
            margin-right: 10px;
            font-size: 1.5em;
        }
        
        /* Large value display */
        .card-value {
            font-size: 2.5em;
            font-weight: bold;
        }
        
        /* Description text below the value */
        .card-description {
            color: #777;
        }
    </style>
</head>
<body>
    <nav>
        <h3 class="navtitle">Story Pulse</h3>
        <div class="navcta">
            <button class="navlinks"><a href="admin_home.php">Home</a></button>
            <button class="navlinks"><a href="manage_users.php">Users</a></button>
            <button class="navlinks"><a href="manage_stories.php">Manage story</a></button>
            <!-- <button class="navlinks"><a href="add_story.php">Add story </a></button> -->
            <button class="navlinks"><a href="admin_bonus_supply.php">Bonus supply </a></button>
            
            <div class="navlinks">
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
     <h1>Admin Dashboard (Summary Panel)</h1>

    <div class="dashboard-container">
        
        <!-- Total Users Card -->
        <a href="manage_users.php" class="card">
            <div class="card-header">
                <span class="icon">👥</span> Total Users
            </div>
            <div class="card-value">
                <?php echo htmlspecialchars($total_users); ?>
            </div>
            <div class="card-description">
                Number of registered users
            </div>
        </a>

        <!-- Total Stories Card -->
        <a href="manage_stories.php" class="card">
            <div class="card-header">
                <span class="icon">📖</span> Total Stories
            </div>
            <div class="card-value">
                <?php echo htmlspecialchars($total_stories); ?>
            </div>
            <div class="card-description">
                Total number of stories
            </div>
        </a>

        <!-- Active Predictions Card -->
        <a href="manage_predictions.php" class="card">
            <div class="card-header">
                <span class="icon">🧠</span> Active Predictions
            </div>
            <div class="card-value">
                <?php echo htmlspecialchars($active_predictions); ?>
            </div>
            <div class="card-description">
                Total ongoing predictions
            </div>
        </a>

        <!-- Total Bonuses Paid Card -->
        <a href="admin_bonus_supply.php" class="card">
            <div class="card-header">
                <span class="icon">💰</span> Total Bonuses Paid
            </div>
            <div class="card-value">
                $<?php echo htmlspecialchars(number_format($total_bonuses, 2)); ?>
            </div>
            <div class="card-description">
                Sum of all bonuses distributed
            </div>
        </a>

    </div>
    <h1>Recent activities</h1>
    
    <h2>Recent Stories</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Created By</th>
            <th>Category</th>
            
        </tr>
        <?php foreach ($recentStories as $story): ?>
            <tr>
                <td><?= htmlspecialchars($story['story_id']) ?></td>
                <td><?= htmlspecialchars($story['title']) ?></td>
                <td><?= htmlspecialchars($story['created_by']) ?></td>
                <td><?= htmlspecialchars($story['category']) ?></td>
                
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Recent Users</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Joined</th>
        </tr>
        <?php foreach ($recentUsers as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['user_id']) ?></td>
                <td><?= htmlspecialchars($user['user_name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Recent Predictions</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Prediction</th>
            <th>User</th>
            <th>Story</th>
            <th>Date</th>
        </tr>
        <?php foreach ($recentPredictions as $prediction): ?>
            <tr>
                <td><?= htmlspecialchars($prediction['prediction_id']) ?></td>
                <td><?= htmlspecialchars($prediction['prediction_text']) ?></td>
                <td><?= htmlspecialchars($prediction['user_name']) ?></td>
                <td><?= htmlspecialchars($prediction['title']) ?></td>
                <td><?= htmlspecialchars($prediction['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>
