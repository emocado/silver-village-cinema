<?php
/** Local coursework adapter. Replace this with a hosted payment provider for production.
 * Card details are validated in memory only: never stored in the DB, session or logs.
 */
function validatePayment(array $input): array {
    $errors=[];
    $name=trim((string)($input['cardholder']??''));
    $number=is_string($input['card_number']??null)?$input['card_number']:'';
    $expiry=trim((string)($input['expiry']??''));
    $cvc=(string)($input['security_code']??'');
    if(strlen($name)<2 || strlen($name)>100) $errors['cardholder']='Enter the name shown on your card.';
    $sum=0;
    if(preg_match('/^(?:[0-9]{16}|[0-9]{4}(?: [0-9]{4}){3})$/D',$number)) {
        $number=str_replace(' ','',$number);
        $reverse=strrev($number);
        for($i=0;$i<strlen($reverse);$i++) { $digit=(int)$reverse[$i]; if($i%2===1) { $digit*=2; if($digit>9) $digit-=9; } $sum+=$digit; }
        if($sum===0 || $sum%10!==0) $errors['card_number']='Check your card number and try again.';
    } else $errors['card_number']='Enter exactly 16 digits in groups of four or without spaces.';
    if(!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/D',$expiry,$parts)) $errors['expiry']='Enter the expiry date as MM/YY.';
    elseif(strtotime(sprintf('%04d-%02d-01',2000+(int)$parts[2],(int)$parts[1]).' +1 month')<=time()) $errors['expiry']='This card has expired. Use another card.';
    if(!preg_match('/^[0-9]{3,4}$/D',$cvc)) $errors['security_code']='Enter the 3 or 4 digit security code.';
    // Only test credentials can complete this local adapter; real cards are not charged.
    $testCards=['4242424242424242'=>'success','4000000000000002'=>'fail'];
    if(!$errors && !isset($testCards[$number])) $errors['card_number']='This checkout accepts test cards only. Please use the test details provided for your demonstration.';
    return ['errors'=>$errors,'outcome'=>$testCards[$number]??null];
}
