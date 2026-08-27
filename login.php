<?php
$pageTitle = "Sign In - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$redirectUrl = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectUrl = $_POST['redirect_url'] ?? 'index.php';

    if (empty($email) || empty($password)) {
        $error = "Please provide both your email address and password.";
    } else {
        // Query user from database with prepared statement (SQL SELECT)
        $stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            header("Location: " . $redirectUrl);
            exit;
        } else {
            $error = "Invalid email address or password. Please try again.";
        }
    }
}
?>

<div class="form-card" style="max-width: 480px;">
    <div class="form-header">
        <span style="font-size: 36px; display: block; margin-bottom: 8px;">🔐</span>
        <h1 class="form-title">Member Sign In</h1>
        <p class="form-subtitle">
            Access your bookings, manage your preference shortlist, and book cinema tickets.
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert--danger">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirectUrl); ?>">

        <!-- Email Field -->
        <div class="form-group">
            <label class="form-label" for="login_email">Email Address</label>
            <input type="email" id="login_email" name="email" class="form-control" placeholder="user@silvervillage.local" required autofocus>
        </div>

        <!-- Password Field -->
        <div class="form-group">
            <label class="form-label" for="login_password">Password</label>
            <input type="password" id="login_password" name="password" class="form-control" placeholder="Your password" required>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top: 16px;">
            Sign In &rarr;
        </button>

        <p style="text-align: center; font-size: 14px; margin-top: 20px; color: var(--color-text-muted);">
            Don't have an account? <a href="register.php" style="font-weight: 600;">Register Here &rarr;</a>
        </p>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
