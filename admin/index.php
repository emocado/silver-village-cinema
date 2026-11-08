<?php
require_once __DIR__.'/../includes/db_connect.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/booking_service.php';
requireAdmin();
function adminDate(string $date): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d && $d->format('Y-m-d')===$date; }
function adminText(string $key,int $max,bool $required=true): string {
    $value=trim($_POST[$key]??'');
    if(($required && $value==='') || strlen($value)>$max) throw new RuntimeException('Enter a valid '.str_replace('_',' ',$key).' (maximum '.$max.' characters).');
    return $value;
}
$tabs = ['movies' => 'Movies', 'screenings' => 'Screenings', 'bookings' => 'Bookings', 'enquiries' => 'Enquiries'];
$activeTab = $_GET['tab'] ?? (isset($_GET['status']) || isset($_GET['date']) ? 'bookings' : 'movies');
if (!is_string($activeTab) || !isset($tabs[$activeTab])) {
    $activeTab = 'movies';
}
$notice=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action=$_POST['action']??''; $id=(int)($_POST['id']??0);
        $activeTab = in_array($action, ['add_screening', 'delete_screening'], true) ? 'screenings' : 'movies';
        if($action==='save_movie') {
            $title=adminText('title',150); $synopsis=adminText('synopsis',10000); $genre=adminText('genre',100); $director=adminText('director',150); $cast=adminText('cast',500);
            $duration=filter_var($_POST['duration_minutes']??'',FILTER_VALIDATE_INT);
            $rating=$_POST['rating']??''; $status=$_POST['status']??''; $release=$_POST['release_date']??''; $poster=adminText('poster_image',255);
            if($duration===false || $duration<1 || $duration>600) throw new RuntimeException('Duration must be between 1 and 600 minutes.');
            if(!in_array($rating,['G','PG','PG13','NC16','M18','R21'],true) || !in_array($status,['now_showing','coming_soon'],true) || !adminDate($release)) throw new RuntimeException('Choose a valid rating, release status and release date.');
            if(!preg_match('/^[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|webp)$/Di',$poster) || !is_file(__DIR__.'/../images/posters/'.$poster)) throw new RuntimeException('Poster must be an existing image filename in images/posters.');
            if($id) {
                if(!cinemaQuery($conn,'SELECT movie_id FROM movies WHERE movie_id=?','i',[$id])->get_result()->fetch_assoc()) throw new RuntimeException('Movie not found.');
                cinemaQuery($conn,'UPDATE movies SET title=?,synopsis=?,genre=?,duration_minutes=?,rating=?,director=?,cast=?,status=?,release_date=?,poster_image=? WHERE movie_id=?','sssissssssi',[$title,$synopsis,$genre,$duration,$rating,$director,$cast,$status,$release,$poster,$id]);
            } else cinemaQuery($conn,'INSERT INTO movies(title,synopsis,genre,duration_minutes,rating,director,cast,status,release_date,poster_image) VALUES(?,?,?,?,?,?,?,?,?,?)','sssissssss',[$title,$synopsis,$genre,$duration,$rating,$director,$cast,$status,$release,$poster]);
            $notice='Movie saved.';
        } elseif($action==='delete_movie') {
            $conn->begin_transaction();
            $movie=cinemaQuery($conn,'SELECT movie_id FROM movies WHERE movie_id=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
            if(!$movie) throw new RuntimeException('Movie not found.');
            cinemaQuery($conn,'SELECT screening_id FROM screenings WHERE movie_id=? ORDER BY screening_id FOR UPDATE','i',[$id])->get_result()->fetch_all();
            $count=cinemaQuery($conn,'SELECT (SELECT COUNT(*) FROM bookings b JOIN screenings s ON s.screening_id=b.screening_id WHERE s.movie_id=?)+(SELECT COUNT(*) FROM booking_wishlist w JOIN screenings s ON s.screening_id=w.screening_id WHERE s.movie_id=?) AS total','ii',[$id,$id])->get_result()->fetch_assoc();
            if($count['total']) throw new RuntimeException('This movie has booking history or shortlisted seats. Keep it to preserve customer records.');
            cinemaQuery($conn,'DELETE FROM movies WHERE movie_id=?','i',[$id]); $conn->commit(); $notice='Movie removed.';
        } elseif($action==='add_screening') {
            $movieId=(int)($_POST['movie_id']??0); $hallId=(int)($_POST['hall_id']??0); $date=$_POST['screening_date']??''; $time=$_POST['screening_time']??'';
            if(!adminDate($date) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$time) || strtotime($date.' '.$time)<=time()) throw new RuntimeException('Choose a valid future screening date and time.');
            $conn->begin_transaction();
            $movie=cinemaQuery($conn,"SELECT duration_minutes FROM movies WHERE movie_id=? AND status='now_showing' FOR UPDATE",'i',[$movieId])->get_result()->fetch_assoc();
            $hall=cinemaQuery($conn,'SELECT hall_id FROM halls WHERE hall_id=? FOR UPDATE','i',[$hallId])->get_result()->fetch_assoc();
            if(!$movie || !$hall) throw new RuntimeException('Choose a valid movie and hall.');
            $start=$date.' '.$time.':00'; $end=date('Y-m-d H:i:s',strtotime($start)+(int)$movie['duration_minutes']*60);
            $overlap=cinemaQuery($conn,'SELECT s.screening_id FROM screenings s JOIN movies m ON m.movie_id=s.movie_id WHERE s.hall_id=? AND TIMESTAMP(s.screening_date,s.screening_time)<? AND DATE_ADD(TIMESTAMP(s.screening_date,s.screening_time),INTERVAL m.duration_minutes MINUTE)>? LIMIT 1','iss',[$hallId,$end,$start])->get_result()->fetch_assoc();
            if($overlap) throw new RuntimeException('This hall already has a screening that overlaps that time.');
            cinemaQuery($conn,'INSERT INTO screenings(movie_id,hall_id,screening_date,screening_time) VALUES(?,?,?,?)','iiss',[$movieId,$hallId,$date,$time]); $conn->commit(); $notice='Screening scheduled.';
        } elseif($action==='delete_screening') {
            $conn->begin_transaction();
            $row=cinemaQuery($conn,'SELECT screening_id FROM screenings WHERE screening_id=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
            if(!$row) throw new RuntimeException('Screening not found.');
            $counts=cinemaQuery($conn,'SELECT (SELECT COUNT(*) FROM bookings WHERE screening_id=?)+(SELECT COUNT(*) FROM booking_wishlist WHERE screening_id=?) AS total','ii',[$id,$id])->get_result()->fetch_assoc();
            if($counts['total']) throw new RuntimeException('This screening has booking history or wishlist choices and cannot be deleted.');
            cinemaQuery($conn,'DELETE FROM screenings WHERE screening_id=?','i',[$id]); $conn->commit(); $notice='Screening removed.';
        } else throw new RuntimeException('Choose a valid admin action.');
        $_SESSION['admin_notice']=$notice; header('Location: index.php?tab='.$activeTab,true,303); exit;
    } catch(Throwable $e) { $conn->rollback(); $error=userError($e); }
}
if(isset($_SESSION['admin_notice'])) { $notice=$_SESSION['admin_notice']; unset($_SESSION['admin_notice']); }
$edit=null;
if(isset($_GET['edit_movie'])) $edit=cinemaQuery($conn,'SELECT * FROM movies WHERE movie_id=?','i',[(int)$_GET['edit_movie']])->get_result()->fetch_assoc();
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_movie') $edit=array_merge($_POST,['movie_id'=>(int)($_POST['id']??0)]);
$movies=$conn->query('SELECT * FROM movies ORDER BY title')->fetch_all(MYSQLI_ASSOC);
$halls=$conn->query('SELECT * FROM halls ORDER BY hall_id')->fetch_all(MYSQLI_ASSOC);
$screenings=$conn->query('SELECT s.*,m.title,h.hall_name FROM screenings s JOIN movies m ON m.movie_id=s.movie_id JOIN halls h ON h.hall_id=s.hall_id ORDER BY s.screening_date DESC,s.screening_time')->fetch_all(MYSQLI_ASSOC);
$filter=$_GET['status']??''; if(!in_array($filter,['','confirmed','cancelled'],true)) $filter='';
$filterDate=$_GET['date']??''; if($filterDate!=='' && !adminDate($filterDate)) $filterDate='';
$bookings=cinemaQuery($conn,'SELECT b.*,u.full_name,m.title,s.screening_date,s.screening_time FROM bookings b JOIN users u ON u.user_id=b.user_id JOIN screenings s ON s.screening_id=b.screening_id JOIN movies m ON m.movie_id=s.movie_id WHERE (?=\'\' OR b.status=?) AND (?=\'\' OR DATE(b.booking_date)=?) ORDER BY b.booking_id DESC','ssss',[$filter,$filter,$filterDate,$filterDate])->get_result()->fetch_all(MYSQLI_ASSOC);
$enquiries=$conn->query('SELECT * FROM enquiries ORDER BY enquiry_id DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
$stats=$conn->query("SELECT COUNT(*) AS total,COALESCE(SUM(total_price),0) AS revenue FROM bookings WHERE status='confirmed' AND payment_status='success'")->fetch_assoc();
$escape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$value=fn($key,$default='')=>$escape($edit[$key]??$default);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cinema Administration - CinéPop</title><link rel="stylesheet" href="../css/styles.css"><link rel="stylesheet" href="../css/cinema-theme.css?v=20261030">
<style>
.admin-tabs {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    max-width: 760px;
    margin: 24px 0;
    border-bottom: 1px solid var(--color-border);
}
.admin-tabs a {
    display: grid;
    place-items: center;
    min-height: 44px;
    padding: 8px;
    border-bottom: 2px solid transparent;
    color: var(--color-text-muted);
    font-weight: 700;
    text-decoration: none;
}
.admin-tabs a:hover { background: var(--color-bg-elevated); }
.admin-tabs a[aria-current="page"] {
    border-bottom-color: var(--color-primary-light);
    color: var(--color-primary-light);
}
.admin-tabs a:focus-visible {
    outline: 2px solid var(--color-primary-light);
    outline-offset: -4px;
}
.page-admin .header-container { justify-content: space-between; }
@media (max-width: 480px) {
    .admin-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
</head><body class="page-admin">
<header class="site-header"><div class="header-container"><a href="index.php" class="brand-logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor">
                        <path d="M18 4l2 4h-3l-2-4h-2l2 4h-3l-2-4H8l2 4H7L5 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4h-4zM8 17H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V7h2v2zm10 8h-8v-2h8v2zm0-4h-8v-2h8v2zm0-4h-8V7h8v2z"/>
                    </svg>
                </div>
                <div class="logo-text">
                    <span class="logo-title">CinéPop</span>
                    <span class="logo-sub">CINEMA</span>
                </div>
            </a><form method="post" action="../logout.php" class="inline-action"><?= csrfField() ?><button class="btn btn--ghost">Logout</button></form></div></header>
<main class="main-content"><h1>Cinema administration</h1><p><?= (int)$stats['total'] ?> confirmed bookings · $<?= number_format($stats['revenue'],2) ?> ticket revenue</p>
<nav class="admin-tabs" aria-label="Cinema administration sections">
    <?php foreach ($tabs as $tab => $label): ?>
        <a href="?tab=<?= $tab ?>"<?= $activeTab === $tab ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
</nav>
<?php if($notice): ?><div class="alert alert--success" role="status"><?= $escape($notice) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert--danger" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<section id="movies"<?= $activeTab === 'movies' ? '' : ' hidden' ?>><h2>Movies</h2><div class="table-responsive"><table class="custom-table"><thead><tr><th>Title</th><th>Status</th><th>Duration</th><th>Actions</th></tr></thead><tbody>
<?php foreach($movies as $m): ?><tr><td><?= $escape($m['title']) ?></td><td><?= $escape(str_replace('_',' ',$m['status'])) ?></td><td><?= (int)$m['duration_minutes'] ?> min</td><td><a class="btn btn--outline btn--sm" href="?tab=movies&amp;edit_movie=<?= (int)$m['movie_id'] ?>#movie-form">Edit</a> <form method="post" class="inline-action"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$m['movie_id'] ?>"><button name="action" value="delete_movie" class="btn btn--danger btn--sm">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<form method="post" id="movie-form" class="form-card admin-form"><?= csrfField() ?><h3><?= $edit?'Edit movie':'Add a movie' ?></h3><input type="hidden" name="action" value="save_movie"><input type="hidden" name="id" value="<?= (int)($edit['movie_id']??0) ?>">
<?php foreach(['title'=>'Title','genre'=>'Genre','director'=>'Director','cast'=>'Cast','poster_image'=>'Poster filename'] as $key=>$label): ?><div class="form-group"><label for="movie_<?= $key ?>"><?= $label ?></label><input class="form-control" id="movie_<?= $key ?>" name="<?= $key ?>" value="<?= $value($key) ?>" maxlength="<?= ['title'=>150,'genre'=>100,'director'=>150,'cast'=>500,'poster_image'=>255][$key] ?>" required></div><?php endforeach; ?>
<div class="form-group"><label for="movie_synopsis">Synopsis</label><textarea class="form-control" id="movie_synopsis" name="synopsis" minlength="1" maxlength="10000" required><?= $value('synopsis') ?></textarea></div>
<div class="form-group"><label for="movie_duration">Duration (minutes)</label><input class="form-control" id="movie_duration" name="duration_minutes" type="number" min="1" max="600" value="<?= $value('duration_minutes',120) ?>" required></div>
<div class="form-group"><label for="movie_rating">Rating</label><select class="form-control" name="rating" id="movie_rating"><?php foreach(['G','PG','PG13','NC16','M18','R21'] as $r): ?><option <?= ($edit['rating']??'PG13')===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="movie_status">Release status</label><select class="form-control" name="status" id="movie_status"><?php foreach(['now_showing'=>'Now showing','coming_soon'=>'Coming soon'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($edit['status']??'now_showing')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="movie_release">Release date</label><input class="form-control" id="movie_release" name="release_date" type="date" value="<?= $value('release_date',date('Y-m-d')) ?>" required></div><button class="btn btn--primary">Save movie</button> <a href="?tab=movies#movie-form" class="btn btn--ghost">New movie</a></form></section>
<section id="screenings"<?= $activeTab === 'screenings' ? '' : ' hidden' ?>><h2>Screenings</h2><form method="post" class="form-card admin-form" id="screening-form"><?= csrfField() ?><input type="hidden" name="action" value="add_screening"><h3>Schedule a showtime</h3>
<div class="form-group"><label for="screen_movie">Movie</label><select class="form-control" name="movie_id" id="screen_movie" required><option value="">Choose a movie</option><?php foreach($movies as $m): if($m['status']!=='now_showing') continue; ?><option value="<?= (int)$m['movie_id'] ?>"><?= $escape($m['title']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="screen_hall">Hall</label><select class="form-control" name="hall_id" id="screen_hall" required><option value="">Choose a hall</option><?php foreach($halls as $h): ?><option value="<?= (int)$h['hall_id'] ?>"><?= $escape($h['hall_name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="screen_date">Date</label><input class="form-control" id="screen_date" name="screening_date" type="date" min="<?= date('Y-m-d') ?>" required></div><div class="form-group"><label for="screen_time">Time</label><input class="form-control" id="screen_time" name="screening_time" type="time" required></div><button class="btn btn--primary">Schedule screening</button></form>
<div class="table-responsive"><table class="custom-table"><thead><tr><th>Movie</th><th>Showtime</th><th>Hall</th><th>Action</th></tr></thead><tbody><?php foreach($screenings as $s): ?><tr><td><?= $escape($s['title']) ?></td><td><?= $escape($s['screening_date'].' '.$s['screening_time']) ?></td><td><?= $escape($s['hall_name']) ?></td><td><form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$s['screening_id'] ?>"><button name="action" value="delete_screening" class="btn btn--danger btn--sm">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<section id="bookings"<?= $activeTab === 'bookings' ? '' : ' hidden' ?>><h2>Booking report</h2><form method="get" class="action-row"><input type="hidden" name="tab" value="bookings"><label>Status <select name="status" class="form-control"><option value="">All</option><?php foreach(['confirmed','cancelled'] as $s): ?><option <?= $filter===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></label><label>Booking date <input type="date" name="date" value="<?= $escape($filterDate) ?>" class="form-control"></label><button class="btn btn--outline">Filter bookings</button></form>
<div class="table-responsive"><table class="custom-table"><thead><tr><th>Reference</th><th>Customer / Movie</th><th>Showtime</th><th>Amount</th><th>Status / Payment</th></tr></thead><tbody><?php foreach($bookings as $b): ?><tr><td><?= $escape($b['booking_reference']) ?></td><td><?= $escape($b['full_name'].' / '.$b['title']) ?></td><td><?= $escape($b['screening_date'].' '.$b['screening_time']) ?></td><td>$<?= number_format($b['total_price'],2) ?></td><td><?= $escape($b['status'].' / '.$b['payment_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section id="enquiries"<?= $activeTab === 'enquiries' ? '' : ' hidden' ?>><h2>Contact enquiries</h2><?php if(!$enquiries): ?><p>No enquiries yet.</p><?php endif; ?><?php foreach($enquiries as $e): ?><article class="enquiry"><h3><?= $escape($e['subject']?:'General enquiry') ?></h3><p><?= $escape($e['full_name'].' · '.$e['email'].' · '.$e['created_at']) ?></p><p><?= nl2br($escape($e['message'])) ?></p></article><?php endforeach; ?></section>
</main></body></html>
