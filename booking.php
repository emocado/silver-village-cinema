<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

// Require login for ticket seat reservation
requireLogin();

$screeningId = isset($_GET['screening_id']) ? (int)$_GET['screening_id'] : 0;
if ($screeningId <= 0) {
    header("Location: movies.php");
    exit;
}

// Fetch Screening, Movie and Hall information
$sql = "
    SELECT s.screening_id, s.screening_date, s.screening_time,
           m.movie_id, m.title AS movie_title, m.rating, m.duration_minutes, m.genre, m.poster_image,
           h.hall_id, h.hall_name, h.experience_type, h.total_rows, h.seats_per_row, 
           h.premium_row_start, h.standard_price, h.premium_price
    FROM screenings s
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.screening_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $screeningId);
$stmt->execute();
$screening = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$screening) {
    header("Location: movies.php");
    exit;
}

// Fetch all booked seats for this screening
$bookedSql = "
    SELECT bs.seat_label 
    FROM booked_seats bs 
    JOIN bookings b ON bs.booking_id = b.booking_id 
    WHERE b.screening_id = ? AND b.status = 'confirmed'
";
$bStmt = $conn->prepare($bookedSql);
$bStmt->bind_param("i", $screeningId);
$bStmt->execute();
$bResult = $bStmt->get_result();
$bookedSeats = [];
while ($row = $bResult->fetch_assoc()) {
    $bookedSeats[] = $row['seat_label'];
}
$bStmt->close();

// Handle POST: Add to Wishlist OR Direct Checkout
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedSeatsStr = trim($_POST['selected_seats'] ?? '');
    $bookingAction = $_POST['booking_action'] ?? 'add_wishlist';
    $prefRank = (int)($_POST['preference_rank'] ?? 1);

    if (empty($selectedSeatsStr)) {
        $errorMsg = "Please select at least one seat on the seat map before proceeding.";
    } else {
        $selectedSeatsArr = explode(',', $selectedSeatsStr);
        // Calculate total price based on seat tiers
        $calculatedTotal = 0;
        foreach ($selectedSeatsArr as $seat) {
            $seat = trim($seat);
            $rowChar = substr($seat, 0, 1);
            $rowIndex = ord(strtoupper($rowChar)) - ord('A') + 1;
            if ($rowIndex >= $screening['premium_row_start']) {
                $calculatedTotal += (float)$screening['premium_price'];
            } else {
                $calculatedTotal += (float)$screening['standard_price'];
            }
        }

        if ($bookingAction === 'add_wishlist') {
            // Insert into booking_wishlist table (SQL INSERT Transaction)
            $userId = getCurrentUserId();
            $wStmt = $conn->prepare("
                INSERT INTO booking_wishlist (user_id, screening_id, selected_seats, preference_rank, estimated_total) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $wStmt->bind_param("iisid", $userId, $screeningId, $selectedSeatsStr, $prefRank, $calculatedTotal);
            
            if ($wStmt->execute()) {
                $wStmt->close();
                header("Location: wishlist.php?added=1");
                exit;
            } else {
                $errorMsg = "Failed to add booking to wishlist. Please try again.";
                $wStmt->close();
            }
        } elseif ($bookingAction === 'direct_checkout') {
            // Store selected seats in session and forward to confirmation.php
            $_SESSION['direct_booking'] = [
                'screening_id' => $screeningId,
                'seats' => $selectedSeatsArr,
                'total_price' => $calculatedTotal
            ];
            header("Location: confirmation.php?mode=direct");
            exit;
        }
    }
}

$pageTitle = "Select Seats: " . htmlspecialchars($screening['movie_title']) . " - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header" style="margin-bottom: 24px;">
    <div>
        <h1 class="section-title">Select Seats & Booking Options</h1>
        <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
            🎬 <strong><?php echo htmlspecialchars($screening['movie_title']); ?></strong> &nbsp;|&nbsp; 
            🎭 <?php echo htmlspecialchars($screening['hall_name']); ?> (<?php echo htmlspecialchars($screening['experience_type']); ?>) &nbsp;|&nbsp; 
            📅 <?php echo date('D, d M Y', strtotime($screening['screening_date'])); ?> @ <?php echo date('h:i A', strtotime($screening['screening_time'])); ?>
        </p>
    </div>
