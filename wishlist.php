<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = getCurrentUserId();
$msg = '';
$msgType = 'info';

require_once __DIR__.'/includes/booking_service.php';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        changeWishlist($conn,$userId,(string)($_POST['action']??''),(int)($_POST['id']??0));
        header('Location: wishlist.php',true,303); exit;
    } catch(Throwable $e) { $msg=userError($e); $msgType='danger'; }
}
// Fetch all wishlist items for current user
$wSql = "
    SELECT w.wishlist_id, w.selected_seats, w.preference_rank, w.estimated_total, w.added_at,
           s.screening_id, s.screening_date, s.screening_time,
           m.movie_id, m.title AS movie_title, m.rating, m.poster_image,
           h.hall_name, h.experience_type
    FROM booking_wishlist w
    JOIN screenings s ON w.screening_id = s.screening_id
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    WHERE w.user_id = ?
    ORDER BY w.preference_rank ASC, w.added_at DESC
";
$wStmt = $conn->prepare($wSql);
$wStmt->bind_param("i", $userId);
$wStmt->execute();
$wishlistItems = $wStmt->get_result();

$pageTitle = "Booking Wishlist & Preferences - CinéPop";
require_once __DIR__ . '/includes/header.php';
?>

<div class="wishlist-hero">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 32px; margin-bottom: 6px; color: var(--color-primary-light);">
                Your Booking Wishlist & Preferences
            </h1>
            <p style="color: var(--color-text-muted); font-size: 14px; margin: 0;">
                Review your shortlisted showtimes ranked in order of preference. Check live seat availability and select one or more bookings to confirm together.
            </p>
        </div>
        <a href="movies.php" class="btn btn--outline btn--sm">
            + Add More Movies
        </a>
    </div>
</div>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert--success">
        ✅ Screening and seats successfully added to your preference wishlist!
    </div>
<?php endif; ?>

<?php if (!empty($msg)): ?>
    <div class="alert alert--<?php echo $msgType; ?>">
        ℹ️ <?php echo htmlspecialchars($msg); ?>
    </div>
<?php endif; ?>

