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
    $first_name = trim($_POST['first_name']);
    $last_name= trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; 
    
    
    if (empty($user_name) || empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $message = 'All fields are required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $message_type = 'error';
    } else {
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_name = :user_name OR email = :email");
            $stmt->execute(['user_name' => $user_name, 'email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'Username or Email already taken. Please choose a different one.';
                $message_type = 'error';
            } 
            $user_type = $_POST['user_type'];

            if ($user_type === 'author') {
                $otp = rand(100000, 999999);
                $_SESSION['temp_user'] = [
                    'user_name' => $user_name,
                    'password' => $hashedPassword,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'user_type' => 'author',
                    'otp' => $otp
                ];
                
                // Calling fnctns page using functioncalling
                if (sendOtpEmail($email, $otp)) {
                    header("Location: verify_otp.php?context=signup");
                    exit();
                }
                else {
                        $message = 'Failed to send OTP. Check your internet.';
                        $message_type = 'error';
                    }
            }
            else {
                
                $stmt = $pdo->prepare("INSERT INTO users (user_name, password, first_name, last_name, email) VALUES (:user_name, :password, :first_name, :last_name, :email)");
                $stmt->execute([
                    'user_name' => $user_name,
                    'password' => $hashedPassword,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email
                ]);

                $message = 'Registration successful! You can now sign in.';
                $message_type = 'success';
                
                header("Refresh: 2; URL=signin.php");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Signup Error: " . $e->getMessage()); // Log the actual error
            echo "Error: " . $e->getMessage();

            $message = 'An error occurred during registration. Please try again.';
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
    <title>Story Prediction - Sign Up</title>
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
        input[type="password"],
        input[type="email"] {
            width: calc(100% - 24px); /* Adjust for padding */
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }
        button {
            padding: 12px 25px;
            background-color: #007bff;
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
            background-color: #0056b3;
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
        <h1>Create Your Account</h1>
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        <form action="signup.php" method="POST">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="user_name">Username:</label>
                <input type="text" id="user_name" name="user_name" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Register as:</label>
                <input type="radio" id="reader" name="user_type" value="reader" checked>
                <label for="reader" style="display:inline; font-weight:400;">Reader</label>
                <input type="radio" id="author" name="user_type" value="author">
                <label for="author" style="display:inline; font-weight:400;">Author</label>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="link-text">
            Already have an account? <a href="signin.php">Sign In</a>
        </div>
    </div>
</body>
</html>
