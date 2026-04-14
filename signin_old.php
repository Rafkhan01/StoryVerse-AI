<?php
session_start(); 
require_once 'db_connect.php'; 
require_once 'functions.php';

$message = '';
$message_type = '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_name = trim($_POST['user_name']);
    $password = $_POST['password'];

    if (empty($user_name) || empty($password)) {
        $message = 'Both username and password are required.';
        $message_type = 'error';
    } else {
        if ($user_name === 'admin' && $password === 'admin') {
            $_SESSION['user_id'] = 'admin';
            $_SESSION['user_name'] = 'admin';
            $_SESSION['is_admin'] = true; // Flag to indicate an admin user

            $message = 'Admin login successful! Redirecting...';
            $message_type = 'success';
            header('Refresh: 1; URL=admin/admin_home.php');
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT user_id, user_name, password, first_name, last_name, email, total_score, user_type FROM users WHERE user_name = :user_name");
            $stmt->execute(['user_name' => $user_name]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['user_type'] = $user['user_type']; 
                $_SESSION['email'] = $user['email'];

                if ($user['user_type'] === 'author') {
                    $otp = rand(100000, 999999);
                    $_SESSION['pending_login_id'] = $user['user_id'];
                    $_SESSION['login_otp'] = $otp;

                    sendOtpEmail($user['email'], $otp);
                    header("Location: verify_otp.php?context=login");
                    exit();
                }
                else {
                
                    $_SESSION['user_id'] = $user['user_id']; 
                    $_SESSION['user_name'] = $user['user_name'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['total_score'] = $user['total_score'];
                    $_SESSION['user_type']=$user['user_type'];
                    $message = 'Login successful! Redirecting...';
                    $message_type = 'success';
                    // Redirect to index.php after a short delay
                    header('Refresh: 1; URL=index.php');
                    exit();
                }
                
            } else {
                $message = 'Invalid username or password.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage()); // Log the actual error
            $message = 'An error occurred during login. Please try again.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story Prediction - Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        h1 {
            color: #333;
            margin-bottom: 25px;
            font-size: 2em;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"],
        input[type="password"] {
            width: calc(100% - 24px); /* Adjust for padding */
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
            outline: none;
        }
        button {
            padding: 12px 25px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
            margin-top: 10px;
        }
        button:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .link-text {
            margin-top: 20px;
            font-size: 0.95em;
            color: #666;
        }
        .link-text a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .link-text a:hover {
            text-decoration: underline;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 500;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sign In to Story Prediction</h1>
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        <form action="signin.php" method="POST">
            <div class="form-group">
                <label for="user_name">Username:</label>
                <input type="text" id="user_name" name="user_name" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="link-text">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>
    </div>
</body>
</html>
