<?php
require_once('../config/config.php');
require_once('../includes/header.php');
// Check if the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Logic for deleting a post goes here

?>

<h1>Delete Post</h1>
<p>Are you sure you want to delete this post?</p>

<form method="post" action="delete_post.php">
    <button type="submit">Yes, Delete</button>
</form>

<?php
require_once('../includes/footer.php');
?>
