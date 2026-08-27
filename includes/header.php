<?php
/**
 * Silver Village Cinema - Shared Header Component
 * Expects $pageTitle variable for dynamic <title> tag.
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Silver Village Cinema - Your Seat, Your Show, Your Way';
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

$wishlistCount = getWishlistCount($conn);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <!-- Google Fonts: Playfair Display (Luxury serif) & Inter (Clean UI sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- External Custom CSS (Project Requirement: external stylesheet with min 4 styles) -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Top Navigation Bar -->
    <header class="site-header">
        <div class="header-container">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor">
                        <path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4zM8 17H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V7h2v2zm10 8h-8v-2h8v2zm0-4h-8v-2h8v2zm0-4h-8V7h8v2z"/>
                    </svg>
                </div>
                <div class="logo-text">
                    <span class="logo-title">SILVER VILLAGE</span>
                    <span class="logo-sub">CINEMA</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="main-nav">
                <ul class="nav-list">
                    <li>
                        <a href="index.php" class="nav-link <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">Home</a>
                    </li>
                    <li>
                        <a href="movies.php" class="nav-link <?php echo ($currentPage == 'movies.php' || $currentPage == 'movie_details.php') ? 'active' : ''; ?>">Movies</a>
                    </li>
                    <li>
                        <a href="wishlist.php" class="nav-link nav-wishlist <?php echo ($currentPage == 'wishlist.php') ? 'active' : ''; ?>">
                            Wishlist
                            <?php if ($wishlistCount > 0): ?>
                                <span class="badge-count"><?php echo $wishlistCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="feedback.php" class="nav-link <?php echo ($currentPage == 'feedback.php') ? 'active' : ''; ?>">Feedback</a>
                    </li>
                    <li>
                        <a href="about.php" class="nav-link <?php echo ($currentPage == 'about.php') ? 'active' : ''; ?>">About Us</a>
                    </li>
                </ul>
            </nav>

            <!-- User Authentication Status -->
            <div class="user-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="my_bookings.php" class="btn btn--outline btn--sm <?php echo ($currentPage == 'my_bookings.php') ? 'active' : ''; ?>">
                        <span class="btn-icon">🎟️</span> My Bookings
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="admin/index.php" class="btn btn--admin btn--sm">
                            <span class="btn-icon">⚡</span> Admin
                        </a>
                    <?php endif; ?>
                    <div class="user-profile-menu">
                        <span class="user-greeting">Hi, <strong><?php echo htmlspecialchars(explode(' ', getCurrentUserName())[0]); ?></strong></span>
                        <a href="logout.php" class="btn btn--ghost btn--sm" title="Sign Out">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn--ghost btn--sm <?php echo ($currentPage == 'login.php') ? 'active' : ''; ?>">Login</a>
                    <a href="register.php" class="btn btn--primary btn--sm <?php echo ($currentPage == 'register.php') ? 'active' : ''; ?>">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="main-content">
