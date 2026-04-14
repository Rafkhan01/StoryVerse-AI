<?php
require_once 'db_connect.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_stories.php?status=error&message=No story specified for deletion.');
    exit;
}

$story_id = $_GET['id'];

try {
    $pdo->beginTransaction();
    $sql_delete_parts = "DELETE FROM Story_Parts WHERE story_id = :story_id";
    $stmt_delete_parts = $pdo->prepare($sql_delete_parts);
    $stmt_delete_parts->bindParam(':story_id', $story_id);
    $stmt_delete_parts->execute();
    $sql_delete_story = "DELETE FROM Stories WHERE story_id = :story_id";
    $stmt_delete_story = $pdo->prepare($sql_delete_story);
    $stmt_delete_story->bindParam(':story_id', $story_id);
    $stmt_delete_story->execute();
    $pdo->commit();
    header('Location: manage_stories.php?status=success&message=Story and its parts deleted successfully.');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete Error: " . $e->getMessage());
    header('Location: manage_stories.php?status=error&message=Failed to delete story: ' . $e->getMessage());
    exit;
}
$pdo = null;
?>
