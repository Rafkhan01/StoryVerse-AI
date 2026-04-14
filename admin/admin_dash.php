<?php
/**
 * Admin Dashboard - Displays key metrics from the database.
 *
 * It is assumed that a file named 'db_connection.php' exists
 * and establishes a PDO connection object named $pdo.
 */

// Include the database connection file.
// The $pdo variable will be available after this line.
require_once 'db_connect.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        /* General body styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
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

    <h1>Admin Dashboard (Summary Panel)</h1>

    <div class="dashboard-container">
        
        <!-- Total Users Card -->
        <a href="/manage-users.php" class="card">
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
        <a href="/manage-stories.php" class="card">
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
        <a href="/manage-predictions.php" class="card">
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
        <a href="/bonus-reports.php" class="card">
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

</body>
</html>
