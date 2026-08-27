<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

$movieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($movieId <= 0) {
    header("Location: movies.php");
    exit;
}

// Fetch Movie Details
$stmt = $conn->prepare("SELECT * FROM movies WHERE movie_id = ?");
$stmt->bind_param("i", $movieId);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$movie) {
    header("Location: movies.php");
    exit;
}

$pageTitle = htmlspecialchars($movie['title']) . " - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';

// Fetch Screenings for this movie with Hall details & booked seats count
$screeningsSql = "
    SELECT s.screening_id, s.screening_date, s.screening_time, 
           h.hall_id, h.hall_name, h.experience_type, h.total_rows, h.seats_per_row,
           h.standard_price, h.premium_price,
           (h.total_rows * h.seats_per_row) AS total_seats,
           (
               SELECT COUNT(*) 
               FROM booked_seats bs 
               JOIN bookings b ON bs.booking_id = b.booking_id 
               WHERE b.screening_id = s.screening_id AND b.status = 'confirmed'
           ) AS booked_count
    FROM screenings s
    JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.movie_id = ? AND s.screening_date >= CURDATE()
    ORDER BY s.screening_date ASC, s.screening_time ASC
";
$sStmt = $conn->prepare($screeningsSql);
$sStmt->bind_param("i", $movieId);
$sStmt->execute();
$screeningsResult = $sStmt->get_result();

// Fetch Customer Feedback & Average Rating
$fStmt = $conn->prepare("
    SELECT f.*, u.full_name 
    FROM feedback f 
    JOIN users u ON f.user_id = u.user_id 
    WHERE f.movie_id = ? 
    ORDER BY f.created_at DESC
");
$fStmt->bind_param("i", $movieId);
$fStmt->execute();
$feedbackResult = $fStmt->get_result();

// Calculate Average Rating
$avgRating = 0;
$totalReviews = $feedbackResult->num_rows;
$ratingSum = 0;
$feedbacks = [];
while ($fb = $feedbackResult->fetch_assoc()) {
    $feedbacks[] = $fb;
    $ratingSum += (int)$fb['rating'];
}
if ($totalReviews > 0) {
    $avgRating = round($ratingSum / $totalReviews, 1);
}

// Determine movie-specific dynamic backdrop
$backdropImg = '';
if ((int)$movieId === 1 && file_exists(__DIR__ . '/images/hero_spiderman.jpg')) {
    $backdropImg = 'images/hero_spiderman.jpg';
} elseif (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])) {
    $backdropImg = 'images/posters/' . $movie['poster_image'];
}
?>

<!-- Movie Hero & Synopsis Banner -->
<div class="hero-section" style="margin-bottom: 36px;">
    <?php if (!empty($backdropImg)): ?>
        <div class="hero-backdrop" style="background-image: url('<?php echo htmlspecialchars($backdropImg); ?>'); <?php echo ((int)$movieId !== 1) ? 'filter: blur(28px) brightness(0.22); transform: scale(1.15); opacity: 0.45;' : ''; ?>"></div>
    <?php endif; ?>
    <div class="hero-overlay" style="max-width: 100%; display: grid; grid-template-columns: 240px 1fr; gap: 36px; align-items: center;">
        <div class="movie-poster-wrap" style="border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,0.8); border:1px solid var(--color-border-glass);">
            <?php if (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])): ?>
                <img src="images/posters/<?php echo htmlspecialchars($movie['poster_image']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?> Poster">
            <?php else: ?>
                <div class="poster-fallback">
                    <span class="poster-fallback-icon">🎬</span>
                    <strong class="poster-fallback-title"><?php echo htmlspecialchars($movie['title']); ?></strong>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                <span class="pill-rating"><?php echo htmlspecialchars($movie['rating']); ?></span>
                <span class="pill-tag"><?php echo (int)$movie['duration_minutes']; ?> Minutes</span>
                <span class="pill-tag"><?php echo htmlspecialchars($movie['genre']); ?></span>
                <?php if ($totalReviews > 0): ?>
                    <span class="pill-tag" style="background: rgba(242, 202, 80, 0.2); color: var(--color-primary-light); font-weight: bold;">
                        ★ <?php echo $avgRating; ?> / 5.0 (<?php echo $totalReviews; ?> Reviews)
                    </span>
                <?php endif; ?>
            </div>
            <h1 style="font-size: 36px; margin-bottom: 14px;"><?php echo htmlspecialchars($movie['title']); ?></h1>
            <p style="color: #d0c5af; margin-bottom: 16px; line-height: 1.7; font-size: 15px;">
                <?php echo nl2br(htmlspecialchars($movie['synopsis'])); ?>
            </p>
            <div style="font-size: 13px; color: var(--color-text-muted); display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <p><strong>Director:</strong> <?php echo htmlspecialchars($movie['director']); ?></p>
                <p><strong>Starring:</strong> <?php echo htmlspecialchars($movie['cast']); ?></p>
                <p><strong>Release Date:</strong> <?php echo date('d M Y', strtotime($movie['release_date'])); ?></p>
                <p><strong>Status:</strong> <span style="text-transform: capitalize; color: #ffffff;"><?php echo str_replace('_', ' ', $movie['status']); ?></span></p>
            </div>
        </div>
    </div>
