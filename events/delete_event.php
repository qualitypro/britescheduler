<?php
// Connect to the database using PDO
// Replace the placeholders with your actual database credentials
$dsn = "mysql:host=localhost;dbname=events";
$username = "root";
$password = "";

try {
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Extract data from the POST request
$eventId = $_POST['event_id'];
$userId = $_POST['user_id'];

// Prepare and execute the SQL query to delete the event
$stmt = $pdo->prepare("DELETE FROM events WHERE event_id = :event_id AND user_id = :user_id");
$stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

$stmt->execute();

echo "Event deleted successfully";
?>
