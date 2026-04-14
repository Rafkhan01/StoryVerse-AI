<?php
require_once 'db_connect.php';
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_story.php?status=error&message=No part specified for deletion.');
    exit;
}

$part_id = $_GET['id'];

try {
    $sql_delete_part = "DELETE FROM Story_Parts WHERE part_id = :part_id";
    $stmt_delete_part = $pdo->prepare($sql_delete_part);
    $stmt_delete_part->bindParam(':part_id', $part_id);
    $stmt_delete_part->execute();
    header('Location: manage_story.php?status=success&message=Story part deleted successfully.');
    exit;

} catch (PDOException $e) {
    error_log("Delete Error: " . $e->getMessage());
    header('Location: manage_story.php?status=error&message=Failed to delete story part: ' . $e->getMessage());
    exit;
}
$pdo = null;
?>
