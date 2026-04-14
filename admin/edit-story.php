<?php
/**
 * Edit Story Page - Allows an admin to update the main details of a story.
 *
 * This script fetches a single story by its 'story_id' from the URL,
 * displays its current data in a form, and processes the form submission
 * to update the record in the 'Stories' table.
 */

require_once 'db_connect.php';

// Check if a story ID is provided in the URL.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_stories.php?status=error&message=No story specified.');
    exit;
}

$story_id = $_GET['id'];
$story = null;
$error_message = '';

// --- Handle Form Submission (Update Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $story_id_post = $_POST['story_id'];
    $title = $_POST['title'];
    $created_by = $_POST['created_by'];
    $category = $_POST['category'];
    $cover_image_url = $_POST['cover_image_url'];
    $current_part_no = $_POST['current_part_no'];
    $total_parts = $_POST['total_parts'];

    // Basic validation to ensure required fields are not empty.
    if (!empty($title) && !empty($created_by) && !empty($category) && !empty($cover_image_url) && !empty($current_part_no) && !empty($total_parts)) {
        try {
            // Prepare the UPDATE statement for the Stories table.
            $sql_update_story = "
                UPDATE Stories
                SET title = :title, created_by = :created_by, category = :category, cover_image_url = :cover_image_url, current_part_no = :current_part_no, total_parts = :total_parts
                WHERE story_id = :story_id
            ";
            $stmt_update_story = $pdo->prepare($sql_update_story);
            $stmt_update_story->bindParam(':title', $title);
            $stmt_update_story->bindParam(':created_by', $created_by);
            $stmt_update_story->bindParam(':category', $category);
            $stmt_update_story->bindParam(':cover_image_url', $cover_image_url);
            $stmt_update_story->bindParam(':current_part_no', $current_part_no, PDO::PARAM_INT);
            $stmt_update_story->bindParam(':total_parts', $total_parts, PDO::PARAM_INT);
            $stmt_update_story->bindParam(':story_id', $story_id_post);
            $stmt_update_story->execute();

            // Redirect back to the manage page with a success message.
            header('Location: manage_stories.php?status=success&message=Story updated successfully.');
            exit;

        } catch (PDOException $e) {
            error_log("Update Error: " . $e->getMessage());
            $error_message = "Failed to update story: " . $e->getMessage();
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// --- Fetch Story Data (Display Logic) ---
try {
    // Select the story to be edited.
    $sql_select = "SELECT story_id, title, created_by, category, cover_image_url, description, current_part_no, total_parts FROM Stories WHERE story_id = :story_id";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->bindParam(':story_id', $story_id);
    $stmt_select->execute();
    
    $story = $stmt_select->fetch(PDO::FETCH_ASSOC);

    // If the story is not found, redirect with an error.
    if (!$story) {
        header('Location: manage_stories.php?status=error&message=Story not found.');
        exit;
    }

} catch (PDOException $e) {
    error_log("Fetch Error: " . $e->getMessage());
    header('Location: manage_stories.php?status=error&message=Failed to retrieve story data.');
    exit;
}

// Close the connection
$pdo = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Story</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            /*height: 100vh;*/
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.2s;
        }
        textarea {
            height: 150px;
            resize: vertical;
        }
        input:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .submit-button {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .submit-button:hover {
            background-color: #2980b9;
        }
        .error-message {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Story: <?php echo htmlspecialchars($story['title']); ?></h1>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="edit-story.php?id=<?php echo htmlspecialchars($story['story_id']); ?>" method="POST">
            <input type="hidden" name="story_id" value="<?php echo htmlspecialchars($story['story_id']); ?>">
            
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($story['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="created_by">Created By</label>
                <input type="text" id="created_by" name="created_by" value="<?php echo htmlspecialchars($story['created_by']); ?>" required>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($story['category']); ?>" required>
            </div>

            <div class="form-group">
                <label for="cover_image_url">Cover Image URL</label>
                <input type="text" id="cover_image_url" name="cover_image_url" value="<?php echo htmlspecialchars($story['cover_image_url']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($story['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="current_part_no">Current Part Number</label>
                <input type="number" id="current_part_no" name="current_part_no" value="<?php echo htmlspecialchars($story['current_part_no']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="total_parts">Total Parts</label>
                <input type="number" id="total_parts" name="total_parts" value="<?php echo htmlspecialchars($story['total_parts']); ?>" required>
            </div>
            
            <button type="submit" class="submit-button">Update Story</button>
        </form>
    </div>
</body>
</html>
