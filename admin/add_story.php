<?php
session_start();
require_once 'db_connect.php'; // Your database connection

$message = '';
$message_type = '';
/*if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}
*/
$existing_stories = [];
try {
    $stmt = $pdo->query("SELECT story_id, title FROM stories ORDER BY title ASC");
    $existing_stories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching existing stories: " . $e->getMessage());
    $message = 'Error loading existing stories.';
    $message_type = 'error';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_story'])) {
    $title = trim($_POST['title']);
    $created_by = trim($_POST['created_by']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $cover_image_url = trim($_POST['cover_image_url']);
    $total_parts = (int)$_POST['total_parts'];
    $part_content = trim($_POST['part_content']);
    $upload_date = $_POST['upload_date'];
    $prediction_deadline = $_POST['prediction_deadline'];

    if (empty($title) || empty($created_by) || empty($description) || empty($part_content) || empty($upload_date) || empty($prediction_deadline)) {
        $message = 'All fields for new story are required.';
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO stories (title, created_by, category, description, cover_image_url, total_parts, current_part_no) VALUES (:title, :created_by, :category, :description, :cover_image_url, :total_parts, 1)");
            $stmt->execute([
                'title' => $title,
                'created_by' => $created_by,
                'category' => $category,
                'description' => $description,
                'cover_image_url' => $cover_image_url,
                'total_parts' => $total_parts
            ]);
            $story_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO story_parts (story_id, part_number, content, upload_date, prediction_deadline) VALUES (:story_id, :part_number, :content, :upload_date, :prediction_deadline)");
            $stmt->execute([
                'story_id' => $story_id,
                'part_number' => 1,
                'content' => $part_content,
                'upload_date' => $upload_date,
                'prediction_deadline' => $prediction_deadline
            ]);

            $pdo->commit();
            $message = 'New Story and Part 1 added successfully!';
            $message_type = 'success';

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add New Story Error: " . $e->getMessage());
            $message = 'An error occurred adding new story: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_part'])) {
    $story_id = (int)$_POST['existing_story_id'];
    $part_number = (int)$_POST['new_part_number'];
    $part_content = trim($_POST['new_part_content']);
    $upload_date = $_POST['new_upload_date'];
    $prediction_deadline = $_POST['new_prediction_deadline'];

    if (empty($story_id) || empty($part_number) || empty($part_content) || empty($upload_date) || empty($prediction_deadline)) {
        $message = 'All fields for new part are required.';
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM story_parts WHERE story_id = :story_id AND part_number = :part_number");
            $stmt->execute(['story_id' => $story_id, 'part_number' => $part_number]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'Part number ' . $part_number . ' already exists for this story. Please choose a different part number or edit the existing one.';
                $message_type = 'error';
                $pdo->rollBack();
            } else {
              
                $stmt = $pdo->prepare("INSERT INTO story_parts (story_id, part_number, content, upload_date, prediction_deadline) VALUES (:story_id, :part_number, :content, :upload_date, :prediction_deadline)");
                $stmt->execute([
                    'story_id' => $story_id,
                    'part_number' => $part_number,
                    'content' => $part_content,
                    'upload_date' => $upload_date,
                    'prediction_deadline' => $prediction_deadline
                ]);
                $stmt = $pdo->prepare("UPDATE stories SET current_part_no = GREATEST(current_part_no, :part_number) WHERE story_id = :story_id");
                $stmt->execute(['part_number' => $part_number, 'story_id' => $story_id]);

                $pdo->commit();
                $message = 'New Part ' . $part_number . ' added successfully to story ID ' . $story_id . '!';
                $message_type = 'success';
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add New Part Error: " . $e->getMessage());
            $message = 'An error occurred adding new part: ' . $e->getMessage();
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
    <title>Admin - Add Story/Part</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            /*display: flex;
            justify-content: center;
            align-items: center;*/
            min-height: 100vh;
            padding: 20px;
        }
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
            align-items: center; 
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
        .lc
        {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 900px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin:30px 0;
        }
        @media (min-width: 768px) {
            .container {
                grid-template-columns: 1fr 1fr;
            }
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
            font-size: 2.2em;
            grid-column: 1 / -1; 
        }
        h2 {
            font-size: 1.8em;
            color: #463fff;
            margin-bottom: 20px;
            text-align: center;
        }
        h3 {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 15px;
            text-align: center;
        }
        .form-section {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="datetime-local"],
        input[type="url"], 
        select,
        textarea {
            width: calc(100% - 24px); 
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input:focus,
        select:focus,
        textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
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
        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 500;
            text-align: center;
            grid-column: 1 / -1; 
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
        .back-link {
            text-align: center;
            margin-top: 20px;
            grid-column: 1 / -1; 
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
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
    <div class="lc">
    <div class="container">
        <h1>Admin Panel: Manage Stories</h1>
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add New Story</h2>
            <form action="admin_add_story.php" method="POST">
                <div class="form-group">
                    <label for="title">Story Title:</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <!-- Removed Status Field -->
                <div class="form-group">
                    <label for="created_by">Author Name:</label>
                    <input type="text" id="created_by" name="created_by" required>
                </div>
                <div class="form-group">
                    <label for="category">Category:</label>
                    <input type="text" id="category" name="category" value="Fantasy" required>
                </div>
                <div class="form-group">
                    <label for="description">Short Description (for cards):</label>
                    <textarea id="description" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="cover_image_url">Story Cover Image URL:</label>
                    <input type="url" id="cover_image_url" name="cover_image_url" placeholder="e.g., https://example.com/image.jpg">
                </div>
                <div class="form-group">
                    <label for="total_parts">Total Parts (initial estimate):</label>
                    <input type="number" id="total_parts" name="total_parts" min="1" value="1" required>
                </div>
                <hr class="my-6 border-gray-200">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Part 1 Details</h3>
                <div class="form-group">
                    <label for="part_content">Part 1 Content:</label>
                    <textarea id="part_content" name="part_content" required></textarea>
                </div>
                <div class="form-group">
                    <label for="upload_date">Upload Date:</label>
                    <input type="date" id="upload_date" name="upload_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="prediction_deadline">Prediction Deadline:</label>
                    <input type="datetime-local" id="prediction_deadline" name="prediction_deadline" value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
                </div>
                <button type="submit" name="add_new_story">Add New Story & Part 1</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Add New Part to Existing Story</h2>
            <form action="admin_add_story.php" method="POST">
                <div class="form-group">
                    <label for="existing_story_id">Select Story:</label>
                    <select id="existing_story_id" name="existing_story_id" required>
                        <option value="">-- Select a Story --</option>
                        <?php foreach ($existing_stories as $story): ?>
                            <option value="<?php echo htmlspecialchars($story['story_id']); ?>">
                                <?php echo htmlspecialchars($story['title']); ?> (ID: <?php echo htmlspecialchars($story['story_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_part_number">New Part Number:</label>
                    <input type="number" id="new_part_number" name="new_part_number" min="1" required>
                </div>
                <div class="form-group">
                    <label for="new_part_content">New Part Content:</label>
                    <textarea id="new_part_content" name="new_part_content" required></textarea>
                </div>
                <div class="form-group">
                    <label for="new_upload_date">Upload Date:</label>
                    <input type="date" id="new_upload_date" name="new_upload_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="new_prediction_deadline">Prediction Deadline:</label>
                    <input type="datetime-local" id="new_prediction_deadline" name="new_prediction_deadline" value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
                </div>
                <button type="submit" name="add_new_part">Add New Part</button>
            </form>
        </div>
        <div class="back-link">
            <a href="admin_home.php">Back to Home</a>
        </div>
    </div>
    </div>
</body>
</html>
