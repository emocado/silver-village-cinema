<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$msg = '';
$msgType = 'success';
$action = $_GET['action'] ?? 'list';

// Handle Delete (SQL DELETE)
if ($action === 'delete' && isset($_GET['id'])) {
    $screeningId = (int)$_GET['id'];
    $delStmt = $conn->prepare("DELETE FROM screenings WHERE screening_id = ?");
    $delStmt->bind_param("i", $screeningId);
    if ($delStmt->execute()) {
        $msg = "Screening schedule successfully removed.";
    } else {
        $msg = "Could not delete screening. It may have confirmed bookings.";
        $msgType = 'danger';
    }
    $delStmt->close();
    $action = 'list';
}

// Handle Add Screening POST (SQL INSERT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $movieId = (int)($_POST['movie_id'] ?? 0);
    $hallId = (int)($_POST['hall_id'] ?? 0);
    $date = $_POST['screening_date'] ?? '';
    $time = $_POST['screening_time'] ?? '';

    if ($movieId > 0 && $hallId > 0 && !empty($date) && !empty($time)) {
        $insStmt = $conn->prepare("INSERT INTO screenings (movie_id, hall_id, screening_date, screening_time) VALUES (?, ?, ?, ?)");
        $insStmt->bind_param("iiss", $movieId, $hallId, $date, $time);
        if ($insStmt->execute()) {
            $msg = "New screening successfully scheduled!";
            $action = 'list';
        } else {
            $msg = "Failed to schedule screening. Please try again.";
            $msgType = 'danger';
        }
        $insStmt->close();
    }
}

// Fetch Movies and Halls for dropdowns
$moviesList = $conn->query("SELECT movie_id, title FROM movies WHERE status = 'now_showing' ORDER BY title ASC");
$hallsList = $conn->query("SELECT hall_id, hall_name, experience_type FROM halls ORDER BY hall_name ASC");

// Fetch Screenings list
$screeningsSql = "
    SELECT s.screening_id, s.screening_date, s.screening_time,
           m.title AS movie_title, h.hall_name, h.experience_type,
           (SELECT COUNT(*) FROM booked_seats bs JOIN bookings b ON bs.booking_id = b.booking_id WHERE b.screening_id = s.screening_id AND b.status = 'confirmed') AS booked_count
    FROM screenings s
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    ORDER BY s.screening_date DESC, s.screening_time ASC
    LIMIT 40
";
$screeningsResult = $conn->query($screeningsSql);

$pageTitle = "Screening Schedules - Silver Village Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <a href="index.php" class="brand-logo">
                <div class="logo-icon" style="background:#4f46e5;">⚡</div>
                <div class="logo-text">
                    <span class="logo-title">SILVER VILLAGE</span>
                    <span class="logo-sub">ADMINISTRATION</span>
                </div>
            </a>
            <nav class="main-nav">
                <ul class="nav-list">
                    <li><a href="index.php" class="nav-link">Dashboard</a></li>
                    <li><a href="manage_movies.php" class="nav-link">Manage Movies</a></li>
                    <li><a href="manage_screenings.php" class="nav-link active">Screenings</a></li>
                    <li><a href="view_bookings.php" class="nav-link">All Bookings</a></li>
                </ul>
            </nav>
            <div class="user-actions">
                <a href="../index.php" class="btn btn--outline btn--sm">Public Site &rarr;</a>
                <a href="../logout.php" class="btn btn--ghost btn--sm">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="section-header" style="margin-bottom: 24px;">
            <div>
                <h1 class="section-title">Screening Schedule Management</h1>
                <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
                    Schedule showtimes across Auditorium Halls A, B, and C.
                </p>
            </div>
            <?php if ($action === 'list'): ?>
                <a href="manage_screenings.php?action=add" class="btn btn--primary btn--sm">+ Schedule Screening</a>
            <?php else: ?>
                <a href="manage_screenings.php" class="btn btn--ghost btn--sm">&larr; Back to Screenings List</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert--<?php echo $msgType; ?>">
                ℹ️ <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'add'): ?>
            <!-- Add Screening Form -->
            <div class="form-card" style="max-width: 600px; margin: 0 auto; padding: 32px;">
                <h2 style="font-size: 22px; color: var(--color-primary-light); margin-bottom: 20px;">
                    + Schedule New Movie Showtime
                </h2>

                <form method="POST" action="manage_screenings.php?action=add">
                    <div class="form-group">
                        <label class="form-label" for="s_movie">Select Movie *</label>
                        <select id="s_movie" name="movie_id" class="form-control" required>
                            <option value="">-- Choose Now Showing Title --</option>
                            <?php while ($m = $moviesList->fetch_assoc()): ?>
                                <option value="<?php echo (int)$m['movie_id']; ?>"><?php echo htmlspecialchars($m['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="s_hall">Auditorium Hall *</label>
                        <select id="s_hall" name="hall_id" class="form-control" required>
                            <option value="">-- Choose Hall --</option>
                            <?php while ($h = $hallsList->fetch_assoc()): ?>
                                <option value="<?php echo (int)$h['hall_id']; ?>">
                                    <?php echo htmlspecialchars($h['hall_name']); ?> (<?php echo htmlspecialchars($h['experience_type']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label" for="s_date">Screening Date *</label>
                            <input type="date" id="s_date" name="screening_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="s_time">Showtime *</label>
                            <input type="time" id="s_time" name="screening_time" class="form-control" value="19:30" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block btn--lg">
                        Add to Schedule &rarr;
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Screenings Table -->
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Movie Title</th>
                            <th>Date</th>
                            <th>Showtime</th>
                            <th>Cinema Hall</th>
                            <th>Experience</th>
                            <th>Booked Seats</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $screeningsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$s['screening_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($s['movie_title']); ?></strong></td>
                                <td><?php echo date('D, d M Y', strtotime($s['screening_date'])); ?></td>
                                <td>
                                    <strong style="font-family:monospace; color:var(--color-primary-light);">
                                        <?php echo date('h:i A', strtotime($s['screening_time'])); ?>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($s['hall_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['experience_type']); ?></td>
                                <td>
                                    <span class="pill-tag" style="background:#1e293b; color:#ffffff;">
                                        <?php echo (int)$s['booked_count']; ?> Booked
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="manage_screenings.php?action=delete&id=<?php echo (int)$s['screening_id']; ?>" 
                                       class="btn btn--danger btn--sm" 
                                       onclick="return confirm('Delete this screening schedule?');">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
