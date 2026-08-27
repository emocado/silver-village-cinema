<?php
$pageTitle = "Create an Account - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$errors = [];
$successMsg = '';

// Form values initialization
$fullName = '';
$email = '';
$phone = '';
$dob = '';

// Handle Server-Side Form Submission (Course Requirement: Server-Side Processing of Form Data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $errors[] = "Security token mismatch. Please reload the page and try again.";
    }

    // 2. Sanitize and retrieve POST data
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);

    // 3. Server-Side Validations
    if (empty($fullName)) {
        $errors['full_name'] = "Full legal name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $fullName)) {
        $errors['full_name'] = "Name must only contain letters, spaces, and hyphens (2-50 characters).";
    }

    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please provide a valid email address.";
    }

    if (empty($phone)) {
        $errors['phone'] = "Mobile phone number is required.";
    } elseif (!preg_match("/^[689]\d{7}$/", preg_replace('/\s+/', '', $phone))) {
        $errors['phone'] = "Please provide a valid 8-digit Singapore phone number (starts with 6, 8, or 9).";
    }

    if (empty($dob)) {
        $errors['date_of_birth'] = "Date of birth is required.";
    } else {
        $dobTimestamp = strtotime($dob);
        if (!$dobTimestamp || $dobTimestamp >= time()) {
            $errors['date_of_birth'] = "Date of birth must be in the past.";
        } else {
            // Check minimum age 13
            $age = (date('Y') - date('Y', $dobTimestamp));
            if (date('md') < date('md', $dobTimestamp)) {
                $age--;
            }
            if ($age < 13) {
                $errors['date_of_birth'] = "You must be at least 13 years old to register.";
            }
        }
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $errors['password'] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors['password'] = "Password must contain at least one number.";
    }

    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (!$terms) {
        $errors['terms'] = "You must accept the terms of service to create an account.";
    }

    // 4. Database uniqueness check (SQL SELECT Transaction)
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $errors['email'] = "This email address is already registered. Please sign in instead.";
        }
        $checkStmt->close();
    }

    // 5. Insert new user into database (SQL INSERT Transaction)
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $cleanPhone = preg_replace('/\s+/', '', $phone);
        
        $insertStmt = $conn->prepare("
            INSERT INTO users (full_name, email, phone, date_of_birth, password_hash, role) 
            VALUES (?, ?, ?, ?, ?, 'customer')
        ");
        $insertStmt->bind_param("sssss", $fullName, $email, $cleanPhone, $dob, $passwordHash);
        
        if ($insertStmt->execute()) {
            $newUserId = $insertStmt->insert_id;
            $insertStmt->close();

            // Automatically log in the user
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'customer';

            // Redirect to homepage with welcome message
            header("Location: index.php?registered=1");
            exit;
        } else {
            $errors[] = "An unexpected error occurred while creating your account. Please try again.";
            $insertStmt->close();
        }
    }
}
?>

