<?php
require_once('config/config.php');
require_once('includes/header.php');

// Get the post ID from the URL
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Fetch the specific post using PDO
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :postId");
    $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
    $stmt->execute();
    
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo '<p>Post not found.</p>';
    } else {
        ?>

        <h1><?php echo $post['title']; ?></h1>
        <p><?php echo $post['content']; ?></p>
        <p><em>Posted on <?php echo date('F j, Y', strtotime($post['created_at'])); ?></em></p>

        <?php
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once('includes/footer.php');
?>
