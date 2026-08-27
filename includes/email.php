<?php
/**
 * Silver Village Cinema - Local Server Email Helper
 * Sends booking acknowledgements and receipts to local web server mailbox
 * and stores an archived HTML receipt log in /sent_emails/ for verification.
 */

function sendBookingAcknowledgement($bookingRef, $recipientEmail, $customerName, $bookingsList, $grandTotal) {
    $subject = "Booking Confirmation & E-Tickets: " . htmlspecialchars($bookingRef) . " - Silver Village Cinema";
    
    // Construct HTML Email Content
    $itemsHtml = '';
    foreach ($bookingsList as $b) {
        $movieTitle = htmlspecialchars($b['title'] ?? 'Movie');
        $hallName = htmlspecialchars($b['hall_name'] ?? 'Hall');
        $showDate = date('D, d M Y', strtotime($b['screening_date']));
        $showTime = date('h:i A', strtotime($b['screening_time']));
        $seats = htmlspecialchars($b['seats'] ?? '');
        $price = number_format((float)($b['price'] ?? 0), 2);

        $itemsHtml .= "
        <div style='background:#1c1f29; border:1px solid #d4af37; border-radius:8px; padding:16px; margin-bottom:12px; color:#dfe2ef;'>
            <h3 style='margin:0 0 8px 0; color:#f2ca50; font-family:serif;'>🎬 $movieTitle</h3>
            <p style='margin:4px 0;'><strong>Hall:</strong> $hallName &nbsp;|&nbsp; <strong>Date:</strong> $showDate &nbsp;|&nbsp; <strong>Time:</strong> $showTime</p>
            <p style='margin:4px 0;'><strong>Reserved Seats:</strong> <span style='background:#262a34; padding:2px 8px; border-radius:4px; color:#f2ca50; font-weight:bold;'>$seats</span></p>
            <p style='margin:4px 0;'><strong>Subtotal:</strong> \$$price</p>
        </div>";
    }

    $formattedTotal = number_format((float)$grandTotal, 2);
    $currentDate = date('d M Y, h:i A');

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Silver Village Cinema - Booking Acknowledgement</title>
    </head>
    <body style='margin:0; padding:20px; background-color:#0a0e17; font-family:Inter, Arial, sans-serif; color:#dfe2ef;'>
        <div style='max-width:640px; margin:0 auto; background:#0f131c; border:1px solid rgba(212,175,55,0.3); border-radius:12px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.8);'>
            <div style='background:linear-gradient(135deg, #1c1f29 0%, #0a0e17 100%); padding:28px 24px; text-align:center; border-bottom:2px solid #d4af37;'>
                <h1 style='margin:0; color:#f2ca50; font-family:serif; letter-spacing:1px; font-size:26px;'>SILVER VILLAGE CINEMA</h1>
                <p style='margin:6px 0 0 0; color:#d0c5af; font-size:14px; letter-spacing:2px; text-transform:uppercase;'>Official Booking Acknowledgement</p>
            </div>
            
            <div style='padding:24px;'>
                <p style='font-size:16px; margin-top:0;'>Dear <strong>" . htmlspecialchars($customerName) . "</strong>,</p>
                <p style='color:#d0c5af;'>Thank you for booking with Silver Village Cinema. Your ticket reservation has been confirmed and paid. Please present this e-ticket at the cinema entrance.</p>
                
                <div style='background:#181b25; border-left:4px solid #f2ca50; padding:12px 16px; margin:20px 0; border-radius:0 8px 8px 0;'>
                    <p style='margin:2px 0; font-size:13px; color:#99907c;'>BOOKING REFERENCE</p>
                    <p style='margin:0; font-size:20px; font-weight:bold; color:#f2ca50; font-family:monospace;'>$bookingRef</p>
                    <p style='margin:4px 0 0 0; font-size:12px; color:#99907c;'>Issued on $currentDate</p>
                </div>

                <h3 style='color:#ffffff; margin:24px 0 12px 0; border-bottom:1px solid #31353f; padding-bottom:8px;'>Ticket Details</h3>
                $itemsHtml

                <div style='background:#181b25; padding:16px; border-radius:8px; margin-top:20px; text-align:right;'>
                    <span style='color:#d0c5af; font-size:15px;'>Grand Total Paid: </span>
                    <span style='color:#f2ca50; font-size:22px; font-weight:bold; font-family:serif;'>\$$formattedTotal</span>
                </div>

                <div style='margin-top:28px; padding-top:16px; border-top:1px solid #31353f; font-size:12px; color:#99907c; text-align:center;'>
                    <p style='margin:4px 0;'>Silver Village Cinema • 50 Nanyang Avenue, Singapore 639798</p>
                    <p style='margin:4px 0;'>Automated Local Server Dispatch to: <code>" . htmlspecialchars($recipientEmail) . "</code></p>
                </div>
            </div>
        </div>
    </body>
    </html>";

    // Headers for HTML email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: Silver Village Cinema <noreply@silvervillage.local>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // 1. Attempt PHP mail() to local SMTP (Mercury / Mailhog / XAMPP)
    @mail($recipientEmail, $subject, $message, $headers);

    // 2. Save a local archived copy in /sent_emails/ for offline evaluation & testing
    $archiveDir = __DIR__ . '/../sent_emails';
    if (!is_dir($archiveDir)) {
        @mkdir($archiveDir, 0777, true);
    }
    $filename = $archiveDir . '/booking_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $bookingRef) . '.html';
    @file_put_contents($filename, $message);

    return true;
}
?>
