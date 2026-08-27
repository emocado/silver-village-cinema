<?php
/**
 * Silver Village Cinema - Authentication & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the user is currently logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the current user has the admin role
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Redirect to login page if user is not logged in
 */
function requireLogin($redirectUrl = '') {
    if (!isLoggedIn()) {
        $target = !empty($redirectUrl) ? urlencode($redirectUrl) : urlencode($_SERVER['REQUEST_URI']);
        header("Location: login.php?redirect=" . $target);
        exit;
    }
}

/**
 * Redirect to home page if user is not an admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: index.php?msg=unauthorized");
        exit;
    }
}

/**
 * Get current logged in user ID or null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged in user name or 'Guest'
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'Guest';
}

/**
 * Generate CSRF token and store in session
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify submitted CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get count of active wishlist items for current user
 */
function getWishlistCount($conn) {
    if (!isLoggedIn()) {
        return 0;
    }
    $userId = getCurrentUserId();
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM booking_wishlist WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($result['total'] ?? 0);
}
?>
