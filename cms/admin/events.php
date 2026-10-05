<?php
require_once('../config/config.php');
require_once('../includes/header.php');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Paginate by month
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

try {
    // Fetch events for the current month using PDO
    $stmt = $pdo->prepare("SELECT * FROM events WHERE MONTH(date) = :month AND YEAR(date) = :year");
    $stmt->bindParam(':month', $currentMonth, PDO::PARAM_INT);
    $stmt->bindParam(':year', $currentYear, PDO::PARAM_INT);
    $stmt->execute();
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Display the calendar
echo '<h1>Events Calendar</h1>';

// Display navigation for previous and next months
echo '<div class="calendar-navigation">';
echo '<a href="events.php?month=' . ($currentMonth - 1) . '&year=' . $currentYear . '">&lt; Previous Month</a>';
echo ' | ';
echo '<a href="events.php?month=' . ($currentMonth + 1) . '&year=' . $currentYear . '">Next Month &gt;</a>';
echo '</div>';

// Display the calendar for the current month
echo '<table class="calendar">';
echo '<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>';

$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$lastDay = mktime(0, 0, 0, $currentMonth + 1, 0, $currentYear);

$currentDay = 1;
$dayOfWeek = date('w', $firstDay);
echo '<tr>';

// Display empty cells for days before the first day of the month
for ($i = 0; $i < $dayOfWeek; $i++) {
    echo '<td></td>';
}

while ($currentDay <= date('t', $lastDay)) {
    for ($i = $dayOfWeek; $i < 7; $i++) {
        $date = date('Y-m-d', mktime(0, 0, 0, $currentMonth, $currentDay, $currentYear));
        
        echo '<td>';
        echo '<span class="day">' . $currentDay . '</span>';
        
        // Display events for the current date
        foreach ($events as $event) {
            if ($event['date'] == $date) {
                echo '<p class="event">' . $event['description'] . '</p>';
            }
        }
        
        echo '</td>';
        
        $currentDay++;
        if ($currentDay > date('t', $lastDay)) {
            break;
        }
    }
    
    echo '</tr><tr>';
    $dayOfWeek = 0;
}

echo '</tr>';
echo '</table>';

require_once('../includes/footer.php');
?>
