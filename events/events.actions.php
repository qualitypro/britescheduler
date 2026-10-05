<?php
// events.actions.php - Server-side script to handle AJAX requests for updating or adding events to the database

// Assuming you have a database connection
$pdo = new PDO('mysql:host=localhost;dbname=events', 'root', '');

// Get data from AJAX POST request
$event_id = $_POST['event_id'];
$user_id = $_POST['user_id'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$description = $_POST['description'];
$repeat_type = $_POST['repeat_type'];
$is_public = $_POST['is_public'];
$is_active = $_POST['is_active'];

// You may want to perform validation and sanitization of input data here

// Prepare SQL statement based on whether it's an update or insert
if (!empty($event_id)) {
    $sql = "UPDATE events SET
            user_id = :user_id,
            start_date = :start_date,
            end_date = :end_date,
            start_time = :start_time,
            end_time = :end_time,
            description = :description,
            repeat_type = :repeat_type,
            is_public = :is_public,
            is_active = :is_active
            WHERE event_id = :event_id";
} else {
    $sql = "INSERT INTO events (user_id, start_date, end_date, start_time, end_time, description, repeat_type, is_public, is_active)
            VALUES (:user_id, :start_date, :end_date, :start_time, :end_time, :description, :repeat_type, :is_public, :is_active)";
}

// Prepare and execute the SQL statement
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->bindParam(':start_time', $start_time);
$stmt->bindParam(':end_time', $end_time);
$stmt->bindParam(':description', $description);
$stmt->bindParam(':repeat_type', $repeat_type);
$stmt->bindParam(':is_public', $is_public);
$stmt->bindParam(':is_active', $is_active);

if (!empty($event_id)) {
    $stmt->bindParam(':event_id', $event_id);
}

$result = $stmt->execute();

if ($result) {
    echo "Event saved successfully";
} else {
    echo "Error saving event";
}
?>
