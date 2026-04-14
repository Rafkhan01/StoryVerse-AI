<?php
require_once 'db_connect.php';
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_users.php?status=error&message=No user ID provided.');
    exit;
}

$user_id = $_GET['id'];

try {
   $pdo->beginTransaction();
    $sql_delete_likes = "DELETE l FROM `Likes` AS l JOIN `Predictions` AS p ON l.prediction_id = p.prediction_id WHERE p.user_id = :user_id";
    $stmt_likes = $pdo->prepare($sql_delete_likes);
    $stmt_likes->bindParam(':user_id', $user_id);
    $stmt_likes->execute();

    $sql_delete_bonuses = "DELETE b FROM `Bonus` AS b JOIN `Predictions` AS p ON b.prediction_id = p.prediction_id WHERE p.user_id = :user_id";
    $stmt_bonuses = $pdo->prepare($sql_delete_bonuses);
    $stmt_bonuses->bindParam(':user_id', $user_id);
    $stmt_bonuses->execute();

    $sql_delete_predictions = "DELETE FROM Predictions WHERE user_id = :user_id";
    $stmt_predictions = $pdo->prepare($sql_delete_predictions);
    $stmt_predictions->bindParam(':user_id', $user_id);
    $stmt_predictions->execute();

    $sql_delete_user = "DELETE FROM Users WHERE user_id = :user_id";
    $stmt_user = $pdo->prepare($sql_delete_user);
    $stmt_user->bindParam(':user_id', $user_id);
    $stmt_user->execute();
    $pdo->commit();
    header('Location: manage_users.php?status=success&message=User and all associated data deleted successfully.');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Deletion Error: " . $e->getMessage());
    header('Location: manage_users.php?status=error&message=Failed to delete user.');
    exit;
}
$pdo = null;
?>
