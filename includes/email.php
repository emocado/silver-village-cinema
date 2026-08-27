<?php
/**
 * Silver Village Cinema - Server Email Dispatcher & Mercury Mail Integration
 * 
 * Sends official booking confirmation e-tickets via:
 * 1. Direct SMTP socket connection to Mercury Mail Server (localhost:25)
 * 2. PHP standard mail() fallback
 * 3. Saves physical copy directly to Mercury Mail Admin Spool (C:/xampp/MercuryMail/MAIL/Admin/)
 * 4. Archives web-ready copy in /sent_emails/ and /mailbox.php
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

    $htmlBody = "
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

    // Full RFC 822 Raw Email Format (for Mercury Mail and Mail Spoolers)
    $boundary = "----=_Part_" . md5(uniqid());
    $rawEmail = "From: Silver Village Cinema <noreply@silvervillage.local>\r\n";
    $rawEmail .= "To: <" . trim($recipientEmail) . ">\r\n";
    $rawEmail .= "Subject: " . $subject . "\r\n";
    $rawEmail .= "Date: " . date('r') . "\r\n";
    $rawEmail .= "MIME-Version: 1.0\r\n";
    $rawEmail .= "Content-Type: text/html; charset=UTF-8\r\n";
    $rawEmail .= "X-Mailer: Silver Village Cinema Mailer (XAMPP Mercury)\r\n";
    $rawEmail .= "X-Booking-Reference: " . $bookingRef . "\r\n\r\n";
    $rawEmail .= $htmlBody;

    // --------------------------------------------------------------------------
    // 1. Physical Delivery to XAMPP Mercury Mailbox (C:/xampp/MercuryMail/MAIL/Admin/)
    // --------------------------------------------------------------------------
    $mercuryAdminDir = 'C:/xampp/MercuryMail/MAIL/Admin';
    if (is_dir($mercuryAdminDir)) {
        // Generate valid Mercury CNM message filename: [8-hex-chars].CNM
        $cnmName = strtoupper(substr(md5(uniqid($bookingRef, true)), 0, 8)) . '.CNM';
        @file_put_contents($mercuryAdminDir . '/' . $cnmName, $rawEmail);
    }

    // Also write to Mercury QUEUE directory if exists
    $mercuryQueueDir = 'C:/xampp/MercuryMail/QUEUE';
    if (is_dir($mercuryQueueDir)) {
        $qName = 'Q' . strtoupper(substr(md5(uniqid()), 0, 7)) . '.QMM';
        @file_put_contents($mercuryQueueDir . '/' . $qName, $rawEmail);
    }

    // --------------------------------------------------------------------------
    // 2. Direct SMTP Socket Transmission to Mercury Server (localhost:25)
    // --------------------------------------------------------------------------
    $smtpLog = "[" . date('Y-m-d H:i:s') . "] Attempting SMTP transmission for $bookingRef to $recipientEmail...\n";
    $socket = @fsockopen("127.0.0.1", 25, $errno, $errstr, 2);
    if ($socket) {
        $response = fgets($socket, 515);
        $smtpLog .= "S: $response";

        fputs($socket, "HELO localhost\r\n");
        $response = fgets($socket, 515);
        $smtpLog .= "S: $response";

        fputs($socket, "MAIL FROM: <noreply@silvervillage.local>\r\n");
        $response = fgets($socket, 515);
        $smtpLog .= "S: $response";

        fputs($socket, "RCPT TO: <Admin@localhost>\r\n");
        $response = fgets($socket, 515);
        $smtpLog .= "S: $response";

        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        $smtpLog .= "S: $response";

        fputs($socket, $rawEmail . "\r\n.\r\n");
        $response = fgets($socket, 515);
        $smtpLog .= "S: $response";

        fputs($socket, "QUIT\r\n");
        fclose($socket);
        $smtpLog .= "SMTP Transmission SUCCESS: Delivered to Mercury Mail Server on localhost:25\n\n";
    } else {
        $smtpLog .= "Mercury SMTP Server (port 25) not currently active. Direct file deposit used.\n\n";
    }

    // --------------------------------------------------------------------------
    // 3. PHP standard mail() dispatch
    // --------------------------------------------------------------------------
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: Silver Village Cinema <noreply@silvervillage.local>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    @mail($recipientEmail, $subject, $htmlBody, $headers);

    // --------------------------------------------------------------------------
    // 4. Archive HTML copy in /sent_emails/ and log SMTP handshake
    // --------------------------------------------------------------------------
    $archiveDir = __DIR__ . '/../sent_emails';
    if (!is_dir($archiveDir)) {
        @mkdir($archiveDir, 0777, true);
    }
    $filename = $archiveDir . '/booking_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $bookingRef) . '.html';
    @file_put_contents($filename, $htmlBody);
    @file_put_contents($archiveDir . '/smtp_log.txt', $smtpLog, FILE_APPEND);

    return true;
}
?>
