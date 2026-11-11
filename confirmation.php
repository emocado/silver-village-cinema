<?php
require_once __DIR__.'/includes/db_connect.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/booking_service.php';
require_once __DIR__.'/includes/email.php';
require_once __DIR__.'/includes/payment.php';
requireLogin();
$error=''; $pending=null; $receipt=null; $paymentErrors=[];
try {
    if($_SERVER['REQUEST_METHOD']==='POST') {
        requireCsrf();
        if(($_POST['action']??'')==='pay') {
            $token=is_string($_POST['checkout_token']??null)?$_POST['checkout_token']:'';
            // A repeated POST redirects to the existing receipt rather than charging twice.
            if(isset($_SESSION['checkout_receipts'][$token])) { header('Location: confirmation.php?receipt='.urlencode($token),true,303); exit; }
            $pending=$_SESSION['pending_checkouts'][$token]??null;
            if(!$pending || $pending['expires']<time()) throw new RuntimeException('This checkout expired. Return to your wishlist and try again.');
            $payment=validatePayment($_POST);
            $paymentErrors=$payment['errors'];
            if(!$paymentErrors) {
            $result=completeCheckout($conn,getCurrentUserId(),$pending['selection'],$payment['outcome']);
            $result['celebrate'] = $result['outcome'] === 'success';
            $result['mail']=$result['outcome']==='success'?sendBookingAcknowledgement($result['reference'],$_SESSION['user_email'],getCurrentUserName(),$result['bookings'],$result['total']):null;
            $_SESSION['checkout_receipts'][$token]=$result;
            unset($_SESSION['pending_checkouts'][$token]);
            $_SESSION['checkout_receipts']=array_slice($_SESSION['checkout_receipts'],-10,null,true);
            header('Location: confirmation.php?receipt='.urlencode($token),true,303); exit;
            }
        } else {
        $ids=$_POST['selected_wishlist_ids']??null;
        if(!is_array($ids) || !$ids) throw new RuntimeException('Select at least one wishlist item.');
        foreach($ids as $id) if(!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1) throw new RuntimeException('Invalid wishlist selection.');
        $selection=['mode'=>'wishlist','ids'=>array_map('intval',$ids)];
        checkoutItems($conn,getCurrentUserId(),$selection);
        $token=bin2hex(random_bytes(20));
        $_SESSION['pending_checkouts'][$token]=['selection'=>$selection,'expires'=>time()+1200];
        $_SESSION['pending_checkouts']=array_slice($_SESSION['pending_checkouts'],-10,null,true);
        header('Location: confirmation.php?checkout='.$token,true,303); exit;
        }
    }
    $token=(string)($_POST['checkout_token']??$_GET['checkout']??'');
    $pending=$_SESSION['pending_checkouts'][$token]??null;
    if($pending) {
        if($pending['expires']<time()) throw new RuntimeException('This checkout expired. Please select your bookings again.');
        $preview=checkoutItems($conn,getCurrentUserId(),$pending['selection']);
    }
    $receiptToken = (string) ($_GET['receipt'] ?? '');
    $receipt = $_SESSION['checkout_receipts'][$receiptToken] ?? null;
    if (!empty($receipt['celebrate'])) {
        unset($_SESSION['checkout_receipts'][$receiptToken]['celebrate']);
    }
} catch(Throwable $e) { $error=userError($e); }
$pageTitle='Checkout - CinéPop';
require __DIR__.'/includes/header.php';
?>
<section class="checkout-content">
<?php if($error): ?>
    <h1>Booking needs your attention</h1>
    <div class="alert alert--danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <a href="wishlist.php" class="btn btn--primary">Return to Wishlist</a>
