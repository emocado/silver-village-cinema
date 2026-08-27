<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';

requireLogin();
$userId = getCurrentUserId();
$userName = getCurrentUserName();
$userEmail = $_SESSION['user_email'] ?? 'user@silvervillage.local';

$confirmedBookings = [];
$grandTotal = 0;
$bookingRef = 'SVC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
$error = '';
$isPaymentSimulatedFail = isset($_GET['simulate']) && $_GET['simulate'] === 'fail';

// --------------------------------------------------------------------------
// 1. Process Wishlist Confirmation (Multi-Booking Preference Fulfillment)
// --------------------------------------------------------------------------
if (isset($_POST['selected_wishlist_ids']) && is_array($_POST['selected_wishlist_ids'])) {
    $wishlistIds = array_map('intval', $_POST['selected_wishlist_ids']);
    
    if (count($wishlistIds) > 0) {
        $conn->begin_transaction();

        try {
            foreach ($wishlistIds as $wId) {
                // Fetch wishlist item details
                $qStmt = $conn->prepare("
                    SELECT w.*, s.screening_id, s.screening_date, s.screening_time,
                           m.title AS movie_title, m.poster_image, h.hall_id, h.hall_name, h.experience_type,
                           h.premium_row_start, h.standard_price, h.premium_price
                    FROM booking_wishlist w
                    JOIN screenings s ON w.screening_id = s.screening_id
                    JOIN movies m ON s.movie_id = m.movie_id
                    JOIN halls h ON s.hall_id = h.hall_id
                    WHERE w.wishlist_id = ? AND w.user_id = ?
                ");
                $qStmt->bind_param("ii", $wId, $userId);
                $qStmt->execute();
                $item = $qStmt->get_result()->fetch_assoc();
                $qStmt->close();

                if (!$item) continue;

                $seatsArr = explode(',', $item['selected_seats']);
                $screeningId = (int)$item['screening_id'];
                $itemPrice = (float)$item['estimated_total'];

                // Insert into bookings table (SQL INSERT)
                $bRef = 'SVC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
                $bStmt = $conn->prepare("
                    INSERT INTO bookings (booking_reference, user_id, screening_id, total_price, status, payment_status) 
                    VALUES (?, ?, ?, ?, 'confirmed', 'success')
                ");
                $bStmt->bind_param("siid", $bRef, $userId, $screeningId, $itemPrice);
                $bStmt->execute();
                $newBookingId = $bStmt->insert_id;
                $bStmt->close();

                // Insert into booked_seats table (SQL INSERT)
                foreach ($seatsArr as $seatLabel) {
                    $seatLabel = trim($seatLabel);
                    $rowChar = substr($seatLabel, 0, 1);
                    $rowIndex = ord(strtoupper($rowChar)) - ord('A') + 1;
                    $isPrem = ($rowIndex >= (int)$item['premium_row_start']);
                    $seatType = $isPrem ? 'premium' : 'standard';
                    $seatPrice = $isPrem ? (float)$item['premium_price'] : (float)$item['standard_price'];

                    $bsStmt = $conn->prepare("
                        INSERT INTO booked_seats (booking_id, seat_label, seat_type, price) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $bsStmt->bind_param("issd", $newBookingId, $seatLabel, $seatType, $seatPrice);
                    $bsStmt->execute();
                    $bsStmt->close();
                }

                // Delete confirmed item from wishlist (SQL DELETE)
                $delStmt = $conn->prepare("DELETE FROM booking_wishlist WHERE wishlist_id = ? AND user_id = ?");
                $delStmt->bind_param("ii", $wId, $userId);
                $delStmt->execute();
                $delStmt->close();

                $confirmedBookings[] = [
                    'booking_id' => $newBookingId,
                    'booking_ref' => $bRef,
                    'title' => $item['movie_title'],
                    'hall_name' => $item['hall_name'],
                    'experience_type' => $item['experience_type'],
                    'screening_date' => $item['screening_date'],
                    'screening_time' => $item['screening_time'],
                    'seats' => $item['selected_seats'],
                    'price' => $itemPrice
                ];
                $grandTotal += $itemPrice;
            }

            $conn->commit();

            // Send local server email acknowledgement (Course Requirement)
            if (!empty($confirmedBookings)) {
                sendBookingAcknowledgement($bookingRef, $userEmail, $userName, $confirmedBookings, $grandTotal);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Transaction failed: " . $e->getMessage();
        }
    }
}
// --------------------------------------------------------------------------
// 2. Process Direct Booking (Skip Wishlist Flow)
// --------------------------------------------------------------------------
elseif (isset($_SESSION['direct_booking'])) {
    $direct = $_SESSION['direct_booking'];
    unset($_SESSION['direct_booking']);

    $screeningId = (int)$direct['screening_id'];
    $seatsArr = $direct['seats'];
    $totalPrice = (float)$direct['total_price'];

    // Fetch screening info
    $sStmt = $conn->prepare("
        SELECT s.*, m.title AS movie_title, h.hall_name, h.experience_type, h.premium_row_start, h.standard_price, h.premium_price 
        FROM screenings s 
        JOIN movies m ON s.movie_id = m.movie_id 
        JOIN halls h ON s.hall_id = h.hall_id 
        WHERE s.screening_id = ?
    ");
    $sStmt->bind_param("i", $screeningId);
    $sStmt->execute();
    $sInfo = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();

    if ($sInfo) {
        $conn->begin_transaction();
        try {
            $bStmt = $conn->prepare("
                INSERT INTO bookings (booking_reference, user_id, screening_id, total_price, status, payment_status) 
                VALUES (?, ?, ?, ?, 'confirmed', 'success')
            ");
            $bStmt->bind_param("siid", $bookingRef, $userId, $screeningId, $totalPrice);
            $bStmt->execute();
            $newBookingId = $bStmt->insert_id;
            $bStmt->close();

            foreach ($seatsArr as $seatLabel) {
                $seatLabel = trim($seatLabel);
                $rowChar = substr($seatLabel, 0, 1);
                $rowIndex = ord(strtoupper($rowChar)) - ord('A') + 1;
                $isPrem = ($rowIndex >= (int)$sInfo['premium_row_start']);
                $seatType = $isPrem ? 'premium' : 'standard';
                $seatPrice = $isPrem ? (float)$sInfo['premium_price'] : (float)$sInfo['standard_price'];

                $bsStmt = $conn->prepare("
                    INSERT INTO booked_seats (booking_id, seat_label, seat_type, price) 
                    VALUES (?, ?, ?, ?)
                ");
                $bsStmt->bind_param("issd", $newBookingId, $seatLabel, $seatType, $seatPrice);
                $bsStmt->execute();
                $bsStmt->close();
            }

            $conn->commit();

            $confirmedBookings[] = [
                'booking_id' => $newBookingId,
                'booking_ref' => $bookingRef,
                'title' => $sInfo['movie_title'],
                'hall_name' => $sInfo['hall_name'],
                'experience_type' => $sInfo['experience_type'],
                'screening_date' => $sInfo['screening_date'],
                'screening_time' => $sInfo['screening_time'],
                'seats' => implode(',', $seatsArr),
                'price' => $totalPrice
            ];
            $grandTotal = $totalPrice;

            // Send local server email
            sendBookingAcknowledgement($bookingRef, $userEmail, $userName, $confirmedBookings, $grandTotal);
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Handle Simulated Payment Failure Flow (Course Requirement)
if ($isPaymentSimulatedFail && !empty($confirmedBookings)) {
    foreach ($confirmedBookings as $b) {
        $bId = (int)$b['booking_id'];
        $failStmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'failed' WHERE booking_id = ?");
        $failStmt->bind_param("i", $bId);
        $failStmt->execute();
        $failStmt->close();
    }
}

$pageTitle = "Booking Confirmation - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 860px; margin: 0 auto;">
    <?php if ($isPaymentSimulatedFail): ?>
        <!-- Payment Failure Banner -->
        <div class="alert alert--danger" style="padding: 24px; border-radius: var(--radius-lg); flex-direction: column; align-items: flex-start; gap: 8px;">
            <h2 style="color: #f87171; font-size: 22px; margin: 0;">❌ Payment Simulation: Transaction Cancelled</h2>
            <p style="color: #dfe2ef; margin: 0; font-size: 14px;">
                The third-party payment gateway simulation reported a payment failure. Your reserved seats have been released and the booking status has been updated to <strong>Cancelled</strong>.
            </p>
            <div style="margin-top: 12px;">
                <a href="wishlist.php" class="btn btn--outline btn--sm">Return to Wishlist</a>
                <a href="movies.php" class="btn btn--primary btn--sm">Explore Movies</a>
            </div>
        </div>
    <?php elseif (!empty($confirmedBookings)): ?>
        <!-- Success Hero Header -->
        <div style="text-align: center; margin-bottom: 36px;">
            <div style="width: 72px; height: 72px; background: rgba(16, 185, 129, 0.15); border: 2px solid #10b981; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; font-size: 32px;">
                ✓
            </div>
            <h1 style="font-size: 32px; color: #ffffff; margin-bottom: 8px;">Booking Confirmed & Digital Tickets Issued!</h1>
            <p style="color: var(--color-text-muted); font-size: 15px;">
                Thank you, <strong><?php echo htmlspecialchars($userName); ?></strong>! Your tickets are ready.
            </p>
        </div>

        <!-- Email Notification Notice with Direct View Button -->
        <div class="alert alert--info" style="margin-bottom: 32px; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 24px;">📧</span>
                <div>
                    <strong style="color: #ffffff;">Email Confirmation Dispatched:</strong>
                    <p style="margin: 0; font-size: 13px; color: var(--color-text-muted);">
                        An official e-ticket receipt has been generated and sent to: <code><?php echo htmlspecialchars($userEmail); ?></code>
                    </p>
                </div>
            </div>
            <div>
                <a href="mailbox.php?ref=<?php echo urlencode($confirmedBookings[0]['booking_ref'] ?? ''); ?>" class="btn btn--primary btn--sm" target="_blank">
                    ✉️ View Email in Local Mailbox &rarr;
                </a>
            </div>
        </div>

        <!-- Digital E-Ticket Passes (Boarding Pass Layout) -->
        <h2 style="font-size: 20px; color: var(--color-primary-light); margin-bottom: 16px;">
            Digital E-Tickets (<?php echo count($confirmedBookings); ?>)
        </h2>

        <?php foreach ($confirmedBookings as $ticket): ?>
            <div class="ticket-pass">
                <div class="ticket-header">
                    <div>
                        <span class="pill-tag" style="background: rgba(212, 175, 55, 0.2); color: var(--color-primary-light); font-weight: bold; margin-bottom: 4px; display: inline-block;">
                            ADMISSION PASS • <?php echo htmlspecialchars($ticket['experience_type']); ?>
                        </span>
                        <h2 style="font-size: 24px; margin: 0; color: #ffffff;"><?php echo htmlspecialchars($ticket['title']); ?></h2>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; color: var(--color-text-dim); display: block;">BOOKING REF</span>
                        <strong style="font-family: monospace; font-size: 16px; color: var(--color-primary-light);">
                            <?php echo htmlspecialchars($ticket['booking_ref']); ?>
                        </strong>
                    </div>
                </div>

                <div class="ticket-body-grid">
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <span style="font-size: 12px; color: var(--color-text-muted); display: block;">DATE & TIME</span>
                                <strong style="color: #ffffff; font-size: 15px;">
                                    <?php echo date('D, d M Y', strtotime($ticket['screening_date'])); ?><br>
                                    <?php echo date('h:i A', strtotime($ticket['screening_time'])); ?>
                                </strong>
                            </div>
                            <div>
                                <span style="font-size: 12px; color: var(--color-text-muted); display: block;">CINEMA HALL</span>
                                <strong style="color: #ffffff; font-size: 15px;">
                                    <?php echo htmlspecialchars($ticket['hall_name']); ?>
                                </strong>
                            </div>
                        </div>

                        <div style="background: #0f131c; border-radius: var(--radius-sm); padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 12px; color: var(--color-text-muted); display: block;">RESERVED SEATS</span>
                                <span style="font-size: 18px; font-weight: bold; color: var(--color-primary-light);">
                                    <?php echo htmlspecialchars($ticket['seats']); ?>
                                </span>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 12px; color: var(--color-text-muted); display: block;">SUBTOTAL</span>
                                <span style="font-size: 18px; font-weight: bold; color: #ffffff;">
                                    $<?php echo number_format($ticket['price'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Barcode Representation -->
                    <div class="qr-code-placeholder">
                        <svg viewBox="0 0 100 100" fill="currentColor">
                            <rect x="10" y="10" width="30" height="30" />
                            <rect x="15" y="15" width="20" height="20" fill="#fff" />
                            <rect x="20" y="20" width="10" height="10" />
                            <rect x="60" y="10" width="30" height="30" />
                            <rect x="65" y="15" width="20" height="20" fill="#fff" />
                            <rect x="70" y="20" width="10" height="10" />
                            <rect x="10" y="60" width="30" height="30" />
                            <rect x="15" y="65" width="20" height="20" fill="#fff" />
                            <rect x="20" y="70" width="10" height="10" />
                            <rect x="45" y="45" width="10" height="10" />
                            <rect x="60" y="60" width="10" height="10" />
                            <rect x="75" y="75" width="15" height="15" />
                        </svg>
                        <small style="font-size: 9px; margin-top: 4px; font-weight: bold; letter-spacing: 1px;">SCAN AT GATE</small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Payment Simulation Sandbox Controls -->
        <div class="form-card" style="max-width: 100%; margin: 32px 0 0 0; padding: 24px; background: #121620;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h3 style="font-size: 16px; color: var(--color-primary-light); margin-bottom: 4px;">
                        💳 Payment Status: <span style="color:#34d399;">SUCCESS (Paid \$<?php echo number_format($grandTotal, 2); ?>)</span>
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: var(--color-text-muted);">
                        Payment verified via third-party sandbox payment portal.
                    </p>
                </div>
                <div>
                    <a href="confirmation.php?simulate=fail" class="btn btn--danger btn--sm" onclick="return confirm('Simulate payment gateway failure and cancel this transaction?');">
                        Simulate Payment Failure Flow
                    </a>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: center; gap: 16px; margin-top: 32px;">
            <a href="my_bookings.php" class="btn btn--primary btn--lg">
                View in My Bookings &rarr;
            </a>
            <a href="movies.php" class="btn btn--ghost btn--lg">
                Browse More Movies
            </a>
        </div>
    <?php else: ?>
        <div class="form-card" style="text-align: center; padding: 48px 24px;">
            <span style="font-size: 48px; display: block; margin-bottom: 16px;">🎟️</span>
            <h2 style="color: #ffffff; margin-bottom: 8px;">No Pending Confirmation</h2>
            <p style="color: var(--color-text-muted); margin-bottom: 24px;">
                You have not initiated any ticket booking. Visit our Movies directory to pick a showtime or check your existing bookings.
            </p>
            <a href="movies.php" class="btn btn--primary">Browse Movies</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
