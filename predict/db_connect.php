<?php
// db_connect.php - Database connection file

$host = 'localhost:3307'; // Your database host (e.g., 'localhost', '127.0.0.1')
$db = 'story_prediction'; // The database name you created
$user = 'root'; // Your database username
$pass = ''; // Your database password (leave empty if no password)
$port=3307;
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
   // echo "Connected successfully to the database!"; //For testing connection
} catch (\PDOException $e) {
    // Log the error for debugging, but don't show sensitive info to the user
    error_log("Database Connection Error: " . $e->getMessage());
    die("Could not connect to the database. Please try again later.");
}
?>
