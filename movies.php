<?php
$pageTitle = "Movie Directory - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';

// Filter parameters from GET
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$genreFilter = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build dynamic SQL query safely with prepared statements
$sql = "SELECT * FROM movies WHERE 1=1";
$types = "";
$params = [];

if ($statusFilter === 'now_showing' || $statusFilter === 'coming_soon') {
    $sql .= " AND status = ?";
    $types .= "s";
    $params[] = $statusFilter;
}

if (!empty($genreFilter)) {
    $sql .= " AND genre LIKE ?";
    $types .= "s";
    $params[] = "%" . $genreFilter . "%";
}

if (!empty($searchQuery)) {
    $sql .= " AND (title LIKE ? OR cast LIKE ? OR director LIKE ?)";
    $types .= "sss";
    $searchPattern = "%" . $searchQuery . "%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$sql .= " ORDER BY status DESC, release_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$moviesResult = $stmt->get_result();

// Get distinct genres for the filter dropdown
$genreQuery = $conn->query("SELECT DISTINCT genre FROM movies");
$allGenres = [];
while ($gRow = $genreQuery->fetch_assoc()) {
    $parts = explode(',', $gRow['genre']);
    foreach ($parts as $p) {
        $trimmed = trim($p);
        if ($trimmed && !in_array($trimmed, $allGenres)) {
            $allGenres[] = $trimmed;
        }
    }
}
sort($allGenres);
?>

<div class="section-header" style="margin-bottom: 28px;">
    <div>
        <h1 class="section-title">Explore Cinema Titles</h1>
        <p style="color: var(--color-text-muted); margin-top: 4px; font-size: 14px;">
            Browse all currently screening movies, upcoming blockbuster releases, and secure your seats early.
        </p>
    </div>
</div>

<!-- Filter & Search Bar (Server-Side Form Processing) -->
<div class="form-card" style="max-width: 100%; padding: 20px 24px; margin-bottom: 36px; background: #121620;">
    <form method="GET" action="movies.php" style="display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto auto; gap: 16px; align-items: end;">
        <!-- Search Keyword -->
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="search_q">Search Movie / Cast</label>
            <input type="text" id="search_q" name="q" class="form-control" placeholder="e.g. Spider-Man, Nolan, Action..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>

        <!-- Status Filter -->
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="status_filter">Release Status</label>
            <select id="status_filter" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="now_showing" <?php echo ($statusFilter === 'now_showing') ? 'selected' : ''; ?>>Now Showing</option>
                <option value="coming_soon" <?php echo ($statusFilter === 'coming_soon') ? 'selected' : ''; ?>>Coming Soon</option>
            </select>
        </div>

        <!-- Genre Filter -->
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="genre_filter">Genre</label>
            <select id="genre_filter" name="genre" class="form-control">
                <option value="">All Genres</option>
                <?php foreach ($allGenres as $g): ?>
                    <option value="<?php echo htmlspecialchars($g); ?>" <?php echo ($genreFilter === $g) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Submit Filter -->
        <button type="submit" class="btn btn--primary" style="height: 42px;">
            Filter
        </button>

        <!-- Reset Filter -->
        <?php if (!empty($statusFilter) || !empty($genreFilter) || !empty($searchQuery)): ?>
            <a href="movies.php" class="btn btn--ghost" style="height: 42px;" title="Clear filters">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Movies Listing Grid -->
<?php if ($moviesResult->num_rows > 0): ?>
    <div class="movie-grid">
        <?php while ($movie = $moviesResult->fetch_assoc()): ?>
            <article class="movie-card">
                <div class="movie-poster-wrap">
                    <span class="card-rating-badge" style="<?php echo ($movie['status'] === 'coming_soon') ? 'background:#4f46e5;' : ''; ?>">
                        <?php echo htmlspecialchars($movie['rating']); ?>
                    </span>
                    <?php if (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])): ?>
                        <img src="images/posters/<?php echo htmlspecialchars($movie['poster_image']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?> Poster" loading="lazy">
                    <?php else: ?>
                        <div class="poster-fallback" style="<?php echo ($movie['status'] === 'coming_soon') ? 'background: linear-gradient(135deg, #1e1b4b, #0a0e17);' : ''; ?>">
                            <span class="poster-fallback-icon"><?php echo ($movie['status'] === 'coming_soon') ? '⏳' : '🎬'; ?></span>
                            <strong class="poster-fallback-title"><?php echo htmlspecialchars($movie['title']); ?></strong>
                            <?php if ($movie['status'] === 'coming_soon'): ?>
                                <small style="color:var(--color-primary-light); margin-top:8px;">Opens <?php echo date('d M Y', strtotime($movie['release_date'])); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="movie-card-content">
                    <h3 class="movie-card-title">
                        <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>">
                            <?php echo htmlspecialchars($movie['title']); ?>
                        </a>
                    </h3>
                    <p class="movie-card-genre"><?php echo htmlspecialchars($movie['genre']); ?></p>
                    <div class="movie-card-info">
                        <span>⏱ <?php echo (int)$movie['duration_minutes']; ?> min</span>
                        <span>•</span>
                        <span>Dir: <?php echo htmlspecialchars(explode(' ', $movie['director'])[0]); ?></span>
                    </div>
                    <div class="movie-card-footer">
                        <?php if ($movie['status'] === 'now_showing'): ?>
                            <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>" class="btn btn--primary btn--sm btn--block">
                                View Showtimes & Book
                            </a>
                        <?php else: ?>
                            <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>" class="btn btn--outline btn--sm btn--block">
                                Movie Details
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="form-card" style="text-align: center; max-width: 500px; padding: 48px 24px;">
        <span style="font-size: 48px; display: block; margin-bottom: 16px;">🔍</span>
        <h3 style="color: #ffffff; margin-bottom: 8px;">No Movies Found</h3>
        <p style="color: var(--color-text-muted); margin-bottom: 24px;">
            No cinema titles matched your search criteria. Try adjusting your search keyword or clearing the filters.
        </p>
        <a href="movies.php" class="btn btn--primary">View All Movies</a>
    </div>
<?php endif; ?>

<?php
$stmt->close();
require_once __DIR__ . '/includes/footer.php';
?>
