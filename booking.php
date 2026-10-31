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
           m.movie_id, m.title AS movie_title, m.rating, m.duration_minutes, m.genre, m.poster_image, m.synopsis,
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

// Validate seat selections again on the server, including edits.
require_once __DIR__.'/includes/booking_service.php';
$editId=(int)($_POST['wishlist_id']??$_GET['wishlist_id']??0);
$editing=null; $initialSeats=[]; $errorMsg='';
if($editId) {
    $editing=cinemaQuery($conn,'SELECT * FROM booking_wishlist WHERE wishlist_id=? AND user_id=?','ii',[$editId,getCurrentUserId()])->get_result()->fetch_assoc();
    if(!$editing || (int)$editing['screening_id']!==$screeningId) { http_response_code(404); exit('Wishlist item not found in your account.'); }
    $initialSeats=explode(',',$editing['selected_seats']);
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $text=trim($_POST['selected_seats']??'');
        $action=$_POST['booking_action']??'';
        $info=screeningInfo($conn,$screeningId);
        $seats=validateSeats($text,$info);
        ensureSeatsAvailable($conn,$screeningId,$seats);
        if($action==='add_wishlist') {
            $rank=filter_var($_POST['preference_rank']??'',FILTER_VALIDATE_INT);
            if($rank===false) throw new RuntimeException('Choose a valid preference.');
            saveWishlist($conn,getCurrentUserId(),$screeningId,$text,$rank,$editId);
            header('Location: wishlist.php?added=1',true,303); exit;
        } elseif($action==='direct_checkout') {
            $token=bin2hex(random_bytes(20));
            $_SESSION['pending_checkouts'][$token]=['selection'=>['mode'=>'direct','screening_id'=>$screeningId,'seats'=>implode(',',$seats)],'expires'=>time()+1200];
            $_SESSION['pending_checkouts']=array_slice($_SESSION['pending_checkouts'],-10,null,true);
            header('Location: confirmation.php?checkout='.$token,true,303); exit;
        } else throw new RuntimeException('Choose a valid booking action.');
    } catch(Throwable $e) { $errorMsg=userError($e); }
}
if($_SERVER['REQUEST_METHOD']==='POST') $initialSeats=explode(',',(string)($_POST['selected_seats']??''));
try { screeningInfo($conn,$screeningId); } catch(Throwable $e) { $errorMsg=userError($e); }

$pageTitle = "Select Seats: " . htmlspecialchars($screening['movie_title']) . " - CinéPop";
require_once __DIR__ . '/includes/header.php';
?>

