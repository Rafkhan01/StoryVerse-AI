<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message = '';
$message_type = '';

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: logout.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $message = 'Error loading profile.';
    $message_type = 'error';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $profile_picture = trim($_POST['profile_picture']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Author-specific fields
    $bio = $user_type === 'author' ? trim($_POST['bio']) : null;
    $website = $user_type === 'author' ? trim($_POST['website']) : null;
    $instagram = $user_type === 'author' ? trim($_POST['instagram']) : null;
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $message = 'First name, last name, and email are required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $message_type = 'error';
    } else {
        $email_changed = ($email !== $user['email']);
        
        // Check if email is already taken by another user
        if ($email_changed) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'Email is already taken by another user.';
                $message_type = 'error';
            }
        }
        
        // Password validation if provided
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $message = 'Passwords do not match.';
                $message_type = 'error';
            } elseif (strlen($new_password) < 8) {
                $message = 'Password must be at least 8 characters long.';
                $message_type = 'error';
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $message = 'Password must contain at least one uppercase letter.';
                $message_type = 'error';
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $message = 'Password must contain at least one lowercase letter.';
                $message_type = 'error';
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $message = 'Password must contain at least one number.';
                $message_type = 'error';
            } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
                $message = 'Password must contain at least one special character.';
                $message_type = 'error';
            }
        }
        
        // If no errors, proceed with update
        if (empty($message)) {
            try {
                // If email changed and user is author, send OTP
                if ($email_changed && $user_type === 'author') {
                    $otp = rand(100000, 999999);
                    $_SESSION['email_change_otp'] = $otp;
                    $_SESSION['email_change_data'] = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'profile_picture' => $profile_picture,
                        'bio' => $bio,
                        'website' => $website,
                        'instagram' => $instagram,
                        'new_password' => !empty($new_password) ? password_hash($new_password, PASSWORD_DEFAULT) : null
                    ];
                    
                    if (sendOtpEmail($email, $otp)) {
                        header("Location: verify_email_change.php");
                        exit();
                    } else {
                        $message = 'Failed to send OTP. Please check your email.';
                        $message_type = 'error';
                    }
                } else {
                    // Direct update for readers or if email didn't change
                    $pdo->beginTransaction();
                    
                    if ($user_type === 'author') {
                        if (!empty($new_password)) {
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET first_name = ?, last_name = ?, email = ?, profile_picture = ?, 
                                    bio = ?, website = ?, instagram = ?, password = ?
                                WHERE user_id = ?
                            ");
                            $stmt->execute([
                                $first_name, $last_name, $email, $profile_picture,
                                $bio, $website, $instagram, password_hash($new_password, PASSWORD_DEFAULT),
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
                                $first_name, $last_name, $email, $profile_picture,
                                $bio, $website, $instagram, $user_id
                            ]);
                        }
                    } else {
                        // Reader update
                        if (!empty($new_password)) {
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET first_name = ?, last_name = ?, email = ?, profile_picture = ?, password = ?
                                WHERE user_id = ?
                            ");
                            $stmt->execute([
                                $first_name, $last_name, $email, $profile_picture,
                                password_hash($new_password, PASSWORD_DEFAULT), $user_id
                            ]);
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET first_name = ?, last_name = ?, email = ?, profile_picture = ?
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$first_name, $last_name, $email, $profile_picture, $user_id]);
                        }
                    }
                    
                    $pdo->commit();
                    
                    // Update session data
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $message = 'Profile updated successfully!';
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Profile update error: " . $e->getMessage());
                $message = 'An error occurred. Please try again.';
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
    <title>Edit Profile - Story Pulse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-book-open text-3xl text-purple-600"></i>
                    <span class="ml-2 text-2xl font-bold gradient-bg bg-clip-text text-transparent">Story Pulse</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?php echo $user_type === 'author' ? 'author/index.php' : 'index.php'; ?>" 
                       class="text-gray-700 hover:text-purple-600 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="gradient-bg px-8 py-6 text-white">
                <h1 class="text-3xl font-bold">Edit Profile</h1>
                <p class="mt-2 text-white text-opacity-90">Update your personal information</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="mx-8 mt-6">
                <div class="p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-3 text-xl"></i>
                        <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="p-8 space-y-6">
                <!-- Profile Picture -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                    <div class="flex items-center space-x-6">
                        <img id="preview" 
                             src="<?php echo htmlspecialchars($user['profile_picture'] ?: "https://ui-avatars.com/api/?name=" . urlencode($user['first_name'] . ' ' . $user['last_name']) . "&background=667eea&color=fff&size=200"); ?>" 
                             alt="Profile" 
                             class="w-24 h-24 rounded-full border-4 border-purple-500 object-cover">
                        <div class="flex-1">
                            <input type="url" name="profile_picture" id="profile_picture"
                                   value="<?php echo htmlspecialchars($user['profile_picture'] ?? ''); ?>"
                                   placeholder="https://example.com/your-image.jpg"
                                   onchange="updatePreview(this.value)"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-2">Enter image URL or leave blank for default avatar</p>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                        <input type="text" name="first_name" required
                               value="<?php echo htmlspecialchars($user['first_name']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                        <input type="text" name="last_name" required
                               value="<?php echo htmlspecialchars($user['last_name']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($user['user_name']); ?>"
                               readonly
                               class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed">
                        <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" required
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <?php if ($user_type === 'author'): ?>
                        <p class="text-xs text-gray-500 mt-1">Email change requires OTP verification</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Author-specific fields -->
                <?php if ($user_type === 'author'): ?>
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Author Information</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                            <textarea name="bio" rows="4" maxlength="500"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                      placeholder="Tell readers about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Maximum 500 characters</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Website</label>
                                <input type="url" name="website"
                                       value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>"
                                       placeholder="https://yourwebsite.com"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Instagram</label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        @
                                    </span>
                                    <input type="text" name="instagram"
                                           value="<?php echo htmlspecialchars($user['instagram'] ?? ''); ?>"
                                           placeholder="username"
                                           class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Password Change -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Change Password (Optional)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" id="new_password"
                                   onkeyup="checkPasswordStrength(this.value)"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <div id="password-strength" class="password-strength mt-2 bg-gray-200"></div>
                            <p class="text-xs text-gray-500 mt-2">Leave blank to keep current password</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm font-medium text-blue-900 mb-2">Password Requirements:</p>
                        <ul class="text-xs text-blue-700 space-y-1">
                            <li id="req-length"><i class="fas fa-circle text-xs mr-2"></i>At least 8 characters</li>
                            <li id="req-upper"><i class="fas fa-circle text-xs mr-2"></i>One uppercase letter</li>
                            <li id="req-lower"><i class="fas fa-circle text-xs mr-2"></i>One lowercase letter</li>
                            <li id="req-number"><i class="fas fa-circle text-xs mr-2"></i>One number</li>
                            <li id="req-special"><i class="fas fa-circle text-xs mr-2"></i>One special character</li>
                        </ul>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="border-t pt-6 bg-gray-50 -mx-8 px-8 py-4">
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <span><i class="fas fa-user-tag mr-2"></i>Account Type: <strong><?php echo ucfirst($user_type); ?></strong></span>
                        <span><i class="fas fa-calendar-alt mr-2"></i>Member Since: <strong><?php echo date('M Y', strtotime($user['created_at'])); ?></strong></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-4">
                    <a href="<?php echo $user_type === 'author' ? 'index.php' : '../index.php'; ?>" 
                       class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-3 gradient-bg text-white rounded-lg font-semibold hover:opacity-90 transition">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updatePreview(url) {
            const preview = document.getElementById('preview');
            if (url.trim() === '') {
                preview.src = "https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>&background=667eea&color=fff&size=200";
            } else {
                preview.src = url;
                preview.onerror = function() {
                    this.src = "https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>&background=667eea&color=fff&size=200";
                };
            }
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength');
            const requirements = {
                'req-length': password.length >= 8,
                'req-upper': /[A-Z]/.test(password),
                'req-lower': /[a-z]/.test(password),
                'req-number': /[0-9]/.test(password),
                'req-special': /[^A-Za-z0-9]/.test(password)
            };

            // Update requirement indicators
            for (const [id, met] of Object.entries(requirements)) {
                const element = document.getElementById(id);
                if (met) {
                    element.classList.add('text-green-700');
                    element.classList.remove('text-blue-700');
                    element.querySelector('i').classList.replace('fa-circle', 'fa-check-circle');
                } else {
                    element.classList.add('text-blue-700');
                    element.classList.remove('text-green-700');
                    element.querySelector('i').classList.replace('fa-check-circle', 'fa-circle');
                }
            }

            // Calculate strength
            const metCount = Object.values(requirements).filter(Boolean).length;
            
            if (password === '') {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength bg-gray-200';
            } else if (metCount <= 2) {
                strengthBar.style.width = '33%';
                strengthBar.className = 'password-strength bg-red-500';
            } else if (metCount <= 4) {
                strengthBar.style.width = '66%';
                strengthBar.className = 'password-strength bg-yellow-500';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.className = 'password-strength bg-green-500';
            }
        }
    </script>
</body>
</html>