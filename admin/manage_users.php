<?php
require_once 'db_connect.php';
$users = [];
try {
    // COALESCE is used to ensure a 0 is returned if a user has no bonuses or predictions.
    $sql_users = "
        SELECT
            u.user_id,
            u.user_name,
            u.email,
            COUNT(p.prediction_id) AS total_predictions,
            COALESCE(SUM(b.bonus_amount), 0) AS total_bonus
        FROM Users AS u
        LEFT JOIN Predictions AS p ON u.user_id = p.user_id
        LEFT JOIN Bonus AS b ON p.prediction_id = b.prediction_id
        GROUP BY u.user_id, u.user_name, u.email
        ORDER BY u.user_id ASC;
    ";
    $stmt = $pdo->query($sql_users);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Query Error in manage-users.php: " . $e->getMessage());
}
$pdo = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <style>
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        /* Navigation Bar */
        nav {
            display: flex;
            width: 95%;
            /*background: #daf8ffff;*/
            background:white;
            padding: 15px 5%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            align-items: center;
            justify-content: space-between;
            margin:-20px;
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
        h1 {
            color: #333;
            text-align: center;
            margin: 30px 0;
        }
        .user-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .user-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }
        .user-details h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .user-details p {
            margin: 5px 0;
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .user-details .bonus, .user-details .predictions {
            font-weight: bold;
            font-size: 1.1em;
        }

        .user-details .bonus {
            color: #27ae60;
        }

        .user-details .predictions {
            color: #e67e22;
        }
        .card-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .action-button {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            color: #fff;
            text-align: center;
            transition: background-color 0.2s, transform 0.2s;
            flex: 1;
        }
        .edit-button {
            background-color: #3498db;
        }
        
        .edit-button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .delete-button {
            background-color: #e74c3c;
        }
        
        .delete-button:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        
        /* Message for when no users are found */
        .no-users-message {
            text-align: center;
            color: #7f8c8d;
            margin-top: 50px;
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
    <h1>Manage Users</h1>

    <div class="user-cards-container">
        
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="user-details">
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($user['user_id']); ?></p>
                        <h3><?php echo htmlspecialchars($user['user_name']); ?></h3>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="predictions"><strong>Total Predictions:</strong> <?php echo htmlspecialchars($user['total_predictions']); ?></p>
                        <p class="bonus"><strong>Total Bonus:</strong> $<?php echo htmlspecialchars(number_format($user['total_bonus'], 2)); ?></p>
                    </div>
                    <div class="card-actions">
                        <a href="edit_users.php?id=<?php echo htmlspecialchars($user['user_id']); ?>" class="action-button edit-button">Edit</a>
                        <a href="delete_user.php?id=<?php echo htmlspecialchars($user['user_id']); ?>" class="action-button delete-button">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-users-message">
                <p>No users found in the database.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
