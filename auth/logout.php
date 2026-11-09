<?php
require_once __DIR__ . '/../includes/auth.php';

// Logging out is a POST action (the Logout button in the header)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    session_destroy();
    session_start();
    setFlash('success', 'You have been logged out.');
    redirect('auth/login.php');
}
redirect('index.php');
