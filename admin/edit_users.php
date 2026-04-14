<?php
/**
 * Admin - Edit User Page
 *
 * This script allows an administrator to edit a user's details and their total bonus amount.
 * It now directly updates the 'users' table to avoid foreign key constraint issues.
 */

require_once 'db_connect.php';

// Check if a user ID is provided in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$user_id = $_GET['id'];
$user = null;
$error_message = null;
$success_message = null;

// --- Handle Form Submission (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_post = $_POST['user_id'];
    $user_name = $_POST['user_name'];
    $email = $_POST['email'];
    $bonus_amount = $_POST['bonus_amount'];

    if (!empty($user_name) && !empty($email) && is_numeric($bonus_amount)) {
        try {
            $pdo->beginTransaction();

            // The user has clarified that total_score is used for the bonus.
            // Update the user's basic information and the total_score column.
            $sql_update_user = "UPDATE users SET user_name = :user_name, email = :email, total_score = :bonus_amount WHERE user_id = :user_id";
            $stmt_update_user = $pdo->prepare($sql_update_user);
            $stmt_update_user->bindParam(':user_name', $user_name, PDO::PARAM_STR);
            $stmt_update_user->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt_update_user->bindParam(':bonus_amount', $bonus_amount, PDO::PARAM_INT);
            $stmt_update_user->bindParam(':user_id', $user_id_post, PDO::PARAM_INT);
            $stmt_update_user->execute();
            
            // Recalculating the score is no longer needed since the form is directly
            $sql_update_bns = "UPDATE bonus SET bonus_amount=:bonus_amount WHERE user_id = :user_id";
            $stmt_update_bns = $pdo->prepare($sql_update_bns);
            $stmt_update_bns->bindParam(':bonus_amount', $bonus_amount, PDO::PARAM_INT);
            $stmt_update_bns->bindParam(':user_id', $user_id_post, PDO::PARAM_INT);
            $stmt_update_bns->execute();
            // setting the total_score.
            
            $pdo->commit();
            $success_message = "User details and total bonus updated successfully!";
            header("Location:manage_users.php");

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
            error_log("Error updating user data: " . $e->getMessage());
        }
    } else {
        $error_message = "All fields are required and bonus amount must be a number.";
    }
}

// --- Fetch User Data for Display ---
try {
    $sql_fetch_user = "
        SELECT 
            u.user_id,
            u.user_name,
            u.email,
            u.total_score
        FROM users u
        WHERE u.user_id = :user_id
    ";
    $stmt_fetch_user = $pdo->prepare($sql_fetch_user);
    $stmt_fetch_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_fetch_user->execute();
    $user = $stmt_fetch_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error_message = "User not found.";
        // Fallback with a default value of 0 for total_score if user is not found
        $user = ['user_id' => '', 'user_name' => '', 'email' => '', 'total_score' => 0];
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Error fetching user data for editing: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #1f2937;
        }
        .container {
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            color: #4b5563;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background-color: #6366f1;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            background-color: #4f46e5;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .message-success {
            background-color: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .message-error {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit User</h1>
        <?php if ($error_message): ?>
            <p class="message-error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="message-success"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <form action="edit_users.php?id=<?php echo htmlspecialchars($user['user_id']); ?>" method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
            <div class="form-group">
                <label for="user_name">User Name</label>
                <input type="text" id="user_name" name="user_name" value="<?php echo htmlspecialchars($user['user_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="bonus_amount">Adjusted Total Score</label>
                <input type="number" id="bonus_amount" name="bonus_amount" value="<?php echo htmlspecialchars($user['total_score'] ?? 0); ?>" required>
            </div>
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
</body>
</html>
