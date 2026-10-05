<?php
require_once('../config/config.php');

// Get the month and year from the request, or use the current month and year
$month = isset($_GET['month']) ? $_GET['month'] : date('n');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get the first day of the month and the number of days in the month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$numDays = date('t', $firstDay);

// Create an array to store the events for each day
$events = array();

// Fetch events from the database for the current month
$stmt = $pdo->prepare("SELECT date, description, time FROM events WHERE MONTH(date) = ? AND YEAR(date) = ?");
$stmt->execute([$month, $year]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $events[$row['date']] = $row['description'];
}

// Start building the calendar HTML
$html = '<h3>' . date('F Y', $firstDay) . '</h3>'; // Display the month name
$html .= '<table class="calendar">';
$html .= '<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>';
$html .= '<tr>';

// Fill in the days of the previous month
for ($i = 1; $i < date('w', $firstDay); $i++) {
    $html .= '<td class="inactive"></td>';
}

// Fill in the days of the current month
for ($day = 1; $day <= $numDays; $day++) {
    $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
    
    $html .= '<td class="day';
    if (isset($events[$date])) {
        $html .= ' event" data-description="' . htmlspecialchars($events[$date]) . '"';
    }
    $html .= '" data-date="' . $date . '">' . $day . '</td>';
    
    // Start a new row after each Saturday
    if (date('w', mktime(0, 0, 0, $month, $day, $year)) == 6 && $day < $numDays) {
        $html .= '</tr><tr>';
    }
}

// Fill in the remaining days of the last week with empty cells
for ($i = date('w', mktime(0, 0, 0, $month, $numDays, $year)); $i < 6; $i++) {
    $html .= '<td class="inactive"></td>';
}

$html .= '</tr></table>';

echo $html;
?>
