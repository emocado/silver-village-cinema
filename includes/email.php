<?php
function localMailConfig(): array { return require __DIR__.'/mail_config.php'; }
function smtpReply($socket,array $expected): void {
    do {
        $line=fgets($socket,1024);
        if($line===false) throw new RuntimeException('The local mail server did not respond.');
    } while(isset($line[3]) && $line[3]==='-');
    if(!in_array((int)substr($line,0,3),$expected,true)) throw new RuntimeException('The local mail server rejected delivery. Check Mercury and the local mailbox.');
}
function smtpWrite($socket,string $data): void {
    while($data!=='') {
        $written=fwrite($socket,$data);
        if($written===false || $written===0) throw new RuntimeException('Connection to the local mail server was interrupted.');
        $data=substr($data,$written);
    }
}
function renderBookingAcknowledgement($reference, $customerEmail, $customerName, $bookings, $grandTotal): string {
    $escape = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking acknowledgement</title>
</head>
<body style="margin:0;padding:20px 12px;background:#edf3fb;color:#0b203b;font:16px/1.5 Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #c8d8e9;">
        <tr>
            <td style="padding:24px 24px 22px;background:#06162b;color:#fff;border-bottom:4px solid #0068c8;">
                <p style="margin:0 0 12px;font-size:17px;font-weight:bold;color:#b9dcff;">CinéPop Cinema</p>
                <h1 style="margin:0;font-size:30px;line-height:1.2;">Booking confirmed</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 18px;">Hi <?= $escape($customerName) ?>, your payment was successful and your seats are confirmed.</p>
                <p style="margin:0 0 24px;padding:12px 14px;background:#edf3fb;">
                    <span style="color:#405773;font-size:13px;">Receipt reference</span><br>
                    <strong style="overflow-wrap:anywhere;"><?= $escape($reference) ?></strong>
                </p>
                <h2 style="margin:0 0 8px;font-size:19px;">Your tickets</h2>
                <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th scope="col" align="left" style="padding:8px 0;border-bottom:1px solid #c8d8e9;color:#405773;font-size:13px;">Booking details</th>
                            <th scope="col" align="right" style="padding:8px 0 8px 12px;border-bottom:1px solid #c8d8e9;color:#405773;font-size:13px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td valign="top" style="padding:14px 0;border-bottom:1px solid #c8d8e9;overflow-wrap:anywhere;">
                                <strong style="font-size:17px;"><?= $escape($booking['title']) ?></strong><br>
                                <?= $escape(date('D, j M Y', strtotime($booking['screening_date']))) ?> at
                                <?= $escape(date('g:i A', strtotime($booking['screening_time']))) ?><br>
                                <?= $escape($booking['hall_name']) ?> &middot; <strong>Seats <?= $escape($booking['seats']) ?></strong><br>
                                <span style="font-size:13px;color:#405773;">Booking reference <?= $escape($booking['booking_ref'] ?? $reference) ?></span>
                            </td>
                            <td align="right" valign="top" style="padding:14px 0 14px 12px;border-bottom:1px solid #c8d8e9;white-space:nowrap;">
                                $<?= number_format((float) $booking['price'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin:20px 0 0;padding:14px;background:#0b203b;color:#fff;font-size:18px;">
                    <strong>Total paid: SGD $<?= number_format((float) $grandTotal, 2) ?></strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;background:#edf3fb;color:#405773;font-size:13px;">
                Show your booking reference at the cinema.<br>
                Booked for <?= $escape($customerEmail) ?>.
            </td>
        </tr>
    </table>
</body>
</html>
    <?php
    return ob_get_clean();
}

function sendBookingAcknowledgement($reference,$customerEmail,$customerName,$bookings,$grandTotal): array {
    $config=localMailConfig(); $socket=null;
    try {
        if($config['host']!=='127.0.0.1' || !preg_match('/^[a-zA-Z0-9._-]+@localhost$/Di',$config['recipient']) || !preg_match('/^[a-zA-Z0-9._-]+@localhost$/Di',$config['sender'])) throw new RuntimeException('Email configuration must use the local Mercury mailbox.');
        $body = renderBookingAcknowledgement($reference, $customerEmail, $customerName, $bookings, $grandTotal);
        $subject='=?UTF-8?B?'.base64_encode('Booking acknowledgement '.$reference.' - CinePop').'?=';
        $message='From: =?UTF-8?B?'.base64_encode('CinePop').'?= <'.$config['sender'].">\r\nTo: <".$config['recipient'].">\r\nSubject: ".$subject."\r\nDate: ".date('r')."\r\nMessage-ID: <".bin2hex(random_bytes(16))."@localhost>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
        $socket=@fsockopen($config['host'],(int)$config['port'],$errno,$errstr,(int)$config['timeout']);
        if(!$socket) throw new RuntimeException('Booking saved, but Mercury is unavailable. Start Mercury in XAMPP, then resend from My Bookings.');
        stream_set_timeout($socket,(int)$config['timeout']); smtpReply($socket,[220]);
        foreach(['EHLO localhost','MAIL FROM:<'.$config['sender'].'>','RCPT TO:<'.$config['recipient'].'>'] as $command) { smtpWrite($socket,$command."\r\n"); smtpReply($socket,[250]); }
        smtpWrite($socket,"DATA\r\n"); smtpReply($socket,[354]);
        smtpWrite($socket,$message."\r\n.\r\n"); smtpReply($socket,[250]);
        @fwrite($socket,"QUIT\r\n"); fclose($socket); $socket=null;
        return ['accepted'=>true,'recipient'=>$config['recipient'],'message'=>'Mercury accepted your acknowledgement. Open Thunderbird and choose Get Messages.'];
    } catch(Throwable $e) {
        if(is_resource($socket)) fclose($socket);
        return ['accepted'=>false,'recipient'=>$config['recipient'],'message'=>$e->getMessage()];
    }
}
