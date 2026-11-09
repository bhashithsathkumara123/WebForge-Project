<?php
// Database settings - change these if your XAMPP/WAMP setup is different
define('DB_HOST', 'localhost');
define('DB_NAME', 'blood_donation');
define('DB_USER', 'root');
define('DB_PASS', '');

// URL path of the project (the folder name inside htdocs)
define('BASE_URL', '/WebForge');

// Stock thresholds in units: below 10 = LOW, below 5 = CRITICAL
define('STOCK_LIMITS', ['LOW' => 10, 'CRITICAL' => 5]);

// Months between two donations, and units added to stock per donation
define('DONATION_GAP_MONTHS', 4);
define('UNITS_PER_DONATION', 1);

// Create the PDO connection used by every page
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please check database/db.php');
}
