<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = getCurrentUserId();
$msg = '';

// Handle Booking Cancellation (Course Requirement: SQL UPDATE Transaction)
if (isset($_GET['cancel_id'])) {
    $cancelId = (int)$_GET['cancel_id'];
    if ($cancelId > 0) {
        $canStmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND user_id = ?");
        $canStmt->bind_param("ii", $cancelId, $userId);
        if ($canStmt->execute()) {
            $msg = "Booking successfully cancelled. The reserved seats have been released.";
        }
        $canStmt->close();
    }
}

// Fetch all bookings for the logged-in user with seats list (SQL JOIN & GROUP_CONCAT)
$sql = "
    SELECT b.booking_id, b.booking_reference, b.total_price, b.status, b.payment_status, b.booking_date,
           s.screening_date, s.screening_time,
           m.title AS movie_title, m.poster_image,
           h.hall_name, h.experience_type,
           GROUP_CONCAT(bs.seat_label ORDER BY bs.seat_label SEPARATOR ', ') AS seats_list
    FROM bookings b
    JOIN screenings s ON b.screening_id = s.screening_id
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    LEFT JOIN booked_seats bs ON b.booking_id = bs.booking_id
    WHERE b.user_id = ?
    GROUP BY b.booking_id
    ORDER BY b.booking_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$bookingsResult = $stmt->get_result();

$pageTitle = "My Bookings - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header" style="margin-bottom: 28px;">
    <div>
        <h1 class="section-title">My Cinema Bookings & Tickets</h1>
        <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
            View your confirmed e-tickets, review showtime details, or manage ticket reservations.
        </p>
    </div>
    <a href="movies.php" class="btn btn--primary btn--sm">+ Book Another Movie</a>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert--success">
        ✅ <?php echo htmlspecialchars($msg); ?>
    </div>
<?php endif; ?>

<?php if ($bookingsResult->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Booking Ref</th>
                    <th>Movie Title</th>
                    <th>Date & Showtime</th>
                    <th>Hall</th>
                    <th>Reserved Seats</th>
                    <th>Amount Paid</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($b = $bookingsResult->fetch_assoc()): 
                    $isConfirmed = ($b['status'] === 'confirmed');
                    $isUpcoming = (strtotime($b['screening_date'] . ' ' . $b['screening_time']) > time());
                ?>
                    <tr>
                        <td>
                            <strong style="font-family: monospace; color: var(--color-primary-light);">
                                <?php echo htmlspecialchars($b['booking_reference']); ?>
                            </strong>
                            <small style="display: block; color: var(--color-text-dim); font-size: 11px;">
                                <?php echo date('d M, h:i A', strtotime($b['booking_date'])); ?>
                            </small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($b['movie_title']); ?></strong>
                        </td>
                        <td>
                            <?php echo date('D, d M Y', strtotime($b['screening_date'])); ?><br>
                            <span style="font-weight: 700; color: #ffffff;"><?php echo date('h:i A', strtotime($b['screening_time'])); ?></span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($b['hall_name']); ?>
                            <small style="display: block; color: var(--color-text-muted); font-size: 11px;">
                                <?php echo htmlspecialchars($b['experience_type']); ?>
                            </small>
                        </td>
                        <td>
                            <span class="pill-tag" style="background:#262a34; color:var(--color-primary-light); font-weight:bold;">
                                <?php echo htmlspecialchars($b['seats_list'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #ffffff;">\$<?php echo number_format($b['total_price'], 2); ?></strong>
                        </td>
                        <td>
                            <?php if ($isConfirmed): ?>
                                <span class="status-badge status-badge--success">Confirmed</span>
                            <?php else: ?>
                                <span class="status-badge status-badge--danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="mailbox.php?ref=<?php echo urlencode($b['booking_reference']); ?>" class="btn btn--outline btn--sm" style="margin-right:4px;" title="View E-Ticket Email Receipt">
                                ✉️ Email
                            </a>
                            <?php if ($isConfirmed && $isUpcoming): ?>
                                <a href="my_bookings.php?cancel_id=<?php echo (int)$b['booking_id']; ?>" 
                                   class="btn btn--danger btn--sm" 
                                   onclick="return confirm('Are you sure you want to cancel this booking? Reserved seats will be released.');">
                                    Cancel
                                </a>
                            <?php elseif ($isConfirmed): ?>
                                <span style="font-size: 12px; color: var(--color-text-dim);">Completed</span>
                            <?php else: ?>
                                <span style="font-size: 12px; color: var(--color-danger);">Cancelled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="form-card" style="text-align: center; max-width: 500px; padding: 48px 24px;">
        <span style="font-size: 48px; display: block; margin-bottom: 16px;">🎟️</span>
        <h2 style="color: #ffffff; margin-bottom: 8px;">No Bookings Found</h2>
        <p style="color: var(--color-text-muted); margin-bottom: 24px;">
            You have not placed any ticket bookings yet. Check out our Now Showing directory and book your seats!
        </p>
        <a href="movies.php" class="btn btn--primary btn--lg">Explore Movies & Showtimes</a>
    </div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/includes/footer.php';
?>
