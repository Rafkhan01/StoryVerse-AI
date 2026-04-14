<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = $_POST['otp'];
    $context = $_GET['context'];

    if ($context == 'signup' && isset($_SESSION['temp_user']) && $entered_otp == $_SESSION['temp_user']['otp']) {
        // Save the new Author to the database
        $u = $_SESSION['temp_user'];
        try {
            $stmt = $pdo->prepare("INSERT INTO users (user_name, password, first_name, last_name, email, user_type, email_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$u['user_name'], $u['password'], $u['first_name'], $u['last_name'], $u['email'], 'author']);
            
            // Get the newly created user ID
            $new_user_id = $pdo->lastInsertId();
            
            // Set session variables for the new author
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['user_name'] = $u['user_name'];
            $_SESSION['first_name'] = $u['first_name'];
            $_SESSION['last_name'] = $u['last_name'];
            $_SESSION['email'] = $u['email'];
            $_SESSION['user_type'] = 'author';
            
            unset($_SESSION['temp_user']);
            header("Location: author/index.php"); 
            exit();
        } catch (PDOException $e) {
            error_log("OTP Verification Error (Signup): " . $e->getMessage());
            $error = "Registration failed. Please try again.";
        }
    } 
    elseif ($context == 'login' && isset($_SESSION['login_otp']) && $entered_otp == $_SESSION['login_otp']) {
        // Fetching
        try {
            $stmt = $pdo->prepare("SELECT user_id, user_name, first_name, last_name, email, total_score, user_type FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['pending_login_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // strng into session cookie
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['total_score'] = $user['total_score'];
                $_SESSION['user_type'] = $user['user_type'];

                unset($_SESSION['login_otp'], $_SESSION['pending_login_id']);
                header("Location: author/index.php"); 
                exit();
            } else {
                $error = "User not found!";
            }
        } catch (PDOException $e) {
            error_log("OTP Verification Error (Login): " . $e->getMessage());
            $error = "Login failed. Please try again.";
        }
    } else {
        $error = "Invalid verification code!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP - Story Pulse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
        }
        .box { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
            width: 100%;
            max-width: 400px;
            text-align: center; 
        }
        h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        p {
            color: #666;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        input { 
            width: calc(100% - 24px);
            padding: 12px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            letter-spacing: 5px;
            font-weight: 600;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
        }
        button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none; 
            padding: 12px; 
            width: 100%; 
            cursor: pointer; 
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .logo {
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="logo">📧</div>
        <h3>Email Verification</h3>
        <p>Enter the 6-digit OTP sent to your email.</p>
        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
            <button type="submit">Verify & Continue</button>
        </form>
    </div>
</body>
</html>