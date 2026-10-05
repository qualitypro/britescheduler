<?php
require_once('../config/config.php');

// Handle AJAX request for scheduling an event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_STRING);
    $completed_status = filter_input(INPUT_POST, 'completed_status', FILTER_SANITIZE_NUMBER_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $repeating = filter_input(INPUT_POST, 'repeating', FILTER_SANITIZE_STRING);
    
    try {
        // Insert new event into the database
        $stmt = $pdo->prepare("INSERT INTO events (userid, date, time, completed_status, description, repeating)
                               VALUES (:userid, :date, :time, :completed_status, :description, :repeating)");
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':completed_status', $completed_status, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':repeating', $repeating);
        $stmt->execute();
        
        echo "Event scheduled successfully!";
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error: " . $e->getMessage();
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo "Invalid request method!";
}
?>