<?php if ($wishlistItems->num_rows > 0): ?>
    <form method="POST" action="confirmation.php?mode=wishlist" id="wishlistConfirmForm"><?= csrfField() ?>
        <div class="booking-layout">
            <!-- Left Column: Wishlist Items Stacked by Preference -->
            <div class="wishlist-cards-list">
                <?php 
                $itemIndex = 0;
                while ($item = $wishlistItems->fetch_assoc()): 
                    $itemIndex++;
                    $wishlistId = (int)$item['wishlist_id'];
                    $screeningId = (int)$item['screening_id'];
                    $seatsArr = explode(',', $item['selected_seats']);

                    // Check live seat availability against booked_seats
                    $placeholders = implode(',', array_fill(0, count($seatsArr), '?'));
                    $checkSql = "
                        SELECT bs.seat_label 
                        FROM booked_seats bs 
                        JOIN bookings b ON bs.booking_id = b.booking_id 
                        WHERE b.screening_id = ? AND b.status = 'confirmed' AND bs.seat_label IN ($placeholders)
                    ";
                    $cStmt = $conn->prepare($checkSql);
                    $types = 'i' . str_repeat('s', count($seatsArr));
                    $cStmt->bind_param($types, $screeningId, ...$seatsArr);
                    $cStmt->execute();
                    $conflictsResult = $cStmt->get_result();
                    $takenSeats = [];
                    while ($tRow = $conflictsResult->fetch_assoc()) {
                        $takenSeats[] = $tRow['seat_label'];
                    }
                    $cStmt->close();

                    $hasStarted = strtotime($item['screening_date'].' '.$item['screening_time']) <= time();
                    $isFullyAvailable = !$hasStarted && (count($takenSeats) === 0);
                    $isPartiallyAvailable = (count($takenSeats) > 0 && count($takenSeats) < count($seatsArr));
                    $isSoldOut = (count($takenSeats) === count($seatsArr));

                    $rankClass = ($item['preference_rank'] == 1) ? 'rank-1' : (($item['preference_rank'] == 2) ? 'rank-2' : 'rank-3');
                ?>
                    <div class="wishlist-card <?php echo $isFullyAvailable ? 'is-selected' : ''; ?>" id="card_<?php echo $wishlistId; ?>">
                        <!-- Selection Checkbox -->
                        <div class="wishlist-checkbox-wrap">
                            <input type="checkbox" 
                                   name="selected_wishlist_ids[]" 
                                   value="<?php echo $wishlistId; ?>" 
                                   class="wishlist-checkbox" 
                                   data-price="<?php echo (float)$item['estimated_total']; ?>"
                                   data-available="<?php echo $isFullyAvailable ? '1' : '0'; ?>"
                                   <?php echo ($isFullyAvailable && $itemIndex === 1) ? 'checked' : ($isFullyAvailable ? 'checked' : 'disabled'); ?>>
                        </div>

                        <!-- Card Content -->
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <span class="wishlist-rank-badge <?php echo $rankClass; ?>">
                                    Preference #<?php echo (int)$item['preference_rank']; ?>
                                </span>
                                <?php if ($isFullyAvailable): ?>
                                    <span class="status-badge status-badge--success">🟢 100% Available</span>
                                <?php elseif ($isPartiallyAvailable): ?>
                                    <span class="status-badge status-badge--warning">🟡 Partially Available (<?php echo implode(', ', $takenSeats); ?> booked)</span>
                                <?php else: ?>
                                    <span class="status-badge status-badge--danger"><?= $hasStarted ? 'Screening has started' : 'Unavailable (Seats taken)' ?></span>
                                <?php endif; ?>
                            </div>

                            <h3 class="wishlist-movie-title">
                                <?php echo htmlspecialchars($item['movie_title']); ?>
                            </h3>

                            <div class="wishlist-meta">
                                <span>Date: <?php echo date('D, d M Y', strtotime($item['screening_date'])); ?></span>
                                <span>Time: <?php echo date('h:i A', strtotime($item['screening_time'])); ?></span>
                                <span><?php echo htmlspecialchars($item['hall_name']); ?> (<?php echo htmlspecialchars($item['experience_type']); ?>)</span>
                            </div>

                            <div style="font-size: 13px; color: var(--color-text-muted);">
                                <span>Reserved Seats: </span>
                                <?php foreach ($seatsArr as $s): ?>
                                    <span class="pill-tag" style="background:#173b61; color:var(--color-primary-light); font-weight:bold;">
                                        <?php echo htmlspecialchars($s); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Actions & Pricing -->
                        <div class="wishlist-actions">
                            <span class="wishlist-price">$<?php echo number_format($item['estimated_total'], 2); ?></span>
                            
                            <div class="wishlist-btn-row">
                                <button type="submit" form="wishlistAction<?= $wishlistId ?>" name="action" value="move_up" class="btn btn--ghost btn--sm" title="Increase Preference Rank">▲</button>
                                <button type="submit" form="wishlistAction<?= $wishlistId ?>" name="action" value="move_down" class="btn btn--ghost btn--sm" title="Decrease Preference Rank">▼</button>
                                <a href="booking.php?screening_id=<?php echo $screeningId; ?>&wishlist_id=<?= $wishlistId ?>" class="btn btn--outline btn--sm" title="Modify Seats">✏️ Edit</a>
                                <button type="submit" form="wishlistAction<?= $wishlistId ?>" name="action" value="remove" class="btn btn--danger btn--sm" title="Remove">Remove</button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Right Column: Consolidated Multi-Booking Checkout Summary -->
            <div class="form-card" style="max-width: 100%; margin: 0; padding: 24px; position: sticky; top: 90px;">
                <h3 style="font-size: 20px; color: var(--color-primary-light); margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
                    Final Selection Summary
                </h3>

                <p style="font-size: 13px; color: var(--color-text-muted); margin-bottom: 12px;">
                    Select the preferred showtime(s) you wish to confirm. Unselected wishlist items will remain in your account.
                </p>

                <div style="background: #0b2544; border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Selected Bookings:</span>
                        <strong id="selectedCountDisplay">0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--color-border); padding-top: 8px; margin-top: 8px; font-size: 18px; color: var(--color-primary-light);">
                        <strong>Grand Total:</strong>
                        <strong id="selectedGrandTotal" style="font-family: var(--font-heading);">$0.00</strong>
                    </div>
                </div>

                <button type="submit" id="confirmWishlistBtn" class="btn btn--primary btn--block btn--lg" style="margin-bottom: 12px;">
                    Confirm & Proceed to Payment
                </button>

                <button type="submit" form="wishlistClear" name="action" value="clear" class="btn btn--ghost btn--block btn--sm">Clear Entire Wishlist</button>
            </div>
        </div>
    </form>
    <form method="post" action="wishlist.php" id="wishlistClear"><?= csrfField() ?></form>
    <?php $actionRows=cinemaQuery($conn,'SELECT wishlist_id FROM booking_wishlist WHERE user_id=?','i',[$userId])->get_result(); while($actionItem=$actionRows->fetch_assoc()): ?>
    <form method="post" action="wishlist.php" id="wishlistAction<?= (int)$actionItem['wishlist_id'] ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$actionItem['wishlist_id'] ?>"></form>
    <?php endwhile; ?>
<?php else: ?>
    <!-- Empty Wishlist State -->
    <div class="form-card" style="text-align: center; max-width: 540px; padding: 48px 24px;">
        <h2 style="font-size: 24px; color: #ffffff; margin-bottom: 8px;">Your Wishlist is Empty</h2>
        <p style="color: var(--color-text-muted); margin-bottom: 24px; line-height: 1.6;">
            You have not shortlisted any movie showtimes yet. Explore our Now Showing titles, pick your preferred seats, and add them to your wishlist to compare and confirm!
        </p>
        <a href="movies.php" class="btn btn--primary btn--lg">
            Browse Movies & Showtimes &rarr;
        </a>
    </div>
<?php endif; ?>

<!-- Script to handle dynamic multi-booking selection and total calculation -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.wishlist-checkbox');
    const countDisplay = document.getElementById('selectedCountDisplay');
    const totalDisplay = document.getElementById('selectedGrandTotal');
    const confirmBtn = document.getElementById('confirmWishlistBtn');
    const form = document.getElementById('wishlistConfirmForm');

    function updateTotal() {
        let count = 0;
        let total = 0;

        checkboxes.forEach(cb => {
            if (cb.checked) {
                count++;
                total += parseFloat(cb.getAttribute('data-price') || 0);
            }
        });

        if (countDisplay) countDisplay.textContent = count + ' ' + (count === 1 ? 'Showtime' : 'Showtimes');
        if (totalDisplay) totalDisplay.textContent = '$' + total.toFixed(2);

        if (confirmBtn) {
            if (count === 0) {
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';
            } else {
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
            }
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateTotal);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            let anyChecked = false;
            checkboxes.forEach(cb => {
                if (cb.checked) anyChecked = true;
            });
            if (!anyChecked) {
                e.preventDefault();
                alert('Please select at least one wishlist item to confirm your booking.');
            }
        });
    }

    // Initial calculation on page load
    updateTotal();
});
</script>

<?php
$wStmt->close();
require_once __DIR__ . '/includes/footer.php';
?>
