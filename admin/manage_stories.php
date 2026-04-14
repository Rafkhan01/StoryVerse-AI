<?php
require_once 'db_connect.php';
// Initialize a variable to hold story data.
$stories = [];

try {
    $sql_stories = "SELECT story_id, title, created_by, category, cover_image_url, current_part_no, description, total_parts FROM Stories";
    $stmt_stories = $pdo->query($sql_stories);
    $stories = $stmt_stories->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stories as &$story) {
         $sql_parts = "
            SELECT
                sp.part_id,
                sp.part_number,
                sp.content,
                sp.upload_date,
                sp.prediction_deadline,
                COUNT(p.prediction_id) AS total_predictions
            FROM Story_Parts AS sp
            LEFT JOIN Predictions AS p ON sp.story_id = p.story_id AND sp.part_number = p.prediction_part_no
            WHERE sp.story_id = :story_id
            GROUP BY sp.part_id, sp.part_number, sp.content, sp.upload_date, sp.prediction_deadline
            ORDER BY sp.part_number ASC;
        ";
        $stmt_parts = $pdo->prepare($sql_parts);
        $stmt_parts->bindParam(':story_id', $story['story_id']);
        $stmt_parts->execute();
        $story['parts'] = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
       error_log("Query Error in manage-stories.php: " . $e->getMessage());
}
$pdo = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stories</title>
    <style>
        /* General body and heading styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
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
            align-items: center; /* Align items vertically */
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
            color: #463fffff;
        }
        h1 {
            color: #333;
            text-align: center;
            margin: 30px 0;
        }

        /* Container for the story cards */
        .story-container {
            display: flex;
            flex-direction: column;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Main story card styling */
        .story-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            border-left: 5px solid #3498db;
        }

        .story-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .story-cover {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 20px;
        }

        .story-details h2 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .story-details p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .story-details p strong {
            color: #555;
        }
        
        /* Action buttons for the main story */
        .story-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        /* Container for the story parts */
        .story-parts-container {
            margin-top: 30px;
            border-top: 1px dashed #ccc;
            padding-top: 20px;
        }

        .story-parts-container h3 {
            color: #555;
            margin-top: 0;
        }
        
        /* Sub-card styling for story parts */
        .part-card {
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 3px solid #2ecc71;
            transition: box-shadow 0.2s;
        }

        .part-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .part-card h4 {
            margin: 0 0 10px 0;
            color: #27ae60;
        }
        
        .part-card .content-snippet {
            font-style: italic;
            color: #999;
            margin-bottom: 10px;
        }
        
        .part-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Base button styles */
        .action-button {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            color: #fff;
            text-align: center;
            transition: background-color 0.2s, transform 0.2s;
            flex: 1;
        }

        .edit-button {
            background-color: #3498db;
        }
        
        .edit-button:hover {
            background-color: #2980b9;
        }

        .delete-button {
            background-color: #e74c3c;
        }
        
        .delete-button:hover {
            background-color: #c0392b;
        }
        
        .no-stories-message {
            text-align: center;
            color: #7f8c8d;
            margin-top: 50px;
        }
        .linkingcntr
        {
            text-decoration:none;
        }
        .linkingcntr h1
        {
            transition:0.3s ease-in;
            border-radius:25px;
            background-color: #463fff;
            color: #c0d1ff;
        }
        .linkingcntr h1:hover
        {
            
            border-radius:25px;
            color: #463fff;
            background-color: #c0d1ff;
        }
    </style>
</head>
<body>
    <nav>
        <h3 class="navtitle">Story Pulse</h3>
        <div class="navcta">
            <button class="navlinks"><a href="admin_home.php">Home</a></button>
            <button class="navlinks"><a href="manage_users.php">Users</a></button>
            <button class="navlinks"><a href="add_story.php">Add story</a></button>
            <!-- <button class="navlinks"><a href="add_story.php">Add story </a></button> -->
            <button class="navlinks"><a href="admin_bonus_supply.php">Bonus supply </a></button>
            
            <div class="navlinks">
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    <!--  <a href="add_story.php" class="linkingcntr"><h1>Add new story or part</h1></a>-->
  <h1>Manage Stories</h1>

    <div class="story-container">
        
        <?php if (!empty($stories)): ?>
            <?php foreach ($stories as $story): ?>
                <div class="story-card">
                    <div class="story-header">
                        <img src="<?php echo htmlspecialchars($story['cover_image_url']); ?>" alt="Cover Image for <?php echo htmlspecialchars($story['title']); ?>" class="story-cover">
                        <div class="story-details">
                            <h2><?php echo htmlspecialchars($story['title']); ?></h2>
                            <p><strong>Story ID:</strong> <?php echo htmlspecialchars($story['story_id']); ?></p>
                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($story['created_by']); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($story['category']); ?></p>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($story['description']); ?></p>
                            <p><strong>Parts:</strong> <?php echo htmlspecialchars($story['current_part_no']); ?> / <?php echo htmlspecialchars($story['total_parts']); ?></p>
                        </div>
                    </div>
                    
                    <div class="story-actions">
                        <a href="edit-story.php?id=<?php echo htmlspecialchars($story['story_id']); ?>" class="action-button edit-button">Edit Story</a>
                        <a href="delete-story.php?id=<?php echo htmlspecialchars($story['story_id']); ?>" class="action-button delete-button">Delete Story</a>
                    </div>

                    <div class="story-parts-container">
                        <h3>Story Parts</h3>
                        <?php if (!empty($story['parts'])): ?>
                            <?php foreach ($story['parts'] as $part): ?>
                                <div class="part-card">
                                    <h4>Part #<?php echo htmlspecialchars($part['part_number']); ?></h4>
                                    <p><strong>Part ID:</strong> <?php echo htmlspecialchars($part['part_id']); ?></p>
                                    <p><strong>Upload Date:</strong> <?php echo htmlspecialchars($part['upload_date']); ?></p>
                                    <p><strong>Prediction Deadline:</strong> <?php echo htmlspecialchars($part['prediction_deadline']); ?></p>
                                    <p><strong>Total Predictions:</strong> <?php echo htmlspecialchars($part['total_predictions']); ?></p>
                                    <p class="content-snippet">"<?php echo htmlspecialchars(substr($part['content'], 0, 150)); ?>..."</p>
                                    <div class="part-actions">
                                        <a href="edit-story-part.php?id=<?php echo htmlspecialchars($part['part_id']); ?>" class="action-button edit-button">Edit Part</a>
                                        <a href="delete-story-part.php?id=<?php echo htmlspecialchars($part['part_id']); ?>" class="action-button delete-button">Delete Part</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No parts found for this story.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-stories-message">
                <p>No stories found in the database.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
