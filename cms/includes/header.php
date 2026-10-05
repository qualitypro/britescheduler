<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Scheduler</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div class="header">
    <?php
    session_start();
    if (isset($_SESSION['user_id'])) {
        // Navigation for logged-in users
        echo '<nav>';
        echo '<a href="/dashboard/cms/index.php">Home</a>';
        echo '|';
        echo '<a href="/dashboard/cms/admin/events.php">View Events</a>';
        echo '|';
        echo '<a href="/dashboard/cms/admin/logout.php">Logout</a>';
        echo '</nav>';
    } else {
        // Navigation for non-logged-in users
        echo '<nav>';
        echo '<a href="/dashboard/cms/">Home</a>';
        echo '|';
        echo '<a href="/dashboard/cms/admin/login.php">Login</a>';
        echo '|';
        echo '<a href="/dashboard/cms/signup.php">Sign Up</a>';
        echo '</nav>';
    }

    // Admin links
    if (isset($_SESSION['user_id']) /*  && check if user is admin */) {
        echo '<div class="admin-links">';
        echo '<h2>Admin Panel</h2>';
        echo '<a href="/dashboard/cms/admin/schedule_event.php">Schedule Events</a>';
        echo '|';
        echo '<a href="/dashboard/cms/admin/users.php">Users</a>';
        // Add more admin links as needed
        echo '</div>';
    }
    ?>

</div>

<div class="content">