<?php elseif($receipt && $receipt['outcome']==='success'): ?>
    <link rel="stylesheet" href="css/booking-receipt.css">
    <div class="booking-receipt">
        <div class="receipt-heading">
            <span class="receipt-check" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 4 4L19 6"/></svg>
            </span>
            <h1>Booking confirmed</h1>
        </div>
        <p class="receipt-intro">Payment received. Your seats are ready for movie night.</p>
        <?php if (!empty($receipt['celebrate'])): ?>
            <link rel="stylesheet" href="css/booking-celebration.css" media="(prefers-reduced-motion: no-preference)">
            <div class="booking-celebration" aria-hidden="true" hidden>
                <?php foreach ([-43, -38, -33, -28, -22, -15, -8, 8, 15, 22, 28, 33, 38, 43] as $index => $spread): ?>
                    <span style="--spread: <?= $spread ?>vw; --delay: <?= ($index % 4) * 65 + 100 ?>ms; --spin: <?= $spread * 8 ?>deg; --size: <?= 16 + ($index % 4) * 3 ?>px"></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php foreach ($receipt['bookings'] as $ticket): ?>
            <article class="receipt-ticket">
                <div class="receipt-ticket-main">
                    <h2><?= htmlspecialchars($ticket['title']) ?></h2>
                    <p class="receipt-showtime"><?= htmlspecialchars(date('D, j M Y · g:i a', strtotime($ticket['screening_date'].' '.$ticket['screening_time']))) ?></p>
                    <dl class="receipt-details">
                        <div><dt>Cinema hall</dt><dd><?= htmlspecialchars($ticket['hall_name']) ?></dd></div>
                        <div><dt>Seats</dt><dd><?= htmlspecialchars(str_replace(',', ', ', $ticket['seats'])) ?></dd></div>
                        <?php if (count($receipt['bookings']) > 1): ?>
                            <div><dt>Ticket amount</dt><dd>$<?= number_format($ticket['price'], 2) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="receipt-reference">
                    <div>
                        <span>Booking reference</span>
                        <strong class="receipt-reference-value"><?= htmlspecialchars($ticket['booking_ref']) ?></strong>
                        <p>Show this reference at the cinema.</p>
                    </div>
                    <button type="button" class="btn btn--outline btn--sm receipt-copy" hidden aria-live="polite">Copy reference</button>
                </div>
            </article>
        <?php endforeach; ?>
        <div class="receipt-total"><span>Total paid</span><strong>SGD $<?= number_format($receipt['total'], 2) ?></strong></div>
        <?php if (!empty($receipt['mail']['accepted'])): ?>
            <p class="receipt-email" role="status">Your booking acknowledgement is on its way.</p>
        <?php else: ?>
            <p class="alert alert--warning receipt-email" role="status">Your seats are confirmed. Resend your email from My Bookings.</p>
        <?php endif; ?>
        <div class="receipt-actions">
            <a href="my_bookings.php" class="btn btn--primary">My Bookings</a>
            <a href="wishlist.php" class="btn btn--outline">My Wishlist</a>
        </div>
    </div>
    <script>
        document.querySelectorAll('.receipt-copy').forEach(button => {
            const reference = button.closest('.receipt-reference').querySelector('.receipt-reference-value');
            button.hidden = false;
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(reference.textContent);
                    button.textContent = 'Copied';
                } catch {
                    const range = document.createRange();
                    range.selectNodeContents(reference);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    button.textContent = 'Reference selected';
                }
            });
        });
    </script>
<?php elseif($receipt): ?>
    <h1>Your payment could not be completed</h1>
    <div class="alert alert--danger" role="status">Your card was declined and you haven't been charged. Your seats have not been reserved. Your wishlist choices are saved, so you can try again with another card.</div>
    <?php foreach($receipt['bookings'] as $ticket): ?>
    <article class="ticket-pass">
        <img class="ticket-mark" src="images/cinepop-mark.svg" alt="CinéPop">
        <h2><?= htmlspecialchars($ticket['title']) ?></h2>
        <p>Reference: <strong><?= htmlspecialchars($ticket['booking_ref']) ?></strong></p>
        <p><?= htmlspecialchars($ticket['screening_date'].' at '.$ticket['screening_time'].' · '.$ticket['hall_name']) ?></p>
        <p>Seats: <strong><?= htmlspecialchars($ticket['seats']) ?></strong> · $<?= number_format($ticket['price'],2) ?></p>
        <p>Status: Cancelled / Payment failed</p>
    </article>
    <?php endforeach; ?>
    <a href="my_bookings.php" class="btn btn--primary">My Bookings</a>
    <a href="wishlist.php" class="btn btn--outline">My Wishlist</a>
