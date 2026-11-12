<?php
// Start the session if the session has not been started yet.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/functions.php';

// Checks whether a user has logged in.
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Enforcing role based restrictions
function requireRole($role) {
    if (!isLoggedIn() || $_SESSION['role'] !== $role) {
        setFlash('error', 'Please log in with the correct account to open that page.');
        redirect('auth/login.php');
    }
}
