<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$msg = '';
$msgType = 'success';
$action = $_GET['action'] ?? 'list';
$editMovieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle Delete (SQL DELETE)
if ($action === 'delete' && $editMovieId > 0) {
    $delStmt = $conn->prepare("DELETE FROM movies WHERE movie_id = ?");
    $delStmt->bind_param("i", $editMovieId);
    if ($delStmt->execute()) {
        $msg = "Movie successfully deleted from catalogue.";
    } else {
        $msg = "Could not delete movie. It may have existing bookings.";
        $msgType = 'danger';
    }
    $delStmt->close();
    $action = 'list';
}

// Handle Add / Edit POST Submission (SQL INSERT / UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $synopsis = trim($_POST['synopsis'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 120);
    $rating = trim($_POST['rating'] ?? 'PG13');
    $director = trim($_POST['director'] ?? '');
    $cast = trim($_POST['cast'] ?? '');
    $status = $_POST['status'] ?? 'now_showing';
    $releaseDate = $_POST['release_date'] ?? date('Y-m-d');
    $poster = trim($_POST['poster_image'] ?? 'placeholder.jpg');

    if ($action === 'add') {
        $insStmt = $conn->prepare("
            INSERT INTO movies (title, synopsis, genre, duration_minutes, rating, director, cast, status, release_date, poster_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->bind_param("sssissssss", $title, $synopsis, $genre, $duration, $rating, $director, $cast, $status, $releaseDate, $poster);
        if ($insStmt->execute()) {
            $msg = "New movie '$title' successfully added!";
            $action = 'list';
        }
        $insStmt->close();
    } elseif ($action === 'edit' && $editMovieId > 0) {
        $upStmt = $conn->prepare("
            UPDATE movies 
            SET title = ?, synopsis = ?, genre = ?, duration_minutes = ?, rating = ?, director = ?, cast = ?, status = ?, release_date = ? 
            WHERE movie_id = ?
        ");
        $upStmt->bind_param("sssisssssi", $title, $synopsis, $genre, $duration, $rating, $director, $cast, $status, $releaseDate, $editMovieId);
        if ($upStmt->execute()) {
            $msg = "Movie '$title' successfully updated!";
            $action = 'list';
        }
        $upStmt->close();
    }
}

// Fetch Movie Details for Edit Mode
$movieData = null;
if ($action === 'edit' && $editMovieId > 0) {
    $eStmt = $conn->prepare("SELECT * FROM movies WHERE movie_id = ?");
    $eStmt->bind_param("i", $editMovieId);
    $eStmt->execute();
    $movieData = $eStmt->get_result()->fetch_assoc();
    $eStmt->close();
}

// Fetch all movies for list view
$allMovies = $conn->query("SELECT * FROM movies ORDER BY status DESC, release_date DESC");

$pageTitle = "Manage Movies - Silver Village Admin";
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
                    <li><a href="manage_movies.php" class="nav-link active">Manage Movies</a></li>
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
        <div class="section-header" style="margin-bottom: 24px;">
            <div>
                <h1 class="section-title">Movie Management</h1>
                <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
                    Create, update, and manage cinema movie titles, ratings, and release statuses.
                </p>
            </div>
            <?php if ($action === 'list'): ?>
                <a href="manage_movies.php?action=add" class="btn btn--primary btn--sm">+ Add New Movie</a>
            <?php else: ?>
                <a href="manage_movies.php" class="btn btn--ghost btn--sm">&larr; Back to Movies List</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert--<?php echo $msgType; ?>">
                ℹ️ <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $movieData)): ?>
            <!-- Add / Edit Movie Form -->
            <div class="form-card" style="max-width: 800px; margin: 0 auto; padding: 32px;">
                <h2 style="font-size: 22px; color: var(--color-primary-light); margin-bottom: 20px;">
                    <?php echo ($action === 'add') ? '+ Add New Movie to Catalogue' : '✏️ Edit Movie: ' . htmlspecialchars($movieData['title']); ?>
                </h2>

                <form method="POST" action="manage_movies.php?action=<?php echo $action; ?><?php echo ($action === 'edit') ? '&id=' . $editMovieId : ''; ?>">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label" for="m_title">Movie Title *</label>
                            <input type="text" id="m_title" name="title" class="form-control" value="<?php echo htmlspecialchars($movieData['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="m_rating">Age Rating *</label>
                            <select id="m_rating" name="rating" class="form-control" required>
                                <?php foreach (['G', 'PG', 'PG13', 'NC16', 'M18', 'R21'] as $r): ?>
                                    <option value="<?php echo $r; ?>" <?php echo (($movieData['rating'] ?? '') === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label" for="m_genre">Genre(s) *</label>
                            <input type="text" id="m_genre" name="genre" class="form-control" placeholder="Action, Sci-Fi" value="<?php echo htmlspecialchars($movieData['genre'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="m_duration">Duration (Minutes) *</label>
                            <input type="number" id="m_duration" name="duration_minutes" class="form-control" value="<?php echo (int)($movieData['duration_minutes'] ?? 120); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="m_status">Status *</label>
                            <select id="m_status" name="status" class="form-control" required>
                                <option value="now_showing" <?php echo (($movieData['status'] ?? '') === 'now_showing') ? 'selected' : ''; ?>>Now Showing</option>
                                <option value="coming_soon" <?php echo (($movieData['status'] ?? '') === 'coming_soon') ? 'selected' : ''; ?>>Coming Soon</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label" for="m_director">Director *</label>
                            <input type="text" id="m_director" name="director" class="form-control" value="<?php echo htmlspecialchars($movieData['director'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="m_release">Release Date *</label>
                            <input type="date" id="m_release" name="release_date" class="form-control" value="<?php echo htmlspecialchars($movieData['release_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_cast">Starring Cast</label>
                        <input type="text" id="m_cast" name="cast" class="form-control" placeholder="e.g. Tom Holland, Zendaya" value="<?php echo htmlspecialchars($movieData['cast'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_synopsis">Synopsis / Plot Summary *</label>
                        <textarea id="m_synopsis" name="synopsis" class="form-control" rows="4" required><?php echo htmlspecialchars($movieData['synopsis'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block btn--lg">
                        <?php echo ($action === 'add') ? 'Save & Publish Movie' : 'Save Changes'; ?> &rarr;
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Movies Data Table -->
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Rating</th>
                            <th>Genre</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Release Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($m = $allMovies->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$m['movie_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                                    <small style="display:block; color:var(--color-text-dim);">Dir: <?php echo htmlspecialchars($m['director']); ?></small>
                                </td>
                                <td>
                                    <span class="pill-rating"><?php echo htmlspecialchars($m['rating']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($m['genre']); ?></td>
                                <td><?php echo (int)$m['duration_minutes']; ?> min</td>
                                <td>
                                    <?php if ($m['status'] === 'now_showing'): ?>
                                        <span class="status-badge status-badge--success">Now Showing</span>
                                    <?php else: ?>
                                        <span class="status-badge status-badge--warning">Coming Soon</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($m['release_date'])); ?></td>
                                <td style="text-align: right;">
                                    <a href="manage_movies.php?action=edit&id=<?php echo (int)$m['movie_id']; ?>" class="btn btn--outline btn--sm">Edit</a>
                                    <a href="manage_movies.php?action=delete&id=<?php echo (int)$m['movie_id']; ?>" class="btn btn--danger btn--sm" onclick="return confirm('Delete this movie?');">Delete</a>
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
