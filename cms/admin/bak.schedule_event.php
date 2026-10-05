<?php
require_once('../config/config.php');
require_once('../includes/header.php');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_STRING);
    $completed_status = filter_input(INPUT_POST, 'completed_status', FILTER_SANITIZE_NUMBER_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $repeating = isset($_POST['repeating']) ? 1 : 0;
    
    try {
        // Insert new event into the database
        $stmt = $pdo->prepare("INSERT INTO events (userid, date, time, completed_status, description, repeating)
                               VALUES (:userid, :date, :time, :completed_status, :description, :repeating)");
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':completed_status', $completed_status, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':repeating', $repeating, PDO::PARAM_INT);
        $stmt->execute();
        
        // Redirect to the events listing or perform other actions
        header('Location: events.php');
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

?>

<h1>Schedule Event</h1>

<form method="post" action="schedule_event.php">
    <input type="hidden" name="userid" value="<?php echo ''.$_SESSION['user_id'].'';?>">

    <label for="date">Date:</label>
    <input type="date" name="date" required>

    <label for="time">Time:</label>
    <input type="time" name="time" required>

    <label for="completed_status">Completed Status:</label>
    <select name="completed_status" required>
        <option value="0">Not Completed</option>
        <option value="1">Completed</option>
    </select>

    <label for="description">Description:</label>
    <textarea name="description" required></textarea>

    <label for="repeating">Repeating:</label>
    <input type="checkbox" name="repeating">

    <button type="submit">Schedule Event</button>
</form>

<?php
require_once('../includes/footer.php');
?>
