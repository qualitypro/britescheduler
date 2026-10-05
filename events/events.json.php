<?php
// events.php - Server-side script to fetch events from the database
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Connect to the database
$pdo = new PDO('mysql:host=localhost;dbname=events', 'root', '');

// Fetch events from the database
$query = $pdo->prepare("SELECT `event_id`,`user_id`,`start_date`,`end_date`,`start_time`,`end_time`,`description`,`repeat_type`,`is_public`, `is_active` FROM `events`");
$query->execute();
$events = $query->fetchAll(PDO::FETCH_ASSOC);

// Convert events to the desired JSON format
$outputEvents = [];
foreach ($events as $event) {

    $formattedEvent = [
        'event_id' => $event['event_id'],
        'title' => $event['description'],
        'description' => $event['description'],
        'user_id' => $event['user_id'],
        'start_time' => $event['start_time'],
        'end_time' => $event['end_time'],
        'repeat_type' => $event['repeat_type'],
        'is_public' => $event['is_public'],
        'is_active' => $event['is_active'],
    ];
    if (!empty($event['start_date'])) {
        if (!empty($event['start_time'])) {
        $formattedEvent['start'] = $event['start_date']."T".$event['start_time'];
        }else{
        $formattedEvent['start'] = $event['start_date'];
        }
    }
    // Check if the event has an end date and include it in the output
    if (!empty($event['end_date'])) {
        
        if (!empty($event['end_time'])) {
        $formattedEvent['end'] = $event['end_date']."T".$event['end_time'];
        }else{
        $formattedEvent['end'] = $event['end_date'];
        }
    }

    // Check if the event has a URL and include it in the output
    if (!empty($event['url'])) {
        $formattedEvent['url'] = $event['url'];
    }

    // Check if the event has a group ID and include it in the output
    if (!empty($event['group_id'])) {
        $formattedEvent['groupId'] = $event['group_id'];
    }

    $outputEvents[] = $formattedEvent;
}

// Output events in JSON format
echo json_encode(['events' => $outputEvents]);
?>
