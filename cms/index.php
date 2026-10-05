<?php
require_once('config/config.php');
require_once('includes/header.php');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 5;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

try {
    // Fetch posts with pagination using PDO
    $stmt = $pdo->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT :start, :perPage");
    $stmt->bindParam(':start', $start, PDO::PARAM_INT);
    $stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

?>

<h1>Recent Posts</h1>

<?php foreach ($posts as $post) : ?>
    <div class="post">
        <h2><?php echo $post['title']; ?></h2>
        <p><?php echo substr($post['content'], 0, 200) . '...'; ?></p>
        <p><em>Posted on <?php echo date('F j, Y', strtotime($post['created_at'])); ?></em></p>
        <a href="page.php?id=<?php echo $post['id']; ?>">Read more</a>
    </div>
<?php endforeach; ?>

<div class="pagination">
    <?php
    try {
        // Count total posts
        $totalPosts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $totalPages = ceil($totalPosts / $perPage);

        // Display pagination links
        for ($i = 1; $i <= $totalPages; $i++) {
            echo "<a href='index.php?page=$i'>$i</a>";
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    ?>
</div>

<?php
require_once('includes/footer.php');
?>
