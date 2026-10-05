<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connect to the database using PDO
// Replace the placeholders with your actual database credentials
$dsn = "mysql:host=localhost;dbname=events";
$username = "root";
$password = "";

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Extract data from the POST request
$eventId = urldecode($_POST['event_id']);
$userId = urldecode($_POST['user_id']);
$startDate = urldecode($_POST['start_date']);
$startTime = urldecode($_POST['start_time']);
$endDate = urldecode($_POST['end_date']);
$endTime = urldecode($_POST['end_time']);
$description = urldecode($_POST['description']);
$repeatType = urldecode($_POST['repeat_type']);
$isPublic = urldecode($_POST['is_public']) === 'true' ? 'yes' : 'no';
$isActive = urldecode($_POST['is_active']) === 'true' ? 'yes' : 'no';

// Prepare and execute the SQL query to save/update the event
try {
    if ($eventId) {
        $stmt = $pdo->prepare("UPDATE events
                              SET start_date = str_to_date(:start_date, '%m/%d/%Y'),
                                  end_date = str_to_date(:end_date, '%m/%d/%Y'),
                                  start_time = STR_TO_DATE(:start_time, '%H:%i'),
                                  end_time = STR_TO_DATE(:end_time, '%H:%i'),
                                  description = :description,
                                  repeat_type = :repeat_type,
                                  is_public = :is_public,
                                  is_active = :is_active
                              WHERE event_id = :event_id AND user_id = :user_id");
        
        $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("INSERT INTO events
                              (user_id, start_date, end_date, start_time, end_time, description, repeat_type, is_public, is_active)
                              VALUES (:user_id, str_to_date(:start_date, '%m/%d/%Y'), str_to_date(:end_date, '%m/%d/%Y'), STR_TO_DATE(:start_time, '%H:%i'), STR_TO_DATE(:end_time, '%H:%i'), :description, :repeat_type, :is_public, :is_active)");
    }
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->bindParam(':start_time', $startTime, PDO::PARAM_STR);
    $stmt->bindParam(':end_time', $endTime, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':repeat_type', $repeatType, PDO::PARAM_STR);
    $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_STR);
    $stmt->bindParam(':is_active', $isActive, PDO::PARAM_STR);
    
    $stmt->execute();
    
    echo "Event saved successfully";
} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
}
?>
