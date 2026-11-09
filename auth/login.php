<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';
$email = '';

// Handle the submitted form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post('email');
    $password = $_POST['password'] ?? '';

    // Find the user and check the password
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && is_string($password) && password_verify($password, $user['password'])) {
        // Save login details in the session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['blood_type'] = $user['blood_type'];

        redirect($user['role'] . '/dashboard.php');
    }
    $error = 'Wrong email or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/../includes/header.php';
?>
<h1>Login</h1>
<form method="post" action="login.php" class="card form-narrow">
    <?php if ($error): ?><ul class="error-list"><li><?= e($error) ?></li></ul><?php endif; ?>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn">Login</button>
    <p>No account yet? <a href="register.php">Register here</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
