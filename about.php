<?php
$pageTitle = "About Us & Contact - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';

$contactSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cName = trim($_POST['contact_name'] ?? '');
    $cEmail = trim($_POST['contact_email'] ?? '');
    $cSubject = trim($_POST['contact_subject'] ?? '');
    $cMessage = trim($_POST['contact_message'] ?? '');

    if (!empty($cName) && !empty($cEmail) && !empty($cMessage)) {
        $contactSuccess = true;
    }
}
?>

<div class="section-header" style="margin-bottom: 32px;">
    <div>
        <h1 class="section-title">About Silver Village Cinema</h1>
        <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
            Crafting world-class cinematic experiences with state-of-the-art projection, audio, and customer convenience.
        </p>
    </div>
</div>

<?php if ($contactSuccess): ?>
    <div class="alert alert--success">
        ✅ Thank you, <strong><?php echo htmlspecialchars($cName); ?></strong>! Your enquiry has been received by our cinema concierge desk.
    </div>
<?php endif; ?>

<!-- Cinema Halls Feature Cards Grid -->
<section style="margin-bottom: 48px;">
    <h2 style="font-size: 22px; margin-bottom: 20px; color: var(--color-primary-light);">Our Screening Auditoriums</h2>
    
    <div class="perks-grid">
        <div class="perk-card" style="flex-direction: column; background:#121620;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <span style="font-size: 28px;">🎭</span>
                <h3 class="perk-title" style="margin:0;">Hall A: Dolby Atmos</h3>
            </div>
            <p class="perk-desc">
                <strong>Capacity:</strong> 60 Seats (Rows A–F)<br>
                <strong>Projection:</strong> Christie 4K Dual Laser<br>
                <strong>Sound:</strong> 64-channel Dolby Atmos Overhead Array<br>
                <strong>Tiers:</strong> Standard (\$10.50) / Premium Recliner (\$14.50)
            </p>
        </div>

        <div class="perk-card" style="flex-direction: column; background:#121620;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <span style="font-size: 28px;">👑</span>
                <h3 class="perk-title" style="margin:0;">Hall B: VIP Premiere</h3>
            </div>
            <p class="perk-desc">
                <strong>Capacity:</strong> 80 Seats (Rows A–H)<br>
                <strong>Projection:</strong> Laser Ultra-High Definition<br>
                <strong>Seating:</strong> Motorized Italian Leather Recliners<br>
                <strong>Tiers:</strong> Standard (\$12.50) / Premium VIP (\$16.50)
            </p>
        </div>

        <div class="perk-card" style="flex-direction: column; background:#121620;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <span style="font-size: 28px;">🎬</span>
                <h3 class="perk-title" style="margin:0;">Hall C: Digital Cinema</h3>
            </div>
            <p class="perk-desc">
                <strong>Capacity:</strong> 100 Seats (Rows A–J)<br>
                <strong>Projection:</strong> Barco Laser 2K High Brightness<br>
                <strong>Sound:</strong> 7.1 Surround Sound<br>
                <strong>Tiers:</strong> Standard (\$10.50) / Premium (\$14.50)
            </p>
        </div>
    </div>
</section>

<!-- Location & Contact Grid -->
<div class="booking-layout">
    <!-- Left Column: Contact Details & Operating Hours -->
    <div>
        <h2 style="font-size: 22px; color: #ffffff; margin-bottom: 16px;">Cinema Location & Hours</h2>
        
        <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px;">
            <p style="font-size: 15px; margin-bottom: 12px;">
                📍 <strong>Address:</strong><br>
                Silver Village Cinema Multiplex<br>
                50 Nanyang Avenue, Academic Complex #02-18<br>
                Singapore 639798
            </p>
            <p style="font-size: 14px; margin-bottom: 12px;">
                📞 <strong>Box Office Hotline:</strong> +65 6791 1744
            </p>
            <p style="font-size: 14px; margin-bottom: 12px;">
                ⏰ <strong>Operating Hours:</strong><br>
                Monday – Thursday: 10:30 AM – 11:30 PM<br>
                Friday – Sunday & Public Holidays: 09:30 AM – 01:00 AM
            </p>
            <div class="footer-notice-box">
                <small>🎟️ <strong>Digital Admission:</strong> E-tickets and booking summaries are dispatched immediately to your registered email address upon checkout.</small>
            </div>
        </div>
    </div>

    <!-- Right Column: Contact Us Form -->
    <div class="form-card" style="max-width: 100%; margin: 0; padding: 28px;">
        <h3 style="font-size: 20px; color: var(--color-primary-light); margin-bottom: 6px;">
            Send Us an Enquiry
        </h3>
        <p style="font-size: 13px; color: var(--color-text-muted); margin-bottom: 18px;">
            Have questions regarding private hall bookings, concession menus, or your tickets?
        </p>

        <form id="contactForm" method="POST" action="about.php" novalidate>
            <div class="form-group">
                <label class="form-label" for="contact_name">Your Full Name</label>
                <input type="text" id="contact_name" name="contact_name" class="form-control" placeholder="e.g. Johnathan Tan" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="contact_email">Email Address</label>
                <input type="email" id="contact_email" name="contact_email" class="form-control" placeholder="user@silvervillage.local" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="contact_subject">Subject</label>
                <input type="text" id="contact_subject" name="contact_subject" class="form-control" placeholder="e.g. Corporate Hall Booking Enquiry">
            </div>

            <div class="form-group">
                <label class="form-label" for="contact_message">Your Message</label>
                <textarea id="contact_message" name="contact_message" class="form-control" rows="4" placeholder="How can we assist you?" required></textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--block">
                Send Message &rarr;
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
