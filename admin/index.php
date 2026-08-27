<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

// Require Admin access
requireAdmin();

$pageTitle = "Admin Dashboard - Silver Village Cinema";

// Aggregate Stats (SQL SELECT)
$statsBookings = $conn->query("SELECT COUNT(*) AS total, SUM(total_price) AS revenue FROM bookings WHERE status = 'confirmed'")->fetch_assoc();
$statsMovies = $conn->query("SELECT COUNT(*) AS total FROM movies WHERE status = 'now_showing'")->fetch_assoc();
$statsUsers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")->fetch_assoc();
$statsScreenings = $conn->query("SELECT COUNT(*) AS total FROM screenings WHERE screening_date >= CURDATE()")->fetch_assoc();

// Recent 5 bookings
$recentBookings = $conn->query("
    SELECT b.booking_id, b.booking_reference, b.total_price, b.status, b.booking_date,
           u.full_name, m.title AS movie_title, s.screening_date, s.screening_time, h.hall_name
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN screenings s ON b.screening_id = s.screening_id
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    ORDER BY b.booking_date DESC
    LIMIT 6
");
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
            <a href="../index.php" class="brand-logo">
                <div class="logo-icon" style="background:#4f46e5;">⚡</div>
                <div class="logo-text">
                    <span class="logo-title">SILVER VILLAGE</span>
                    <span class="logo-sub">ADMINISTRATION</span>
                </div>
            </a>

            <nav class="main-nav">
                <ul class="nav-list">
                    <li><a href="index.php" class="nav-link active">Dashboard</a></li>
                    <li><a href="manage_movies.php" class="nav-link">Manage Movies</a></li>
                    <li><a href="manage_screenings.php" class="nav-link">Screenings</a></li>
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
        <div class="section-header" style="margin-bottom: 28px;">
            <div>
                <h1 class="section-title">Cinema Operations Dashboard</h1>
                <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
                    Real-time overview of ticket revenue, screening schedules, and movie catalogue.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="manage_movies.php?action=add" class="btn btn--primary btn--sm">+ Add Movie</a>
                <a href="manage_screenings.php?action=add" class="btn btn--outline btn--sm">+ Add Screening</a>
            </div>
        </div>

        <!-- 4 KPI Stat Cards Grid -->
        <div class="perks-grid" style="margin-bottom: 36px;">
            <div class="perk-card" style="background:#121620; border-color: rgba(212,175,55,0.3);">
                <span class="perk-icon">💰</span>
                <div>
                    <span style="font-size: 12px; color: var(--color-text-muted); text-transform: uppercase;">Total Ticket Revenue</span>
                    <h2 style="font-size: 26px; color: var(--color-primary-light); margin: 4px 0;">
                        $<?php echo number_format((float)($statsBookings['revenue'] ?? 0), 2); ?>
                    </h2>
                    <small style="color: var(--color-text-dim);"><?php echo (int)($statsBookings['total'] ?? 0); ?> Confirmed Bookings</small>
                </div>
            </div>

            <div class="perk-card" style="background:#121620;">
                <span class="perk-icon">🎬</span>
                <div>
                    <span style="font-size: 12px; color: var(--color-text-muted); text-transform: uppercase;">Now Showing Movies</span>
                    <h2 style="font-size: 26px; color: #ffffff; margin: 4px 0;">
                        <?php echo (int)($statsMovies['total'] ?? 0); ?>
                    </h2>
                    <small style="color: var(--color-text-dim);">Titles currently in rotation</small>
                </div>
            </div>

            <div class="perk-card" style="background:#121620;">
                <span class="perk-icon">📅</span>
                <div>
                    <span style="font-size: 12px; color: var(--color-text-muted); text-transform: uppercase;">Upcoming Screenings</span>
                    <h2 style="font-size: 26px; color: #ffffff; margin: 4px 0;">
                        <?php echo (int)($statsScreenings['total'] ?? 0); ?>
                    </h2>
                    <small style="color: var(--color-text-dim);">Across Halls A, B, and C</small>
                </div>
            </div>

            <div class="perk-card" style="background:#121620;">
                <span class="perk-icon">👥</span>
                <div>
                    <span style="font-size: 12px; color: var(--color-text-muted); text-transform: uppercase;">Registered Customers</span>
                    <h2 style="font-size: 26px; color: #ffffff; margin: 4px 0;">
                        <?php echo (int)($statsUsers['total'] ?? 0); ?>
                    </h2>
                    <small style="color: var(--color-text-dim);">Premiere Club members</small>
                </div>
            </div>
        </div>

        <!-- Recent Transactions Table -->
        <div class="section-header" style="margin-bottom: 16px;">
            <h2 class="section-title" style="font-size: 20px;">Recent Ticket Bookings</h2>
            <a href="view_bookings.php" class="btn btn--ghost btn--sm">View All Bookings &rarr;</a>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Customer</th>
                        <th>Movie Title</th>
                        <th>Screening Time</th>
                        <th>Hall</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($rb = $recentBookings->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; color: var(--color-primary-light);">
                                    <?php echo htmlspecialchars($rb['booking_reference']); ?>
                                </strong>
                            </td>
                            <td><?php echo htmlspecialchars($rb['full_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars($rb['movie_title']); ?></strong></td>
                            <td>
                                <?php echo date('D, d M', strtotime($rb['screening_date'])); ?> @ 
                                <?php echo date('h:i A', strtotime($rb['screening_time'])); ?>
                            </td>
                            <td><?php echo htmlspecialchars($rb['hall_name']); ?></td>
                            <td>\$<?php echo number_format($rb['total_price'], 2); ?></td>
                            <td>
                                <?php if ($rb['status'] === 'confirmed'): ?>
                                    <span class="status-badge status-badge--success">Confirmed</span>
                                <?php else: ?>
                                    <span class="status-badge status-badge--danger">Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-container" style="text-align: center; color: var(--color-text-dim); font-size: 12px;">
            Silver Village Cinema • Admin Management Portal • IE4727 Project
        </div>
    </footer>
</body>
</html>