<div class="form-card">
    <div class="form-header">
        <span style="font-size: 36px; display: block; margin-bottom: 8px;">👑</span>
        <h1 class="form-title">Join Premiere Club</h1>
        <p class="form-subtitle">
            Create an account to book tickets, manage your preference wishlists, and receive e-tickets directly.
        </p>
    </div>

    <!-- General Error Banner -->
    <?php if (!empty($errors) && isset($errors[0])): ?>
        <div class="alert alert--danger">
            ⚠️ <?php echo htmlspecialchars($errors[0]); ?>
        </div>
    <?php endif; ?>

    <!-- 6-Field Registration Form with Client-Side + Server-Side Validation -->
    <form id="registerForm" method="POST" action="register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <!-- Field 1: Full Name -->
        <div class="form-group">
            <label class="form-label" for="full_name">
                <span>Full Legal Name <span style="color:var(--color-primary-light);">*</span></span>
            </label>
            <input type="text" id="full_name" name="full_name" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" placeholder="e.g. Johnathan Tan" value="<?php echo htmlspecialchars($fullName); ?>" required>
            <?php if (isset($errors['full_name'])): ?>
                <div class="form-error-msg" style="display:block;"><?php echo htmlspecialchars($errors['full_name']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Field 2: Email Address -->
        <div class="form-group">
            <label class="form-label" for="email">
                <span>Email Address <span style="color:var(--color-primary-light);">*</span></span>
            </label>
            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="e.g. user@silvervillage.local" value="<?php echo htmlspecialchars($email); ?>" required>
            <small class="form-help-text">E-tickets and receipts will be dispatched to this local server address.</small>
            <?php if (isset($errors['email'])): ?>
                <div class="form-error-msg" style="display:block;"><?php echo htmlspecialchars($errors['email']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Field 3: Mobile Phone -->
        <div class="form-group">
            <label class="form-label" for="phone">
                <span>Mobile Phone (SG +65) <span style="color:var(--color-primary-light);">*</span></span>
            </label>
            <input type="tel" id="phone" name="phone" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" placeholder="e.g. 9123 4567" value="<?php echo htmlspecialchars($phone); ?>" maxlength="12" required>
            <small class="form-help-text">8-digit Singapore number starting with 6, 8, or 9.</small>
            <?php if (isset($errors['phone'])): ?>
                <div class="form-error-msg" style="display:block;"><?php echo htmlspecialchars($errors['phone']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Field 4: Date of Birth -->
        <div class="form-group">
            <label class="form-label" for="date_of_birth">
                <span>Date of Birth <span style="color:var(--color-primary-light);">*</span></span>
            </label>
            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control <?php echo isset($errors['date_of_birth']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($dob); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            <small class="form-help-text">Must be at least 13 years of age for movie rating compliance.</small>
            <?php if (isset($errors['date_of_birth'])): ?>
                <div class="form-error-msg" style="display:block;"><?php echo htmlspecialchars($errors['date_of_birth']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Field 5: Password -->
        <div class="form-group">
            <label class="form-label" for="password">
                <span>Account Password <span style="color:var(--color-primary-light);">*</span></span>
            </label>
            <input type="password" id="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" placeholder="Min. 8 characters, 1 uppercase, 1 digit" required>
            <div class="password-meter-wrap">
                <div class="password-meter-bar">
                    <div id="pwdStrengthFill" class="password-meter-fill"></div>
                </div>
                <span id="pwdStrengthText" class="password-strength-text">Password strength</span>
            </div>
            <?php if (isset($errors['password'])): ?>
                <div class="form-error-msg" style="display:block;"><?php echo htmlspecialchars($errors['password']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Field 6: Confirm Password -->
        <div class="form-group">
            <label class="form-label" for="confirm_password">
                <span>Confirm Password <span style="color:var(--color-primary-light);">*</span></span>
            </label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" placeholder="Re-type your password" required>
            <?php if (isset($errors['confirm_password'])): ?>
                <div class="form-error-msg" style="display:block;"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Terms Agreement Checkbox -->
        <div class="form-group" style="flex-direction: row; align-items: flex-start; gap: 10px; margin-top: 10px;">
            <input type="checkbox" id="terms" name="terms" style="margin-top: 4px; accent-color: var(--color-primary);" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?> required>
            <label for="terms" style="font-size: 13px; color: var(--color-text-muted); cursor: pointer;">
                I agree to the Silver Village Cinema Booking Terms of Service and Privacy Policy.
            </label>
        </div>
        <?php if (isset($errors['terms'])): ?>
            <div class="form-error-msg" style="display:block; margin-top: -12px; margin-bottom: 16px;"><?php echo htmlspecialchars($errors['terms']); ?></div>
        <?php endif; ?>

        <!-- Submit Button -->
        <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top: 16px;">
            Create Account & Start Booking
        </button>

        <p style="text-align: center; font-size: 14px; margin-top: 20px; color: var(--color-text-muted);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign In Here &rarr;</a>
        </p>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
