<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$sql = "
    SELECT b.booking_id, b.booking_reference, b.total_price, b.status, b.payment_status, b.booking_date,
           u.full_name, u.email, u.phone,
           m.title AS movie_title, s.screening_date, s.screening_time, h.hall_name,
           GROUP_CONCAT(bs.seat_label ORDER BY bs.seat_label SEPARATOR ', ') AS seats_list
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN screenings s ON b.screening_id = s.screening_id
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    LEFT JOIN booked_seats bs ON b.booking_id = bs.booking_id
    WHERE 1=1
";
$types = "";
$params = [];

if ($statusFilter === 'confirmed' || $statusFilter === 'cancelled') {
    $sql .= " AND b.status = ?";
    $types .= "s";
    $params[] = $statusFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND DATE(b.booking_date) = ?";
    $types .= "s";
    $params[] = $dateFilter;
}

$sql .= " GROUP BY b.booking_id ORDER BY b.booking_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookingsResult = $stmt->get_result();

// Revenue calculation for filtered view
$totalRevenue = 0;
$confirmedCount = 0;
$rows = [];
while ($row = $bookingsResult->fetch_assoc()) {
    $rows[] = $row;
    if ($row['status'] === 'confirmed') {
        $totalRevenue += (float)$row['total_price'];
        $confirmedCount++;
    }
}

$pageTitle = "All Bookings Report - Silver Village Admin";
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
                    <li><a href="manage_screenings.php" class="nav-link">Screenings</a></li>
                    <li><a href="view_bookings.php" class="nav-link active">All Bookings</a></li>
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
                <h1 class="section-title">All Customer Bookings Report</h1>
                <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
                    Server-side dynamic report displaying all customer reservations, seat allocations, and payment statuses.
                </p>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="form-card" style="max-width: 100%; padding: 18px 24px; margin-bottom: 24px; background: #121620;">
            <form method="GET" action="view_bookings.php" style="display: grid; grid-template-columns: 2fr 2fr auto auto; gap: 16px; align-items: end;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="b_status">Booking Status</label>
                    <select id="b_status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="confirmed" <?php echo ($statusFilter === 'confirmed') ? 'selected' : ''; ?>>Confirmed Only</option>
                        <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled Only</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="b_date">Booking Date</label>
                    <input type="date" id="b_date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                </div>

                <button type="submit" class="btn btn--primary" style="height: 42px;">Filter Report</button>
                <?php if (!empty($statusFilter) || !empty($dateFilter)): ?>
                    <a href="view_bookings.php" class="btn btn--ghost" style="height: 42px;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Summary Strip -->
        <div style="display: flex; gap: 20px; margin-bottom: 20px; font-size: 14px; background: #181b25; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 12px 20px;">
            <div>Total Transactions: <strong><?php echo count($rows); ?></strong></div>
            <div>Confirmed: <strong style="color:#34d399;"><?php echo $confirmedCount; ?></strong></div>
            <div>Report Revenue: <strong style="color:var(--color-primary-light);">$<?php echo number_format($totalRevenue, 2); ?></strong></div>
        </div>

        <!-- Bookings Table -->
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Customer</th>
                        <th>Movie & Showtime</th>
                        <th>Hall</th>
                        <th>Seats</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Booked On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) > 0): ?>
                        <?php foreach ($rows as $b): ?>
                            <tr>
                                <td>
                                    <strong style="font-family:monospace; color:var(--color-primary-light);">
                                        <?php echo htmlspecialchars($b['booking_reference']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($b['full_name']); ?></strong>
                                    <small style="display:block; color:var(--color-text-dim);"><?php echo htmlspecialchars($b['email']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($b['movie_title']); ?></strong>
                                    <small style="display:block; color:var(--color-text-muted);">
                                        <?php echo date('d M Y', strtotime($b['screening_date'])); ?> @ <?php echo date('h:i A', strtotime($b['screening_time'])); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($b['hall_name']); ?></td>
                                <td>
                                    <span class="pill-tag" style="background:#262a34; color:var(--color-primary-light); font-weight:bold;">
                                        <?php echo htmlspecialchars($b['seats_list'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td><strong>$<?php echo number_format($b['total_price'], 2); ?></strong></td>
                                <td>
                                    <span style="font-size: 11px; text-transform: uppercase; font-weight: bold; color: <?php echo ($b['payment_status'] === 'success') ? '#34d399' : '#f87171'; ?>;">
                                        <?php echo htmlspecialchars($b['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'confirmed'): ?>
                                        <span class="status-badge status-badge--success">Confirmed</span>
                                    <?php else: ?>
                                        <span class="status-badge status-badge--danger">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M, h:i A', strtotime($b['booking_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--color-text-muted); padding: 32px;">
                                No bookings found matching the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
