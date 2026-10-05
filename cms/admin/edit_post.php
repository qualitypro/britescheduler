<?php
require_once('../config/config.php');
require_once('../includes/header.php');
// Check if the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Form handling logic for editing an existing post goes here

?>

<h1>Edit Post</h1>
<form method="post" action="edit_post.php">
    <label for="title">Title:</label>
    <input type="text" name="title" value="<?php echo $postTitle; ?>" required>
    
    <label for="content">Content:</label>
    <textarea name="content" required><?php echo $postContent; ?></textarea>

    <button type="submit">Save Changes</button>
</form>

<?php
require_once('../includes/footer.php');
?>
