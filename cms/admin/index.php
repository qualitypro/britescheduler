<?php
require_once('../config/config.php');
require_once('../includes/header.php');

// Check if the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Admin panel logic goes here

?>

<h1>Welcome, <?php echo $_SESSION['username']; ?>!</h1>
<p>This is the admin panel content.</p>
<a href="logout.php">Logout</a>

<?php
require_once('../includes/footer.php');
?>
