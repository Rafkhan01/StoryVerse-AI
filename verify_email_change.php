<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email_change_otp'])) {
    header('Location: profile.php');
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = $_POST['otp'];
    
    if ($entered_otp == $_SESSION['email_change_otp']) {
        $user_id = $_SESSION['user_id'];
        $data = $_SESSION['email_change_data'];
        
        try {
            $pdo->beginTransaction();
            
            if ($data['new_password']) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, profile_picture = ?, 
                        bio = ?, website = ?, instagram = ?, password = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $data['first_name'], $data['last_name'], $data['email'], $data['profile_picture'],
                    $data['bio'], $data['website'], $data['instagram'], $data['new_password'],
                    $user_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, profile_picture = ?, 
                        bio = ?, website = ?, instagram = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $data['first_name'], $data['last_name'], $data['email'], $data['profile_picture'],
                    $data['bio'], $data['website'], $data['instagram'],
                    $user_id
                ]);
            }
            
            $pdo->commit();
            
            // Update session
            $_SESSION['first_name'] = $data['first_name'];
            $_SESSION['last_name'] = $data['last_name'];
            $_SESSION['email'] = $data['email'];
            
            unset($_SESSION['email_change_otp'], $_SESSION['email_change_data']);
            
            $_SESSION['profile_message'] = ['status' => 'success', 'message' => 'Profile updated successfully!'];
            header("Location: profile.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Email change error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    } else {
        $error = "Invalid verification code!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Email Change - Story Pulse</title>
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
        <h3>Verify Email Change</h3>
        <p>Enter the 6-digit OTP sent to your new email address.</p>
        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
            <button type="submit">Verify & Update</button>
        </form>
    </div>
</body>
</html>