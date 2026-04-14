<?php
session_start(); // Start the session
require_once 'db_connect.php'; // Include the database connection

$message = '';
$message_type = '';

// Check for and handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the login form was submitted
    if (isset($_POST['login_submit'])) {
        $user_name = trim($_POST['user_name']);
        $password = $_POST['password'];

        if (empty($user_name) || empty($password)) {
            $message = 'Both username and password are required.';
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, user_name, password, first_name, last_name, email, total_score FROM users WHERE user_name = :user_name");
                $stmt->execute(['user_name' => $user_name]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Login successful, store user data in session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['user_name'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['total_score'] = $user['total_score'];

                    // Redirect to a dashboard page
                    header('Location: index.php');
                    exit();
                } else {
                    $message = 'Invalid username or password.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    // Check if the signup form was submitted
    else if (isset($_POST['signup_submit'])) {
        $user_name = trim($_POST['user_name']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
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
                    $message = 'Username or email already exists. Please use a different one.';
                    $message_type = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (user_name, password, first_name, last_name, email) VALUES (:user_name, :password, :first_name, :last_name, :email)");
                    $stmt->execute([
                        'user_name' => $user_name,
                        'password' => $hashedPassword,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email
                    ]);

                    $message = 'Registration successful! Please sign in with your new account.';
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story Prediction - Login Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Body Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Subtle animated background */
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .background-animation::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: backgroundMove 20s linear infinite;
        }

        @keyframes backgroundMove {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(50px, 50px) rotate(360deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 0;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 10;
            overflow: hidden;
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 400;
        }

        .form-container {
            padding: 40px;
            position: relative;
            height: 520px; /* Adjust height for the taller signup form */
            overflow: hidden;
        }

        .form-toggle {
            display: flex;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 30px;
            position: relative;
        }

        .toggle-btn {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #6c757d;
            font-weight: 500;
            font-size: 14px;
            position: relative;
            z-index: 2;
        }

        .toggle-btn.active {
            color: #495057;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Form transition logic */
        .form {
            position: absolute;
            top: 105px;
            left: 40px;
            right: 40px;
            opacity: 0;
            transform: scale(0.95);
            visibility: hidden;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        .form.active {
            opacity: 1;
            transform: scale(1);
            visibility: visible;
        }

        /* Form elements */
        .form-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: #f8f9fa;
            color: #495057;
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
            background: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .form-group i {
            position: absolute;
            left: 18px;
            top: 63%;
            transform: translateY(-50%);
            color: #adb5bd;
            transition: color 0.3s ease;
        }

        .form-group input:focus + i {
            color: #667eea;
        }

        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #667eea;
            font-size: 14px;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #556ee0;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
            box-shadow: 0 10px 28px rgba(102, 126, 234, 0.5);
        }

        /* Notification styles */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            transform: translateX(120%);
            animation: slideInRight 0.3s ease-out forwards;
            z-index: 1000;
        }

        .notification.success { border-left: 5px solid #28a745; }
        .notification.error { border-left: 5px solid #dc3545; }

        .notification i {
            margin-right: 15px;
            font-size: 24px;
        }

        .notification.success i { color: #28a745; }
        .notification.error i { color: #dc3545; }

        @keyframes slideInRight {
            to { transform: translateX(0); }
        }
    </style>
</head>
<body>

    <!-- Animated Background -->
    <div class="background-animation"></div>

    <div class="login-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <h1>StoryPulse</h1>
            <p>Read, Predict, Win!</p>
        </div>
        <!-- End Header -->

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-toggle">
                <button id="loginToggle" class="toggle-btn active">Sign In</button>
                <button id="signupToggle" class="toggle-btn">Sign Up</button>
            </div>

            <!-- This hidden div will store the PHP message for JavaScript to read -->
            <div id="php-message-data" data-message="<?php echo htmlspecialchars($message); ?>" data-type="<?php echo htmlspecialchars($message_type); ?>"></div>

            <!-- Sign In Form -->
            <form id="loginForm" class="form active" action="auth_portal.php" method="POST">
                <input type="hidden" name="login_submit" value="1">
                <div class="form-group">
                    <label for="login-user_name">Username</label>
                    <input type="text" id="login-user_name" name="user_name" required>
                    <i class="fas fa-user"></i>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                    <i class="fas fa-lock"></i>
                </div>
                <div class="forgot-password">
                    <a href="#">Forgot Password?</a>
                </div>
                <button type="submit" class="submit-btn">Sign In</button>
            </form>

            <!-- Sign Up Form -->
            <form id="signupForm" class="form" action="auth_portal.php" method="POST">
                <input type="hidden" name="signup_submit" value="1">
                <div class="form-group">
                    <label for="signup-first_name">First Name</label>
                    <input type="text" id="signup-first_name" name="first_name" required>
                    <i class="fas fa-signature"></i>
                </div>
                <div class="form-group">
                    <label for="signup-last_name">Last Name</label>
                    <input type="text" id="signup-last_name" name="last_name" required>
                    <i class="fas fa-signature"></i>
                </div>
                <div class="form-group">
                    <label for="signup-email">Email</label>
                    <input type="email" id="signup-email" name="email" required>
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="form-group">
                    <label for="signup-user_name">Username</label>
                    <input type="text" id="signup-user_name" name="user_name" required>
                    <i class="fas fa-user"></i>
                </div>
                <div class="form-group">
                    <label for="signup-password">Password</label>
                    <input type="password" id="signup-password" name="password" required>
                    <i class="fas fa-lock"></i>
                </div>
                <button type="submit" class="submit-btn">Sign Up</button>
            </form>

        </div>
        <!-- End Form Container -->
    </div>

    <script>
        // Form switching logic
        const loginToggle = document.getElementById('loginToggle');
        const signupToggle = document.getElementById('signupToggle');
        const loginForm = document.getElementById('loginForm');
        const signupForm = document.getElementById('signupForm');

        function showLogin() {
            loginToggle.classList.add('active');
            signupToggle.classList.remove('active');
            loginForm.classList.add('active');
            signupForm.classList.remove('active');
        }

        function showSignup() {
            signupToggle.classList.add('active');
            loginToggle.classList.remove('active');
            signupForm.classList.add('active');
            loginForm.classList.remove('active');
        }

        loginToggle.addEventListener('click', showLogin);
        signupToggle.addEventListener('click', showSignup);

        // Show notification from PHP message
        function showNotification(message, type) {
            if (!message) return;
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideInRight 0.3s ease-out reverse';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Check for message on page load and display it
        document.addEventListener('DOMContentLoaded', () => {
            const messageData = document.getElementById('php-message-data');
            const message = messageData.dataset.message;
            const type = messageData.dataset.type;

            if (message) {
                // Determine which form to show based on the message type
                if (type === 'success' || message.includes('password')) {
                    showLogin();
                } else if (type === 'error') {
                    showSignup();
                }
                showNotification(message, type);
            }
        });
    </script>
</body>
</html>
