<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($action === 'edit') {
    $text = mysqli_real_escape_string($conn, $_POST['text']);
    $user_id = $_SESSION['user_id'];

    $sql = "UPDATE predictions SET prediction_text='$text', is_edited=1, updated_at=NOW() 
            WHERE prediction_id=$id AND user_id=$user_id";
    mysqli_query($conn, $sql);
    echo "success";
}

if ($action === 'delete') {
    $user_id = $_SESSION['user_id'];
    $sql = "DELETE FROM predictions WHERE prediction_id=$id AND user_id=$user_id";
    mysqli_query($conn, $sql);
    echo "deleted";
}
?>
