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
function sendBookingAcknowledgement($reference,$customerEmail,$customerName,$bookings,$grandTotal): array {
    $config=localMailConfig(); $socket=null;
    try {
        if($config['host']!=='127.0.0.1' || !preg_match('/^[a-zA-Z0-9._-]+@localhost$/Di',$config['recipient']) || !preg_match('/^[a-zA-Z0-9._-]+@localhost$/Di',$config['sender'])) throw new RuntimeException('Email configuration must use the local Mercury mailbox.');
        $escape=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); $items='';
        foreach($bookings as $b) $items.='<tr><td style="padding:12px;border-bottom:1px solid #ccc"><strong>'.$escape($b['title']).'</strong><br>Booking: '.$escape($b['booking_ref']??$reference).'<br>'.$escape($b['screening_date']).' at '.$escape($b['screening_time']).'<br>'.$escape($b['hall_name']).' | Seats: '.$escape($b['seats']).'</td><td style="padding:12px">$'.number_format((float)$b['price'],2).'</td></tr>';
        $body='<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Booking acknowledgement</title></head><body style="font-family:Arial,sans-serif;background:#f5f3ed;color:#202020;padding:24px"><main style="max-width:640px;margin:auto;background:white;padding:24px"><h1>CinéPop</h1><h2>Booking confirmed</h2><p>Dear '.$escape($customerName).', your payment was successful and your tickets are confirmed. We look forward to seeing you.</p><p>Receipt: <strong>'.$escape($reference).'</strong></p><table style="width:100%;border-collapse:collapse"><thead><tr><th align="left">Booking details</th><th align="left">Amount</th></tr></thead><tbody>'.$items.'</tbody></table><p><strong>Total paid: $'.number_format((float)$grandTotal,2).'</strong></p><p>Please keep your booking reference and present it at the cinema. Booked for '.$escape($customerEmail).'.</p></main></body></html>';
        $subject='=?UTF-8?B?'.base64_encode('Booking acknowledgement '.$reference.' - CinéPop').'?=';
        $message='From: =?UTF-8?B?'.base64_encode('CinéPop').'?= <'.$config['sender'].">\r\nTo: <".$config['recipient'].">\r\nSubject: ".$subject."\r\nDate: ".date('r')."\r\nMessage-ID: <".bin2hex(random_bytes(16))."@localhost>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
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
