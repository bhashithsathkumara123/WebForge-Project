<?php
// Start the session, load settings and helpers (every page includes this file)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/functions.php';

// Is someone logged in?
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Allow only one role on a page, otherwise send the visitor to the login page
function requireRole($role) {
    if (!isLoggedIn() || $_SESSION['role'] !== $role) {
        setFlash('error', 'Please log in with the correct account to open that page.');
        redirect('auth/login.php');
    }
}