</div>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert--danger">
        ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php endif; ?>

<div class="booking-layout">
    <!-- Left Column: Cinema Stage & Interactive Seat Grid -->
    <div class="screen-stage-wrap">
        <!-- Curved Cinema Screen Visualizer -->
        <div class="curved-screen">
            <span class="screen-label">CURVED CINEMA SCREEN</span>
        </div>

        <!-- Seat Grid -->
        <div class="seat-grid-container" id="seatGrid">
            <?php
            $totalRows = (int)$screening['total_rows'];
            $seatsPerRow = (int)$screening['seats_per_row'];
            $premiumRowStart = (int)$screening['premium_row_start'];

            for ($r = 1; $r <= $totalRows; $r++) {
                $rowLetter = chr(64 + $r);
                $isPremiumRow = ($r >= $premiumRowStart);
                echo '<div class="seat-row">';
                echo '<span class="seat-row-label">' . $rowLetter . '</span>';

                for ($c = 1; $c <= $seatsPerRow; $c++) {
                    $seatLabel = $rowLetter . $c;
                    $isBooked = in_array($seatLabel, $bookedSeats);
                    $seatClass = 'seat-btn';
                    
                    if ($isBooked) {
                        $seatClass .= ' seat-booked';
                    } elseif ($isPremiumRow) {
                        $seatClass .= ' seat-premium';
                    }

                    $seatPrice = $isPremiumRow ? $screening['premium_price'] : $screening['standard_price'];
                    $seatType = $isPremiumRow ? 'premium' : 'standard';

                    echo '<button type="button" class="' . $seatClass . '" ' . 
                         ($isBooked ? 'disabled title="Seat ' . $seatLabel . ' (Booked)"' : '') . 
                         ' data-seat="' . $seatLabel . '" ' .
                         ' data-type="' . $seatType . '" ' .
                         ' data-price="' . $seatPrice . '" ' .
                         ' title="' . $seatLabel . ' - ' . ucfirst($seatType) . ' ($' . number_format($seatPrice, 2) . ')">' . 
                         $c . 
                         '</button>';
                }

                echo '<span class="seat-row-label">' . $rowLetter . '</span>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- Seat Legend -->
        <div class="seat-legend">
            <div class="legend-item">
                <span class="legend-sample" style="background:#1e293b; border:1px solid #475569;"></span>
                <span>Standard (\$<?php echo number_format($screening['standard_price'], 2); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="legend-sample" style="background:#2a2414; border:1px solid #d4af37;"></span>
                <span>Premium Recliner (\$<?php echo number_format($screening['premium_price'], 2); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="legend-sample" style="background:#f2ca50; border:1px solid #f2ca50;"></span>
                <span>Selected</span>
            </div>
            <div class="legend-item">
                <span class="legend-sample" style="background:#181b22; border:1px solid #242938; text-decoration:line-through; opacity:0.6;"></span>
                <span>Booked / Occupied</span>
            </div>
        </div>
    </div>

    <!-- Right Column: Glassmorphic Booking Summary & Preference Form -->
    <div class="form-card" style="max-width: 100%; margin: 0; padding: 24px; position: sticky; top: 90px;">
        <h3 style="font-size: 20px; color: var(--color-primary-light); margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
            Booking Summary
        </h3>

        <div style="margin-bottom: 16px;">
            <p style="font-size: 13px; color: var(--color-text-muted); margin-bottom: 4px;">Selected Seats:</p>
            <div id="selectedSeatsBadgeWrap" style="min-height: 36px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                <span style="font-size: 13px; color: var(--color-text-dim); font-style: italic;" id="noSeatsPlaceholder">
                    No seats selected yet. Click on the map to pick your seats.
                </span>
            </div>
        </div>

        <div style="background: #121620; border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                <span>Tickets Subtotal:</span>
                <strong id="ticketsSubtotal">$0.00</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: var(--color-text-muted);">
                <span>Hall Surcharge:</span>
                <span>Included</span>
            </div>
            <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--color-border); padding-top: 8px; margin-top: 8px; font-size: 16px; color: var(--color-primary-light);">
                <strong>Estimated Total:</strong>
                <strong id="grandTotal" style="font-family: var(--font-heading);">$0.00</strong>
            </div>
        </div>

        <!-- Preference Ranking & Multi-Booking Form -->
        <form method="POST" action="booking.php?screening_id=<?php echo (int)$screeningId; ?>" id="bookingSubmitForm">
            <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="preference_rank">
                    <span>Preference Ranking <small style="color:var(--color-primary-light); font-weight:normal;">(For Wishlist)</small></span>
                </label>
                <select name="preference_rank" id="preference_rank" class="form-control" style="font-size: 13px;">
                    <option value="1">Preference #1 (Top Choice)</option>
                    <option value="2">Preference #2 (Alternative Option)</option>
                    <option value="3">Preference #3 (Backup Option)</option>
                </select>
                <small class="form-help-text">
                    Shortlisting allows you to compare multiple showtimes and confirm all your preferences together.
                </small>
            </div>

            <!-- Action Buttons: Add to Wishlist VS Direct Checkout -->
            <button type="submit" name="booking_action" value="add_wishlist" class="btn btn--outline btn--block" style="margin-bottom: 10px;">
                📋 Add to Booking Wishlist
            </button>
            <button type="submit" name="booking_action" value="direct_checkout" class="btn btn--primary btn--block btn--lg">
                Proceed to Checkout &rarr;
            </button>
        </form>
    </div>
</div>

<!-- Interactive Seat Selection Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const seatButtons = document.querySelectorAll('.seat-btn:not(.seat-booked)');
    const selectedSeatsInput = document.getElementById('selectedSeatsInput');
    const badgeWrap = document.getElementById('selectedSeatsBadgeWrap');
    const placeholder = document.getElementById('noSeatsPlaceholder');
    const subtotalDisplay = document.getElementById('ticketsSubtotal');
    const grandTotalDisplay = document.getElementById('grandTotal');
    const form = document.getElementById('bookingSubmitForm');

    let selectedSeats = [];

    seatButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const seatLabel = this.getAttribute('data-seat');
            const seatPrice = parseFloat(this.getAttribute('data-price'));
            const seatType = this.getAttribute('data-type');

            const index = selectedSeats.findIndex(s => s.label === seatLabel);
            if (index > -1) {
                // Deselect seat
                selectedSeats.splice(index, 1);
                this.classList.remove('seat-selected');
            } else {
                // Select seat
                selectedSeats.push({ label: seatLabel, price: seatPrice, type: seatType });
                this.classList.add('seat-selected');
            }

            updateSummaryUI();
        });
    });

    function updateSummaryUI() {
        if (selectedSeats.length === 0) {
            badgeWrap.innerHTML = '';
            badgeWrap.appendChild(placeholder);
            placeholder.style.display = 'block';
            subtotalDisplay.textContent = '$0.00';
            grandTotalDisplay.textContent = '$0.00';
            selectedSeatsInput.value = '';
            return;
        }

        placeholder.style.display = 'none';
        badgeWrap.innerHTML = '';
        let total = 0;
        let seatLabelsArr = [];

        selectedSeats.forEach(s => {
            total += s.price;
            seatLabelsArr.push(s.label);

            const badge = document.createElement('span');
            badge.className = 'pill-tag';
            badge.style.background = s.type === 'premium' ? '#d4af37' : '#334155';
            badge.style.color = s.type === 'premium' ? '#0a0e17' : '#ffffff';
            badge.style.fontWeight = 'bold';
            badge.textContent = s.label + ' ($' + s.price.toFixed(2) + ')';
            badgeWrap.appendChild(badge);
        });

        subtotalDisplay.textContent = '$' + total.toFixed(2);
        grandTotalDisplay.textContent = '$' + total.toFixed(2);
        selectedSeatsInput.value = seatLabelsArr.join(',');
    }

    form.addEventListener('submit', function (e) {
        if (selectedSeats.length === 0) {
            e.preventDefault();
            alert('Please select at least one seat on the seat map before continuing.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
