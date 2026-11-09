<?php
// Top of every page + navigation. The page sets $pageTitle before including this file.
$role = $_SESSION['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Home') ?> - LifeLink Blood</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="<?= BASE_URL ?>/assets/js/main.js" defer></script>
</head>
<body>
<?php
$isHome = (($pageTitle ?? '') === 'Home');
// Home = big banner; every other page = slim banner. Login and Register get their own picture.
$bannerClass = $isHome ? 'top-banner-home' : 'top-banner-slim';
if (($pageTitle ?? '') === 'Login')    { $bannerClass .= ' top-banner-login'; }
if (($pageTitle ?? '') === 'Register') { $bannerClass .= ' top-banner-register'; }
?>
<!-- The banner picture is the background of the whole top area; the header sits on top of it -->
<div class="top-banner <?= $bannerClass ?>">
<header class="site-header">
    <div class="container nav-bar">
        <a class="logo" href="<?= BASE_URL ?>/index.php">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            LifeLink Blood
        </a>
        <button type="button" class="menu-toggle" id="menuToggle">Menu</button>
        <nav id="mainNav">
            <a href="<?= BASE_URL ?>/index.php">Home</a>
            <?php if ($role): ?>
                <a href="<?= BASE_URL ?>/<?= e($role) ?>/dashboard.php">Dashboard</a>
            <?php endif; ?>
            <?php if ($role === 'patient'): ?>
                <a href="<?= BASE_URL ?>/patient/request.php">New Request</a>
            <?php endif; ?>

            <?php if ($role): ?>
                <form method="post" action="<?= BASE_URL ?>/auth/logout.php" class="logout-form">
                    <button type="submit" class="link-button">Logout (<?= e($_SESSION['name']) ?>)</button>
                </form>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/auth/login.php">Login</a>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-small">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($isHome): ?>
    <div class="banner-text">
        <h1>Give blood. Save lives.</h1>
        <p>Find blood quickly, request it from a hospital, or pledge to donate.</p>
    </div>
<?php else: ?>
    <div class="banner-text">
        <strong>Give blood. Save lives.</strong>
        <span>LifeLink Blood connects patients, donors and hospitals.</span>
    </div>
<?php endif; ?>
</div>
<main class="container">
<?php showFlash(); ?>
