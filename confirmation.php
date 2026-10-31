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
    $receipt=$_SESSION['checkout_receipts'][(string)($_GET['receipt']??'')]??null;
} catch(Throwable $e) { $error=userError($e); }
$pageTitle='Checkout - CinéPop';
require __DIR__.'/includes/header.php';
?>
<section class="checkout-content">
<?php if($error): ?>
    <h1>Booking needs your attention</h1>
    <div class="alert alert--danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <a href="wishlist.php" class="btn btn--primary">Return to Wishlist</a>
<?php elseif($receipt): ?>
    <h1><?= $receipt['outcome']==='success'?"You're going to the movies.":'Your payment could not be completed' ?></h1>
    <?php if($receipt['outcome']==='success'): ?>
        <p>Payment received. Your seats are confirmed — we look forward to seeing you.</p>
        <div class="alert <?= $receipt['mail']['accepted']?'alert--success':'alert--warning' ?>" role="status">
            <?= $receipt['mail']['accepted']?'Your booking acknowledgement is on its way. Keep your booking reference for entry.':"Your tickets are confirmed, but we couldn't send your email. You can resend it from My Bookings." ?>
        </div>
    <?php else: ?>
        <div class="alert alert--danger" role="status">Your card was declined and you haven't been charged. Your seats have not been reserved. Your wishlist choices are saved, so you can try again with another card.</div>
    <?php endif; ?>
    <?php foreach($receipt['bookings'] as $ticket): ?>
    <article class="ticket-pass">
        <img class="ticket-mark" src="images/cinepop-mark.svg" alt="CinéPop">
        <h2><?= htmlspecialchars($ticket['title']) ?></h2>
        <p>Reference: <strong><?= htmlspecialchars($ticket['booking_ref']) ?></strong></p>
        <p><?= htmlspecialchars($ticket['screening_date'].' at '.$ticket['screening_time'].' · '.$ticket['hall_name']) ?></p>
        <p>Seats: <strong><?= htmlspecialchars($ticket['seats']) ?></strong> · $<?= number_format($ticket['price'],2) ?></p>
        <p>Status: <?= $receipt['outcome']==='success'?'Confirmed / Paid':'Cancelled / Payment failed' ?></p>
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
                    <div class="form-group"><label class="form-label" for="cardholder">Name on card</label><input class="form-control" id="cardholder" name="cardholder" autocomplete="off" maxlength="100" placeholder="Full name as shown on card" value="<?= htmlspecialchars($_POST['cardholder']??'') ?>" required><?php if(isset($paymentErrors['cardholder'])): ?><p class="payment-error"><?= htmlspecialchars($paymentErrors['cardholder']) ?></p><?php endif; ?></div>
                    <div class="form-group"><label class="form-label" for="card_number">Card number</label><input class="form-control" id="card_number" name="card_number" inputmode="numeric" autocomplete="off" maxlength="23" placeholder="1234  5678  9012  3456" required><?php if(isset($paymentErrors['card_number'])): ?><p class="payment-error"><?= htmlspecialchars($paymentErrors['card_number']) ?></p><?php endif; ?></div>
                    <div class="payment-field-row"><div class="form-group"><label class="form-label" for="expiry">Expiry date</label><input class="form-control" id="expiry" name="expiry" inputmode="numeric" autocomplete="off" maxlength="5" placeholder="MM/YY" value="<?= htmlspecialchars($_POST['expiry']??'') ?>" pattern="(0[1-9]|1[0-2])/[0-9]{2}" required><?php if(isset($paymentErrors['expiry'])): ?><p class="payment-error"><?= htmlspecialchars($paymentErrors['expiry']) ?></p><?php endif; ?></div>
                    <div class="form-group"><label class="form-label" for="security_code">Security code</label><input class="form-control" id="security_code" name="security_code" type="password" inputmode="numeric" autocomplete="off" maxlength="4" pattern="[0-9]{3,4}" placeholder="CVC" required><?php if(isset($paymentErrors['security_code'])): ?><p class="payment-error"><?= htmlspecialchars($paymentErrors['security_code']) ?></p><?php endif; ?></div></div>
                </form>
                <p class="payment-demo-note">Demo checkout · use test card details. No charge will be made.</p>
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
    <script src="js/payment.js"></script>
<?php else: ?>
    <h1>No booking selected</h1><p>Choose a screening or select bookings from your wishlist.</p>
    <a href="movies.php" class="btn btn--primary">Browse Movies</a>
<?php endif; ?>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
