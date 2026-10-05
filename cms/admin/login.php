<?php
require_once('../config/config.php');
require_once('../includes/header.php');

// Handle login logic
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate user credentials
    $username = $_POST['username'];
    //$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    //$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    //$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $password = MD5($_POST['password']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND password = :password");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password); // In a production scenario, hash the password and compare the hash
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

?>

<h1>Login</h1>

<?php if (isset($error)) : ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<form method="post" action="login.php">
    <label for="username">Username:</label>
    <input type="text" name="username" required>
    
    <label for="password">Password:</label>
    <input type="password" name="password" required>

    <button type="submit">Login</button>
</form>

<?php
require_once('../includes/footer.php');
?>
