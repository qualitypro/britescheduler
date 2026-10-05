<?php
require_once('../config/config.php');
require_once('../includes/header.php');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Paginate results
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

try {
    // Fetch users with pagination using PDO
    $stmt = $pdo->prepare("SELECT * FROM users LIMIT :start, :perPage");
    $stmt->bindParam(':start', $start, PDO::PARAM_INT);
    $stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Display the users in a table
echo '<h1>Admin View Users</h1>';
echo '<table border="1">';
echo '<tr><th>User ID</th><th>Username</th><th>Email</th></tr>';

foreach ($users as $user) {
    echo '<tr>';
    echo '<td>' . $user['userid'] . '</td>';
    echo '<td>' . $user['username'] . '</td>';
    echo '<td>' . $user['email'] . '</td>';
    echo '</tr>';
}

echo '</table>';

// Display pagination links
echo '<div class="pagination">';
try {
    // Count total users
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
    
    // Display pagination links
    for ($i = 1; $i <= $totalPages; $i++) {
        echo "<a href='users.php?page=$i'>$i</a>";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
echo '</div>';

require_once('../includes/footer.php');
?>
