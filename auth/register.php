<?php
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$old = ['role' => '', 'name' => '', 'email' => '', 'blood_type' => ''];

// Handle the submitted form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $value) {
        $old[$key] = post($key);
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role = $old['role'];

    // Validate the input
    if (!in_array($role, ['patient', 'donor', 'hospital'])) {
        $errors[] = 'Please choose a role.';
    }
    if (strlen($old['name']) < 2 || strlen($old['name']) > 100) {
        $errors[] = 'Name must be 2 to 100 characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!is_string($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($role !== 'hospital' && !in_array($old['blood_type'], bloodTypes())) {
        $errors[] = 'Please choose a blood type.';
    }

    // Email must not be used already
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    // Save the new account (hospitals have no blood type and start with no stock)
    if (!$errors) {
        $bloodType = ($role === 'hospital') ? null : $old['blood_type'];
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, blood_type) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $role, $bloodType]);

        setFlash('success', 'Account created. You can log in now.');
        redirect('auth/login.php');
    }
}

$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>
<h1>Create an account</h1>
<form method="post" action="register.php" class="card form-narrow">
    <?php if ($errors): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="field">
        <label for="role">I am a</label>
        <select id="role" name="role" required>
            <option value="">-- Select role --</option>
            <?php foreach (['patient', 'donor', 'hospital'] as $r): ?>
                <option value="<?= $r ?>" <?= $old['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="name">Name (hospital name for hospitals)</label>
        <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" minlength="2" maxlength="100" required>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
    </div>
    <div class="field">
        <label for="password">Password (6+ characters)</label>
        <input type="password" id="password" name="password" minlength="6" required>
    </div>
    <div class="field">
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>

    <!-- Hidden by main.js when the role is hospital -->
    <div class="field" id="bloodTypeBox">
        <label for="blood_type">Blood type</label>
        <select id="blood_type" name="blood_type">
            <?= bloodTypeOptions($old['blood_type']) ?>
        </select>
    </div>

    <button type="submit" class="btn">Register</button>
    <p>Already registered? <a href="login.php">Login here</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
