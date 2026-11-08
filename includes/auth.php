<?php
/**
 * CinéPop - Authentication & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
    session_start();
}
date_default_timezone_set('Asia/Singapore');

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
        header('Location: '.cinemaUrl('account.php').'?redirect='.$target);
        exit;
    }
}

/**
 * Redirect to home page if user is not an admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: '.cinemaUrl('index.php').'?msg=unauthorized');
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
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function cinemaUrl(string $path): string {
    $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??$_SERVER['PHP_SELF']??'/index.php')),'/');
    $base=preg_replace('~/(?:admin|enhanced)$~', '', $base);
    return ($base==='.'?'':$base).'/'.$path;
}
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(generateCsrfToken(),ENT_QUOTES,'UTF-8').'">'; }
function requireCsrf(): void {
    if(!verifyCsrfToken($_POST['csrf_token']??'')) throw new RuntimeException('Your form expired. Reload the page and try again.');
}
function safeLocalRedirect($target): string {
    $fallback=cinemaUrl('index.php');
    if(!is_string($target) || preg_match('/[\x00-\x20\\\\]/',$target) || strpos($target,'//')===0 || preg_match('/^[a-z][a-z0-9+.-]*:/i',$target)) return $fallback;
    $base=dirname($fallback).'/';
    if(strpos($target,'/')===0 && strpos($target,$base)!==0) return $fallback;
    if(strpos($target,'..')!==false) return $fallback;
    return $target?:$fallback;
}
function userError(Throwable $error): string {
    return $error instanceof mysqli_sql_exception?'The database could not complete this request. Please try again.':$error->getMessage();
}

// Keep admin sessions on staff routes, while allowing logout.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (isAdmin() && basename(dirname($scriptName)) !== 'admin' && basename($scriptName) !== 'logout.php') {
    header('Location: '.cinemaUrl('admin/index.php'), true, 303);
    exit;
}

// Enforce the same request protection for every included form handler.
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && !verifyCsrfToken($_POST['csrf_token']??'')) {
    http_response_code(403);
    exit('<!doctype html><html lang="en"><meta charset="utf-8"><title>Form expired</title><h1>Your form expired</h1><p>Go back, reload the page and submit again.</p></html>');
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    foreach($_POST as $field=>$value) {
        if(!is_string($value) && !($field==='selected_wishlist_ids' && is_array($value) && !array_filter($value,fn($id)=>!is_string($id)))) {
            http_response_code(400); exit('Invalid form input. Reload the page and try again.');
        }
    }
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