<?php
$bookingPoster='images/posters/'.$screening['poster_image'];
$bookingBackdrop=((int)$screening['movie_id']===1 && is_file(__DIR__.'/images/hero_spiderman.jpg'))?'images/hero_spiderman.jpg':$bookingPoster;
?>
<section class="seat-booking-scene" style="--booking-backdrop:url('<?= htmlspecialchars(cinemaUrl($bookingBackdrop),ENT_QUOTES,'UTF-8') ?>')">
    <div class="booking-layout cinema-seat-layout">
        <div class="seat-booking-main">
            <a class="seat-back-link" href="movie_details.php?id=<?= (int)$screening['movie_id'] ?>">← Back to movie details</a>
            <header class="seat-film-header">
                <h1><?= htmlspecialchars($screening['movie_title']) ?></h1>
                <div class="seat-film-meta"><span class="pill-rating"><?= htmlspecialchars($screening['rating']) ?></span><span><?= (int)$screening['duration_minutes'] ?> Mins</span><span><?= htmlspecialchars($screening['genre']) ?></span><span><?= htmlspecialchars($screening['experience_type']) ?></span></div>
                <p class="seat-film-synopsis"><?= htmlspecialchars($screening['synopsis']) ?></p>
                <h2 class="seat-step-title">Select Seats & Booking Options</h2>
            </header>
            <dl class="seat-screening-details">
                <div><dt>Cinema hall</dt><dd><?= htmlspecialchars($screening['hall_name']) ?></dd></div>
                <div><dt>Date</dt><dd><?= date('D, d M Y',strtotime($screening['screening_date'])) ?></dd></div>
                <div><dt>Showtime</dt><dd><?= date('h:i A',strtotime($screening['screening_time'])) ?></dd></div>
            </dl>
            <?php if($errorMsg): ?><div class="alert alert--danger" role="alert"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>
            <div class="screen-stage-wrap cinematic-seat-map">
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
                    if ($c === (int)ceil($seatsPerRow/2)) $seatClass .= ' seat-aisle-end';
                    
                    if ($isBooked) {
                        $seatClass .= ' seat-booked';
                    } elseif ($isPremiumRow) {
                        $seatClass .= ' seat-premium';
                    }

                    $seatPrice = $isPremiumRow ? $screening['premium_price'] : $screening['standard_price'];
                    $seatType = $isPremiumRow ? 'premium' : 'standard';

                    echo '<button type="button" class="' . $seatClass . '" ' . 
                         ($isBooked ? 'disabled' : '') . ' aria-pressed="false" aria-label="Seat ' . $seatLabel . ', ' . ucfirst($seatType) . ', $' . number_format($seatPrice, 2) . ($isBooked ? ', booked' : ', available') . '"' . 
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
                <span>Standard ($<?php echo number_format($screening['standard_price'], 2); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="legend-sample" style="background:#103b68; border:1px solid #0068c8;"></span>
                <span>Premium Recliner ($<?php echo number_format($screening['premium_price'], 2); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="legend-sample" style="background:#70c9ff; border:1px solid #70c9ff;"></span>
                <span>Selected</span>
            </div>
            <div class="legend-item">
                <span class="legend-sample" style="background:#181b22; border:1px solid #242938; text-decoration:line-through; opacity:0.6;"></span>
                <span>Booked / Occupied</span>
            </div>
        </div>
    </div>

        </div>
    <!-- Poster, selected seats, pricing and original booking actions. -->
    <aside class="form-card seat-booking-sidebar">
        <a class="seat-poster-link" href="movie_details.php?id=<?= (int)$screening['movie_id'] ?>"><img src="<?= htmlspecialchars($bookingPoster) ?>" alt="<?= htmlspecialchars($screening['movie_title']) ?> poster" class="seat-film-poster"></a>
        <h3 style="font-size: 20px; color: var(--color-primary-light); margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
            Booking Summary
        </h3>

        <div style="margin-bottom: 16px;">
            <p style="font-size: 13px; color: var(--color-text-muted); margin-bottom: 4px;">Selected Seats:</p>
            <div id="selectedSeatsBadgeWrap" class="seat-selection-list" aria-live="polite">
                <span style="font-size: 13px; color: var(--color-text-dim); font-style: italic;" id="noSeatsPlaceholder">
                    No seats selected yet. Click on the map to pick your seats.
                </span>
            </div>
        </div>

        <div style="background: #0b2544; border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; font-size: 13px;">
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
        <form method="POST" action="booking.php?screening_id=<?php echo (int)$screeningId; ?>" id="bookingSubmitForm"><?= csrfField() ?><input type="hidden" name="wishlist_id" value="<?= $editId ?>">
            <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="preference_rank">
                    <span>Preference Ranking <small style="color:var(--color-primary-light); font-weight:normal;">(For Wishlist)</small></span>
                </label>
                <select name="preference_rank" id="preference_rank" class="form-control" style="font-size: 13px;">
                    <option value="1" <?= $editing && (int)$editing['preference_rank']===1 ? 'selected' : '' ?>>Preference #1 (Top Choice)</option>
                    <option value="2" <?= $editing && (int)$editing['preference_rank']===2 ? 'selected' : '' ?>>Preference #2 (Alternative Option)</option>
                    <option value="3" <?= $editing && (int)$editing['preference_rank']>=3 ? 'selected' : '' ?>>Preference #3 (Backup Option)</option>
                </select>
                <small class="form-help-text">
                    Shortlisting allows you to compare multiple showtimes and confirm all your preferences together.
                </small>
            </div>

            <!-- Action Buttons: Add to Wishlist VS Direct Checkout -->
            <button type="submit" name="booking_action" value="add_wishlist" class="btn btn--outline btn--block" style="margin-bottom: 10px;">
                <?= $editing ? 'Save wishlist changes' : 'Add to Booking Wishlist' ?>
            </button>
            <button type="submit" name="booking_action" value="direct_checkout" class="btn btn--primary btn--block btn--lg">
                Proceed to Checkout &rarr;
            </button>
        </form>
    </aside>
</div>
</section>

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
                this.setAttribute('aria-pressed', 'false');
            } else {
                // Select seat
                selectedSeats.push({ label: seatLabel, price: seatPrice, type: seatType });
                this.classList.add('seat-selected');
                this.setAttribute('aria-pressed', 'true');
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

            const row = document.createElement('div');
            row.className = 'seat-selection-row';
            const description = document.createElement('span');
            description.textContent = s.label + ' · ' + (s.type === 'premium' ? 'Premium Recliner' : 'Standard');
            const price = document.createElement('strong');
            price.textContent = '$' + s.price.toFixed(2);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'seat-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove seat ' + s.label);
            remove.addEventListener('click', function () {
                const seatButton = [...seatButtons].find(button => button.getAttribute('data-seat') === s.label);
                if (seatButton) seatButton.click();
            });
            row.append(description, price, remove);
            badgeWrap.appendChild(row);
        });

        subtotalDisplay.textContent = '$' + total.toFixed(2);
        grandTotalDisplay.textContent = '$' + total.toFixed(2);
        selectedSeatsInput.value = seatLabelsArr.join(',');
    }

    const initialLabels = [<?php echo implode(',', array_map(fn($seat) => "'" . htmlspecialchars($seat, ENT_QUOTES, 'UTF-8') . "'", array_filter($initialSeats, fn($s) => preg_match('/^[A-Z][1-9][0-9]?$/D', $s)))); ?>];
    seatButtons.forEach(btn => { if (initialLabels.includes(btn.getAttribute('data-seat'))) btn.click(); });

    form.addEventListener('submit', function (e) {
        if (selectedSeats.length === 0) {
            e.preventDefault();
            alert('Please select at least one seat on the seat map before continuing.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
