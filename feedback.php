<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

$successMsg = '';
$errorMsg = '';
$selectedMovieId = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;

// Handle Review Submission (SQL INSERT Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $userId = getCurrentUserId();
    $movieId = (int)($_POST['movie_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');

    if ($movieId <= 0) {
        $errorMsg = "Please select a valid movie to review.";
    } elseif ($rating < 1 || $rating > 5) {
        $errorMsg = "Please provide a star rating between 1 and 5 stars.";
    } elseif (strlen($reviewText) < 10) {
        $errorMsg = "Please write a review of at least 10 characters.";
    } else {
        $insStmt = $conn->prepare("INSERT INTO feedback (user_id, movie_id, rating, review_text) VALUES (?, ?, ?, ?)");
        $insStmt->bind_param("iiis", $userId, $movieId, $rating, $reviewText);
        if ($insStmt->execute()) {
            $successMsg = "Thank you! Your verified customer review has been posted.";
            $selectedMovieId = $movieId;
        } else {
            $errorMsg = "Failed to submit review. Please try again.";
        }
        $insStmt->close();
    }
}

// Fetch all movies for dropdown
$moviesList = $conn->query("SELECT movie_id, title FROM movies ORDER BY title ASC");

// Fetch recent customer feedback entries
$reviewsSql = "
    SELECT f.*, u.full_name, m.title AS movie_title, m.poster_image 
    FROM feedback f 
    JOIN users u ON f.user_id = u.user_id 
    JOIN movies m ON f.movie_id = m.movie_id 
    ORDER BY f.created_at DESC 
    LIMIT 20
";
$reviewsResult = $conn->query($reviewsSql);

$pageTitle = "Audience Reviews & Feedback - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header" style="margin-bottom: 28px;">
    <div>
        <h1 class="section-title">Audience Feedback & Film Ratings</h1>
        <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
            Share your cinematic thoughts, rate movies you've watched, and read reviews from fellow cinema patrons.
        </p>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert--success">
        ✅ <?php echo htmlspecialchars($successMsg); ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert--danger">
        ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php endif; ?>

<div class="booking-layout">
    <!-- Left Column: Submit Review Form (Form requirement with server-side processing & DB interaction) -->
    <div class="form-card" style="max-width: 100%; margin: 0; padding: 28px;">
        <h2 style="font-size: 22px; color: var(--color-primary-light); margin-bottom: 8px;">
            ⭐ Write a Movie Review
        </h2>
        <p style="font-size: 13px; color: var(--color-text-muted); margin-bottom: 20px;">
            <?php if (isLoggedIn()): ?>
                Posting as <strong><?php echo htmlspecialchars(getCurrentUserName()); ?></strong>
            <?php else: ?>
                <em>You must be logged in to submit a review. <a href="login.php?redirect=feedback.php">Login here</a>.</em>
            <?php endif; ?>
        </p>

        <form id="feedbackForm" method="POST" action="feedback.php" novalidate>
            <!-- Movie Selection Dropdown -->
            <div class="form-group">
                <label class="form-label" for="movie_id">Select Movie</label>
                <select name="movie_id" id="movie_id" class="form-control" <?php echo !isLoggedIn() ? 'disabled' : ''; ?> required>
                    <option value="">-- Choose a Title --</option>
                    <?php while ($m = $moviesList->fetch_assoc()): ?>
                        <option value="<?php echo (int)$m['movie_id']; ?>" <?php echo ($selectedMovieId === (int)$m['movie_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Star Rating Selector -->
            <div class="form-group">
                <label class="form-label">Your Rating (1 to 5 Stars)</label>
                <div style="display: flex; gap: 16px; align-items: center; background: #0f131c; padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; color: #ffffff; font-size: 14px;">
                            <input type="radio" name="rating" value="<?php echo $i; ?>" style="accent-color: var(--color-primary);" <?php echo !isLoggedIn() ? 'disabled' : ''; ?> required>
                            <span><?php echo $i; ?>★</span>
                        </label>
                    <?php endfor; ?>
                </div>
                <div id="ratingError" class="form-error-msg">Please select a star rating.</div>
            </div>

            <!-- Review Text -->
            <div class="form-group">
                <label class="form-label" for="review_text">Review & Commentary</label>
                <textarea id="review_text" name="review_text" class="form-control" rows="4" placeholder="What did you think of the cinematography, performances, or sound experience?" <?php echo !isLoggedIn() ? 'disabled' : ''; ?> required></textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg" <?php echo !isLoggedIn() ? 'disabled style="opacity:0.5;"' : ''; ?>>
                Submit Verified Review &rarr;
            </button>
        </form>
    </div>

    <!-- Right Column: Recent Community Reviews -->
    <div>
        <h2 style="font-size: 20px; color: #ffffff; margin-bottom: 16px;">
            Recent Verified Reviews
        </h2>

        <?php if ($reviewsResult->num_rows > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php while ($r = $reviewsResult->fetch_assoc()): ?>
                    <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--color-primary-light); font-size: 15px; display: block;">
                                    <?php echo htmlspecialchars($r['movie_title']); ?>
                                </strong>
                                <span style="font-size: 12px; color: var(--color-text-muted);">
                                    By <?php echo htmlspecialchars($r['full_name']); ?>
                                </span>
                            </div>
                            <span style="color: var(--color-primary-light); font-size: 14px;">
                                <?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?>
                            </span>
                        </div>
                        <p style="color: #dfe2ef; font-size: 13px; line-height: 1.6; margin: 0;">
                            "<?php echo htmlspecialchars($r['review_text']); ?>"
                        </p>
                        <small style="color: var(--color-text-dim); display: block; margin-top: 8px; font-size: 11px;">
                            <?php echo date('d M Y, h:i A', strtotime($r['created_at'])); ?>
                        </small>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--color-text-muted);">No reviews submitted yet. Be the first to share your thoughts!</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
