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
    if(preg_match('/\p{N}/u',$name)) $errors['cardholder']='Enter a name without numbers.';
    elseif(strlen($name)<2 || strlen($name)>100) $errors['cardholder']='Enter the name shown on your card.';
    if(!preg_match('/^(?:[0-9]{16}|[0-9]{4}(?: [0-9]{4}){3})$/D',$number)) $errors['card_number']='Enter exactly 16 digits in groups of four or without spaces.';
    if(!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/D',$expiry,$parts)) $errors['expiry']='Enter the expiry date as MM/YY.';
    elseif(strtotime(sprintf('%04d-%02d-01',2000+(int)$parts[2],(int)$parts[1]).' +1 month')<=time()) $errors['expiry']='This card has expired. Use another card.';
    if(!preg_match('/^[0-9]{3}$/D',$cvc)) $errors['security_code']='Enter exactly 3 digits for the security code.';
    return ['errors'=>$errors,'outcome'=>$errors ? null : 'success'];
}
