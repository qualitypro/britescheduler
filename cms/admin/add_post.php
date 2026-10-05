<?php
require_once('../config/config.php');
require_once('../includes/header.php');
// Check if the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Form handling logic for adding a new post goes here

?>

<h1>Add New Post</h1>
<form method="post" action="add_post.php">
    <label for="title">Title:</label>
    <input type="text" name="title" required>
    
    <label for="content">Content:</label>
    <textarea name="content" required></textarea>

    <button type="submit">Add Post</button>
</form>

<?php
require_once('../includes/footer.php');
?>
