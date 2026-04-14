<?php
/**
 * Edit Story Part Page - Allows an admin to update a specific story part.
 *
 * This script fetches a single story part by its 'part_id' from the URL,
 * displays its current data in a form, and processes the form submission
 * to update the record in the 'Story_Parts' table.
 */

require_once 'db_connect.php';

// Check if a part ID is provided in the URL.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_story.php?status=error&message=No part specified.');
    exit;
}

$part_id = $_GET['id'];
$part = null;
$error_message = '';

// --- Handle Form Submission (Update Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_id_post = $_POST['part_id'];
    $content = $_POST['content'];
    $upload_date = $_POST['upload_date'];
    $prediction_deadline = $_POST['prediction_deadline'];

    // Basic validation to ensure all fields are filled.
    if (!empty($content) && !empty($upload_date) && !empty($prediction_deadline)) {
        try {
            // Prepare the UPDATE statement for the Story_Parts table.
            $sql_update_part = "
                UPDATE Story_Parts
                SET content = :content, upload_date = :upload_date, prediction_deadline = :prediction_deadline
                WHERE part_id = :part_id
            ";
            $stmt_update_part = $pdo->prepare($sql_update_part);
            $stmt_update_part->bindParam(':content', $content);
            $stmt_update_part->bindParam(':upload_date', $upload_date);
            $stmt_update_part->bindParam(':prediction_deadline', $prediction_deadline);
            $stmt_update_part->bindParam(':part_id', $part_id_post);
            $stmt_update_part->execute();

            // Redirect back to the manage page with a success message.
            header('Location: manage_story.php?status=success&message=Story part updated successfully.');
            exit;

        } catch (PDOException $e) {
            error_log("Update Error: " . $e->getMessage());
            $error_message = "Failed to update story part: " . $e->getMessage();
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// --- Fetch Story Part Data (Display Logic) ---
try {
    // Select the story part to be edited.
    $sql_select = "SELECT part_id, part_number, content, upload_date, prediction_deadline FROM Story_Parts WHERE part_id = :part_id";
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->bindParam(':part_id', $part_id);
    $stmt_select->execute();
    
    $part = $stmt_select->fetch(PDO::FETCH_ASSOC);

    // If the part is not found, redirect with an error.
    if (!$part) {
        header('Location: manage_story.php?status=error&message=Story part not found.');
        exit;
    }

} catch (PDOException $e) {
    error_log("Fetch Error: " . $e->getMessage());
    header('Location: manage_story.php?status=error&message=Failed to retrieve story part data.');
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
    <title>Edit Story Part</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
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
        input[type="text"], input[type="date"], input[type="datetime-local"], textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.2s;
        }
        textarea {
            height: 200px;
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
        <h1>Edit Story Part #<?php echo htmlspecialchars($part['part_number']); ?></h1>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="edit_story_part.php?id=<?php echo htmlspecialchars($part['part_id']); ?>" method="POST">
            <input type="hidden" name="part_id" value="<?php echo htmlspecialchars($part['part_id']); ?>">
            
            <div class="form-group">
                <label for="content">Content</label>
                <textarea id="content" name="content" required><?php echo htmlspecialchars($part['content']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="upload_date">Upload Date</label>
                <input type="date" id="upload_date" name="upload_date" value="<?php echo htmlspecialchars($part['upload_date']); ?>" required>
            </div>

            <div class="form-group">
                <label for="prediction_deadline">Prediction Deadline</label>
                <input type="datetime-local" id="prediction_deadline" name="prediction_deadline" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $part['prediction_deadline'])); ?>" required>
            </div>
            
            <button type="submit" class="submit-button">Update Story Part</button>
        </form>
    </div>
</body>
</html>