<?php elseif($pending): ?>
    <a class="checkout-back" href="wishlist.php">← Back to your wishlist</a>
    <h1>Make it a movie night.</h1>
    <p class="checkout-intro">Your seats are almost yours. Review your booking and complete your payment.</p>
    <div class="payment-layout">
        <div class="payment-main">
            <section class="checkout-bookings" aria-labelledby="booking-heading">
                <h2 id="booking-heading">Your tickets</h2>
                <?php $total=0; $ticketCount=0; foreach($preview as $item): $total+=$item['price']; $ticketCount+=count($item['seats_array']); ?>
                <article class="checkout-film">
                    <img src="images/posters/<?= htmlspecialchars($item['poster_image']) ?>" alt="<?= htmlspecialchars($item['title']) ?> poster">
                    <div><h3><?= htmlspecialchars($item['title']) ?></h3><p><?= date('D, j M Y',strtotime($item['screening_date'])) ?> · <?= date('g:i A',strtotime($item['screening_time'])) ?></p><p><?= htmlspecialchars($item['hall_name']) ?> · <?= htmlspecialchars($item['experience_type']) ?></p><p class="checkout-seats">Seats <?= htmlspecialchars(implode(', ',$item['seats_array'])) ?></p></div>
                    <strong>$<?= number_format($item['price'],2) ?></strong>
                </article>
                <?php endforeach; ?>
            </section>
            <section class="payment-details" aria-labelledby="payment-heading">
                <div class="payment-section-heading"><h2 id="payment-heading">Payment details</h2><span class="card-types">VISA <span>Mastercard</span></span></div>
                <p>Credit or debit card</p>
                <?php if($paymentErrors): ?><div class="alert alert--danger" role="alert">Please check the highlighted payment details.</div><?php endif; ?>
                <form method="post" action="confirmation.php" id="paymentForm">
                    <?= csrfField() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="checkout_token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group"><label class="form-label" for="cardholder">Name on card</label><input class="form-control<?= isset($paymentErrors['cardholder']) ? ' is-invalid' : '' ?>" aria-describedby="cardholder-error"<?= isset($paymentErrors['cardholder']) ? ' aria-invalid="true"' : '' ?> id="cardholder" name="cardholder" autocomplete="off" maxlength="100" placeholder="Full name as shown on card" value="<?= htmlspecialchars($_POST['cardholder']??'') ?>" required><p id="cardholder-error" class="form-error-msg" aria-live="polite"<?= isset($paymentErrors['cardholder']) ? ' style="display:block"' : '' ?>><?= htmlspecialchars($paymentErrors['cardholder'] ?? '') ?></p></div>
                    <div class="form-group"><label class="form-label" for="card_number">Card number</label><input class="form-control<?= isset($paymentErrors['card_number']) ? ' is-invalid' : '' ?>" aria-describedby="card_number-error"<?= isset($paymentErrors['card_number']) ? ' aria-invalid="true"' : '' ?> id="card_number" name="card_number" inputmode="numeric" autocomplete="off" maxlength="19" pattern="[0-9]{16}|[0-9]{4}(?: [0-9]{4}){3}" title="Enter exactly 16 digits" placeholder="1234 5678 9012 3456" required><p id="card_number-error" class="form-error-msg" aria-live="polite"<?= isset($paymentErrors['card_number']) ? ' style="display:block"' : '' ?>><?= htmlspecialchars($paymentErrors['card_number'] ?? '') ?></p></div>
                    <div class="payment-field-row"><div class="form-group"><label class="form-label" for="expiry">Expiry date</label><input class="form-control<?= isset($paymentErrors['expiry']) ? ' is-invalid' : '' ?>" aria-describedby="expiry-error"<?= isset($paymentErrors['expiry']) ? ' aria-invalid="true"' : '' ?> id="expiry" name="expiry" inputmode="numeric" autocomplete="off" maxlength="5" placeholder="MM/YY" value="<?= htmlspecialchars($_POST['expiry']??'') ?>" pattern="(0[1-9]|1[0-2])/[0-9]{2}" required><p id="expiry-error" class="form-error-msg" aria-live="polite"<?= isset($paymentErrors['expiry']) ? ' style="display:block"' : '' ?>><?= htmlspecialchars($paymentErrors['expiry'] ?? '') ?></p></div>
                    <div class="form-group"><label class="form-label" for="security_code">Security code</label><input class="form-control<?= isset($paymentErrors['security_code']) ? ' is-invalid' : '' ?>" aria-describedby="security_code-error"<?= isset($paymentErrors['security_code']) ? ' aria-invalid="true"' : '' ?> id="security_code" name="security_code" type="password" inputmode="numeric" autocomplete="off" maxlength="3" pattern="[0-9]{3}" placeholder="CVC" required><p id="security_code-error" class="form-error-msg" aria-live="polite"<?= isset($paymentErrors['security_code']) ? ' style="display:block"' : '' ?>><?= htmlspecialchars($paymentErrors['security_code'] ?? '') ?></p></div></div>
                </form>
            </section>
        </div>
        <aside class="payment-summary" aria-labelledby="summary-heading">
            <h2 id="summary-heading">Booking summary</h2>
            <dl><div><dt><?= $ticketCount ?> <?= $ticketCount===1?'ticket':'tickets' ?></dt><dd>$<?= number_format($total,2) ?></dd></div><div><dt>Booking fees</dt><dd>Included</dd></div></dl>
            <div class="payment-total"><span>Total to pay <small>SGD</small></span><strong>$<?= number_format($total,2) ?></strong></div>
            <button type="submit" form="paymentForm" class="btn btn--primary btn--block btn--lg" id="payButton">Pay $<?= number_format($total,2) ?></button>
            <p class="payment-summary-note">Your seats will be confirmed once payment is complete. A booking acknowledgement will follow by email.</p>
            <a href="wishlist.php" class="checkout-edit">Change your selection</a>
        </aside>
    </div>
    <script src="js/payment.js?v=demo-card-format"></script>
<?php else: ?>
    <h1>No booking selected</h1><p>Choose a screening or select bookings from your wishlist.</p>
    <a href="movies.php" class="btn btn--primary">Browse Movies</a>
<?php endif; ?>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