</div>

<!-- Structured Showtimes Table (IE4727 Core Requirement: 1 table displaying content effectively) -->
<section style="margin-bottom: 48px;">
    <div class="section-header">
        <div>
            <h2 class="section-title">Screening Schedule & Showtimes</h2>
            <p style="color: var(--color-text-muted); font-size: 13px; margin-top: 4px;">
                Select your preferred screening to pick your seats and add to your booking shortlist or checkout.
            </p>
        </div>
    </div>

    <?php if ($screeningsResult->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Showtime</th>
                        <th>Cinema Hall</th>
                        <th>Audio & Experience</th>
                        <th>Seat Availability</th>
                        <th>Pricing Tier</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $screeningsResult->fetch_assoc()): 
                        $availableSeats = $s['total_seats'] - $s['booked_count'];
                        $isSoldOut = ($availableSeats <= 0);
                        $isAlmostFull = ($availableSeats <= 10 && !$isSoldOut);
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo date('D, d M Y', strtotime($s['screening_date'])); ?></strong>
                                <?php if ($s['screening_date'] == date('Y-m-d')): ?>
                                    <span class="status-badge status-badge--success" style="font-size: 10px; margin-left: 4px;">Today</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 16px; font-weight: 700; color: var(--color-primary-light);">
                                    <?php echo date('h:i A', strtotime($s['screening_time'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($s['hall_name']); ?></strong>
                            </td>
                            <td>
                                <span class="pill-tag" style="background: rgba(212, 175, 55, 0.1); border: 1px solid rgba(212, 175, 55, 0.3);">
                                    <?php echo htmlspecialchars($s['experience_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isSoldOut): ?>
                                    <span class="status-badge status-badge--danger">Sold Out (0/<?php echo $s['total_seats']; ?>)</span>
                                <?php elseif ($isAlmostFull): ?>
                                    <span class="status-badge status-badge--warning">Only <?php echo $availableSeats; ?> seats left!</span>
                                <?php else: ?>
                                    <span class="status-badge status-badge--success"><?php echo $availableSeats; ?> / <?php echo $s['total_seats']; ?> Available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--color-text-muted);">
                                    Std: $<?php echo number_format($s['standard_price'], 2); ?> | Prem: $<?php echo number_format($s['premium_price'], 2); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($isSoldOut): ?>
                                    <button class="btn btn--ghost btn--sm" disabled style="opacity: 0.5; cursor: not-allowed;">Sold Out</button>
                                <?php else: ?>
                                    <a href="booking.php?screening_id=<?php echo (int)$s['screening_id']; ?>" class="btn btn--primary btn--sm">
                                        Select Seats &rarr;
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="form-card" style="text-align: center; padding: 36px 20px;">
            <p style="color: var(--color-text-muted); margin-bottom: 16px;">
                No upcoming screenings are currently scheduled for this title. Please check back soon or browse other movies.
            </p>
            <a href="movies.php" class="btn btn--outline">Browse Other Movies</a>
        </div>
    <?php endif; ?>
</section>

<!-- Customer Reviews & Feedback Section -->
<section class="reviews-section" style="margin-bottom: 40px;">
    <div class="section-header">
        <div>
            <h2 class="section-title">Audience Reviews & Ratings</h2>
            <p style="color: var(--color-text-muted); font-size: 13px; margin-top: 4px;">
                Verified thoughts from Silver Village Cinema patrons.
            </p>
        </div>
        <a href="feedback.php?movie_id=<?php echo (int)$movieId; ?>" class="btn btn--outline btn--sm">
            ⭐ Write a Review
        </a>
    </div>

    <?php if (count($feedbacks) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($feedbacks as $fb): ?>
                <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong><?php echo htmlspecialchars($fb['full_name']); ?></strong>
                        <span style="color: var(--color-primary-light); font-size: 14px;">
                            <?php echo str_repeat('★', (int)$fb['rating']) . str_repeat('☆', 5 - (int)$fb['rating']); ?>
                        </span>
                    </div>
                    <p style="color: #d0c5af; font-size: 14px; margin-bottom: 10px; line-height: 1.6;">
                        "<?php echo htmlspecialchars($fb['review_text']); ?>"
                    </p>
                    <small style="color: var(--color-text-dim);">
                        Posted on <?php echo date('d M Y', strtotime($fb['created_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: var(--color-text-muted); font-style: italic;">
            Be the first to share your thoughts on this movie! <a href="feedback.php?movie_id=<?php echo (int)$movieId; ?>">Leave a review here</a>.
        </p>
    <?php endif; ?>
</section>

<?php
$sStmt->close();
$fStmt->close();
require_once __DIR__ . '/includes/footer.php';
?>
