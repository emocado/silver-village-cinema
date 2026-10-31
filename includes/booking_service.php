<?php
/** Booking writers lock the customer, then screening rows in ascending order. */
function cinemaQuery(mysqli $db,string $sql,string $types='',array $args=[]): mysqli_stmt {
    $stmt=$db->prepare($sql);
    if($types!=='') $stmt->bind_param($types,...$args);
    $stmt->execute(); return $stmt;
}
function lockCustomer(mysqli $db,int $userId): void {
    if(!cinemaQuery($db,'SELECT user_id FROM users WHERE user_id=? FOR UPDATE','i',[$userId])->get_result()->fetch_assoc()) throw new RuntimeException('Please sign in again.');
}
function normalizeWishlist(mysqli $db,int $userId): void {
    $rows=cinemaQuery($db,'SELECT wishlist_id FROM booking_wishlist WHERE user_id=? ORDER BY preference_rank,wishlist_id','i',[$userId])->get_result();
    $rank=1;
    while($row=$rows->fetch_assoc()) cinemaQuery($db,'UPDATE booking_wishlist SET preference_rank=? WHERE wishlist_id=? AND user_id=?','iii',[$rank++,$row['wishlist_id'],$userId]);
}
function screeningInfo(mysqli $db,int $id,bool $lock=false): array {
    if($lock && !cinemaQuery($db,'SELECT screening_id FROM screenings WHERE screening_id=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc()) throw new RuntimeException('This screening is no longer available.');
    $row=cinemaQuery($db,'SELECT s.*,m.title AS movie_title,m.poster_image,m.duration_minutes,m.rating,m.genre,h.* FROM screenings s JOIN movies m ON m.movie_id=s.movie_id JOIN halls h ON h.hall_id=s.hall_id WHERE s.screening_id=?','i',[$id])->get_result()->fetch_assoc();
    if(!$row) throw new RuntimeException('This screening is no longer available.');
    if(strtotime($row['screening_date'].' '.$row['screening_time'])<=time()) throw new RuntimeException('This screening has already started. Please choose a future showtime.');
    return $row;
}
function validateSeats(string $text,array $info): array {
    if(strlen($text)>255 || trim($text)==='') throw new RuntimeException('Select at least one valid seat.');
    $seats=array_map('trim',explode(',',$text));
    if(count(array_unique($seats))!==count($seats)) throw new RuntimeException('Each seat can only be selected once.');
    foreach($seats as $seat) {
        if(!preg_match('/^([A-Z])([1-9][0-9]?)$/D',$seat,$parts) || ord($parts[1])-64>(int)$info['total_rows'] || (int)$parts[2]>(int)$info['seats_per_row']) throw new RuntimeException('One or more seats do not exist in this hall. Please select seats on the map.');
    }
    return $seats;
}
function priceSeats(array $seats,array $info): float {
    $cents=0;
    foreach($seats as $seat) $cents+=(int)round(100*(float)$info[ord($seat[0])-64 >= (int)$info['premium_row_start']?'premium_price':'standard_price']);
    return $cents/100;
}
function ensureSeatsAvailable(mysqli $db,int $sid,array $seats): void {
    // Current locking reads avoid a stale snapshot under REPEATABLE READ.
    $rows=cinemaQuery($db,"SELECT bs.seat_label FROM booked_seats bs JOIN bookings b ON b.booking_id=bs.booking_id WHERE b.screening_id=? AND b.status='confirmed' FOR UPDATE",'i',[$sid])->get_result();
    while($row=$rows->fetch_assoc()) if(in_array($row['seat_label'],$seats,true)) throw new RuntimeException('A selected seat is no longer available. Please choose different seats.');
}
function saveWishlist(mysqli $db,int $uid,int $sid,string $text,int $rank,int $edit=0): void {
    if($rank<1 || $rank>3) throw new RuntimeException('Choose preference 1, 2 or 3.');
    $db->begin_transaction();
    try {
        lockCustomer($db,$uid); $info=screeningInfo($db,$sid,true); $seats=validateSeats($text,$info); ensureSeatsAvailable($db,$sid,$seats);
        $rows=cinemaQuery($db,'SELECT wishlist_id FROM booking_wishlist WHERE user_id=? ORDER BY preference_rank,wishlist_id FOR UPDATE','i',[$uid])->get_result(); $ids=[];
        while($row=$rows->fetch_assoc()) $ids[]=(int)$row['wishlist_id'];
        if($edit && !in_array($edit,$ids,true)) throw new RuntimeException('This wishlist item is not in your account.');
        $total=priceSeats($seats,$info); $text=implode(',',$seats);
        if($edit) {
            cinemaQuery($db,'UPDATE booking_wishlist SET screening_id=?,selected_seats=?,estimated_total=? WHERE wishlist_id=? AND user_id=?','isdii',[$sid,$text,$total,$edit,$uid]);
            $ids=array_values(array_diff($ids,[$edit]));
        } else {
            $stmt=cinemaQuery($db,'INSERT INTO booking_wishlist(user_id,screening_id,selected_seats,preference_rank,estimated_total) VALUES(?,?,?,?,?)','iisid',[$uid,$sid,$text,$rank,$total]); $edit=(int)$stmt->insert_id;
        }
        array_splice($ids,min($rank-1,count($ids)),0,[$edit]);
        foreach($ids as $i=>$id) cinemaQuery($db,'UPDATE booking_wishlist SET preference_rank=? WHERE wishlist_id=? AND user_id=?','iii',[$i+1,$id,$uid]);
        $db->commit();
    } catch(Throwable $e) { $db->rollback(); throw $e; }
}
function changeWishlist(mysqli $db,int $uid,string $action,int $id): void {
    if(!in_array($action,['remove','clear','move_up','move_down'],true)) throw new RuntimeException('Choose a valid wishlist action.');
    $db->begin_transaction();
    try {
        lockCustomer($db,$uid); $ids=[];
        $rows=cinemaQuery($db,'SELECT wishlist_id FROM booking_wishlist WHERE user_id=? ORDER BY preference_rank,wishlist_id FOR UPDATE','i',[$uid])->get_result();
        while($row=$rows->fetch_assoc()) $ids[]=(int)$row['wishlist_id'];
        if($action==='clear') cinemaQuery($db,'DELETE FROM booking_wishlist WHERE user_id=?','i',[$uid]);
        else {
            $pos=array_search($id,$ids,true);
            if($pos===false) throw new RuntimeException('This wishlist item is not in your account.');
            if($action==='remove') cinemaQuery($db,'DELETE FROM booking_wishlist WHERE wishlist_id=? AND user_id=?','ii',[$id,$uid]);
            else {
                $next=$pos+($action==='move_up'?-1:1);
                if(isset($ids[$next])) {
                    [$ids[$pos],$ids[$next]]=[$ids[$next],$ids[$pos]];
                    foreach($ids as $i=>$wid) cinemaQuery($db,'UPDATE booking_wishlist SET preference_rank=? WHERE wishlist_id=? AND user_id=?','iii',[$i+1,$wid,$uid]);
                }
            }
        }
        normalizeWishlist($db,$uid); $db->commit();
    } catch(Throwable $e) { $db->rollback(); throw $e; }
}
function checkoutItems(mysqli $db,int $uid,array $selection): array {
    if(($selection['mode']??'')==='wishlist') {
        $ids=$selection['ids']??[];
        if(!$ids || count($ids)>50 || count(array_unique($ids))!==count($ids)) throw new RuntimeException('Select one or more different wishlist items.');
        $items=[];
        foreach($ids as $id) {
            if(!is_int($id) || $id<1) throw new RuntimeException('Invalid wishlist selection.');
            $row=cinemaQuery($db,'SELECT * FROM booking_wishlist WHERE wishlist_id=? AND user_id=?','ii',[$id,$uid])->get_result()->fetch_assoc();
            if(!$row) throw new RuntimeException('A selected wishlist item changed. Return to your wishlist and try again.');
            $items[]=$row;
        }
    } elseif(($selection['mode']??'')==='direct') $items=[['screening_id'=>(int)$selection['screening_id'],'selected_seats'=>$selection['seats'],'wishlist_id'=>0]];
    else throw new RuntimeException('Select a booking before continuing.');
    $seen=[];
    foreach($items as &$item) {
        $info=screeningInfo($db,(int)$item['screening_id']); $seats=validateSeats($item['selected_seats'],$info); ensureSeatsAvailable($db,(int)$item['screening_id'],$seats);
        foreach($seats as $seat) {
            $key=$item['screening_id'].':'.$seat;
            if(isset($seen[$key])) throw new RuntimeException('Two selected bookings contain the same screening and seat. Select only one of these choices.');
            $seen[$key]=true;
        }
        $item=array_merge($info,$item,['seats_array'=>$seats,'price'=>priceSeats($seats,$info),'title'=>$info['movie_title']]);
    }
    unset($item); return $items;
}
function completeCheckout(mysqli $db,int $uid,array $selection,string $outcome): array {
    if(!in_array($outcome,['success','fail'],true)) throw new RuntimeException('Choose a payment outcome.');
    $db->begin_transaction();
    try {
        lockCustomer($db,$uid);
        if(($selection['mode']??'')==='direct') $screenings=[(int)$selection['screening_id']];
        elseif(($selection['mode']??'')==='wishlist') {
            $screenings=[];
            foreach(($selection['ids']??[]) as $id) {
                if(!is_int($id) || $id<1) throw new RuntimeException('Invalid wishlist selection.');
                $row=cinemaQuery($db,'SELECT screening_id FROM booking_wishlist WHERE wishlist_id=? AND user_id=?','ii',[$id,$uid])->get_result()->fetch_assoc();
                if(!$row) throw new RuntimeException('A selected wishlist item changed. Return to your wishlist and try again.');
                $screenings[]=(int)$row['screening_id'];
            }
        } else throw new RuntimeException('Select a booking before continuing.');
        $screenings=array_unique($screenings); sort($screenings,SORT_NUMERIC);
        foreach($screenings as $sid) screeningInfo($db,$sid,true);
        $items=checkoutItems($db,$uid,$selection); $bookings=[]; $total=0;
        foreach($items as $item) {
            $ref='SVC-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(6))); $status=$outcome==='success'?'confirmed':'cancelled'; $payment=$outcome==='success'?'success':'failed';
            $stmt=cinemaQuery($db,'INSERT INTO bookings(booking_reference,user_id,screening_id,total_price,status,payment_status) VALUES(?,?,?,?,?,?)','siidss',[$ref,$uid,$item['screening_id'],$item['price'],$status,$payment]); $bid=(int)$stmt->insert_id;
            if($outcome==='success') {
                foreach($item['seats_array'] as $seat) {
                    $premium=ord($seat[0])-64 >= (int)$item['premium_row_start'];
                    cinemaQuery($db,'INSERT INTO booked_seats(booking_id,seat_label,seat_type,price) VALUES(?,?,?,?)','issd',[$bid,$seat,$premium?'premium':'standard',(float)$item[$premium?'premium_price':'standard_price']]);
                }
                if($item['wishlist_id']) cinemaQuery($db,'DELETE FROM booking_wishlist WHERE wishlist_id=? AND user_id=?','ii',[$item['wishlist_id'],$uid]);
            }
            $bookings[]=['booking_id'=>$bid,'booking_ref'=>$ref,'title'=>$item['title'],'hall_name'=>$item['hall_name'],'experience_type'=>$item['experience_type'],'screening_date'=>$item['screening_date'],'screening_time'=>$item['screening_time'],'seats'=>implode(',',$item['seats_array']),'price'=>$item['price']]; $total+=$item['price'];
        }
        normalizeWishlist($db,$uid); $db->commit();
        return ['bookings'=>$bookings,'total'=>round($total,2),'outcome'=>$outcome,'reference'=>$bookings[0]['booking_ref']];
    } catch(Throwable $e) { $db->rollback(); throw $e; }
}
function cancelBooking(mysqli $db,int $uid,int $id): void {
    $db->begin_transaction();
    try {
        lockCustomer($db,$uid);
        $item=cinemaQuery($db,'SELECT * FROM bookings WHERE booking_id=? AND user_id=?','ii',[$id,$uid])->get_result()->fetch_assoc();
        if(!$item || $item['status']!=='confirmed') throw new RuntimeException('This confirmed booking was not found in your account.');
        screeningInfo($db,(int)$item['screening_id'],true);
        cinemaQuery($db,"UPDATE bookings SET status='cancelled' WHERE booking_id=? AND user_id=?",'ii',[$id,$uid]); $db->commit();
    } catch(Throwable $e) { $db->rollback(); throw $e; }
}
