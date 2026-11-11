<?php
$pageTitle = "CinéPop - Your Seat, Your Show, Your Way";
require_once __DIR__ . '/includes/header.php';

// Fetch the four featured movies from the database.
// Keep Spider-Man first, followed by the other current releases.
$featuredMovies = $conn->query(
    "SELECT * FROM movies
     WHERE status = 'now_showing'
     ORDER BY (title LIKE '%Spider-Man%') DESC,
              release_date DESC,
              movie_id ASC
     LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);

// Assign each featured movie its landscape backdrop.
foreach ($featuredMovies as &$feature) {

    // Get the movie's landscape backdrop from the database
    $backdropFile = $feature['backdrop_image'] ?? '';
    $backdropPath = 'images/' . $backdropFile;

    if (
        $backdropFile !== '' &&
        file_exists(__DIR__ . '/' . $backdropPath)
    ) {
        // Use the dedicated landscape backdrop
        $feature['backdrop'] = $backdropPath;
        $feature['is_poster'] = false;

    } else {
        // Fallback to the movie poster if no backdrop exists
        $feature['backdrop'] = 'images/posters/' . $feature['poster_image'];
        $feature['is_poster'] = true;
    }

    // Automatically refresh the image when its file changes
    if (file_exists(__DIR__ . '/' . $feature['backdrop'])) {
        $feature['backdrop'] .= '?v=' .
            filemtime(__DIR__ . '/' . $feature['backdrop']);
    }
}
unset($feature);
$featuredMovie = $featuredMovies[0] ?? null;

// Fetch Now Showing Movies
$nowShowingResult = $conn->query("SELECT * FROM movies WHERE status = 'now_showing' ORDER BY release_date DESC LIMIT 6");

// Fetch Coming Soon Movies
$comingSoonResult = $conn->query("SELECT * FROM movies WHERE status = 'coming_soon' ORDER BY release_date ASC LIMIT 4");
?>

<!-- Hero Banner -->
<?php if ($featuredMovie): ?>
<section class="hero-section movie-rotation" aria-label="Featured movies" aria-roledescription="carousel">
<div class="hero-backdrop <?= !empty($featuredMovie['is_poster']) ? 'is-poster' : '' ?>"
     style="background-image: url('<?= htmlspecialchars($featuredMovie['backdrop'], ENT_QUOTES, 'UTF-8') ?>');"></div>
    <div class="hero-overlay">
        <span class="hero-badge">★ Featured Premiere of the Month</span>
        <h1 class="hero-title"><?php echo htmlspecialchars($featuredMovie['title']); ?></h1>
        <div class="hero-meta">
            <span class="pill-rating"><?php echo htmlspecialchars($featuredMovie['rating']); ?></span>
            <span class="pill-tag"><?php echo htmlspecialchars($featuredMovie['duration_minutes']); ?> Mins</span>
            <span class="pill-tag"><?php echo htmlspecialchars($featuredMovie['genre']); ?></span>
            <span class="pill-tag">Dolby Atmos 4K Laser</span>
        </div>
        <div class="hero-actions">
            <a href="movie_details.php?id=<?php echo (int)$featuredMovie['movie_id']; ?>" class="btn btn--primary btn--lg" data-feature-booking>
                Book Tickets & Choose Seats
            </a>
            <a href="movies.php" class="btn btn--outline btn--lg">
                Explore All Movies
            </a>
        </div>
    </div>
    <?php if (count($featuredMovies) > 1): ?>
    <div class="movie-rotation-controls" hidden>
        <button type="button" data-feature-prev aria-label="Previous featured movie">&#8592;</button>
        <span data-feature-position>1 / <?= count($featuredMovies) ?></span>
        <button type="button" data-feature-next aria-label="Next featured movie">&#8594;</button>
    </div>
    <?php endif; ?>
</section>
<p class="featured-synopsis"><?php echo htmlspecialchars($featuredMovie['synopsis']); ?></p>
<p class="sr-only" id="featuredMovieAnnouncement" aria-live="polite" aria-atomic="true"></p>
<div id="featuredMovieData" hidden>
    <?php foreach ($featuredMovies as $movie): ?>
        <div
            data-movie-id="<?= (int)$movie['movie_id'] ?>"
            data-title="<?= htmlspecialchars($movie['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-rating="<?= htmlspecialchars($movie['rating'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-duration-minutes="<?= (int)$movie['duration_minutes'] ?>"
            data-genre="<?= htmlspecialchars($movie['genre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-backdrop="<?= htmlspecialchars($movie['backdrop'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-is-poster="<?= $movie['is_poster'] ? '1' : '0' ?>"
            data-synopsis="<?= htmlspecialchars($movie['synopsis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        ></div>
    <?php endforeach; ?>
</div>
<script src="js/movie-rotation.js?v=<?= filemtime(__DIR__ . '/js/movie-rotation.js') ?>" defer></script>
<?php endif; ?>

<!-- Quick Preference Highlight Callout -->
<div class="alert alert--info" style="margin-bottom: 40px; justify-content: space-between; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 24px;"></span>
        <div>
            <strong style="color: #ffffff;">Introducing Multi-Booking Preference Shortlists:</strong>
            <p style="margin: 0; font-size: 13px; color: var(--color-text-muted);">Shortlist multiple showtimes ranked by your preference (#1, #2, #3), review live seat availability, and confirm one or more bookings together!</p>
        </div>
    </div>
    <a href="wishlist.php" class="btn btn--primary btn--sm" style="margin-top: 8px;">View My Wishlist</a>
</div>

<!-- Now Showing Section -->
<section class="movies-section">
    <div class="section-header">
        <h2 class="section-title">Now Showing in Theatres</h2>
        <a href="movies.php?status=now_showing" class="btn btn--ghost btn--sm">View All Now Showing &rarr;</a>
    </div>

    <div class="movie-grid">
        <?php while ($movie = $nowShowingResult->fetch_assoc()): ?>
            <article class="movie-card">
                <div class="movie-poster-wrap">
                    <span class="card-rating-badge"><?php echo htmlspecialchars($movie['rating']); ?></span>
                    <?php if (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])): ?>
                        <img
                        src="images/posters/<?php echo rawurlencode($movie['poster_image']); ?>?v=<?php echo filemtime(__DIR__ . '/images/posters/' . $movie['poster_image']); ?>"
                        alt="<?php echo htmlspecialchars($movie['title']); ?> Poster"
                        loading="lazy"
                        >
                    <?php else: ?>
                        <div class="poster-fallback">
                            <span class="poster-fallback-icon">🎬</span>
                            <strong class="poster-fallback-title"><?php echo htmlspecialchars($movie['title']); ?></strong>
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
                        <span>Direct: <?php echo htmlspecialchars(explode(' ', $movie['director'])[0]); ?></span>
                    </div>
                    <div class="movie-card-footer">
                        <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>" class="btn btn--primary btn--sm btn--block">
                            View Showtimes & Book
                        </a>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</section>

<!-- Coming Soon Section -->
<section class="movies-section">
    <div class="section-header">
        <h2 class="section-title">Coming Soon to CinéPop</h2>
        <a href="movies.php?status=coming_soon" class="btn btn--ghost btn--sm">View All Coming Soon &rarr;</a>
    </div>

    <div class="movie-grid">
        <?php while ($movie = $comingSoonResult->fetch_assoc()): ?>
            <article class="movie-card">
                <div class="movie-poster-wrap">
                    <span class="card-rating-badge" style="background:#4f46e5;"><?php echo htmlspecialchars($movie['rating']); ?></span>
                    <?php if (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])): ?>
                        <img
                        src="images/posters/<?php echo rawurlencode($movie['poster_image']); ?>?v=<?php echo filemtime(__DIR__ . '/images/posters/' . $movie['poster_image']); ?>"
                        alt="<?php echo htmlspecialchars($movie['title']); ?> Poster"
                        loading="lazy"
                        >
                    <?php else: ?>
                        <div class="poster-fallback" style="background: linear-gradient(135deg, #1e1b4b, #06162b);">
                            <span class="poster-fallback-icon">⏳</span>
                            <strong class="poster-fallback-title"><?php echo htmlspecialchars($movie['title']); ?></strong>
                            <small style="color:var(--color-primary-light); margin-top:8px;">Releasing <?php echo date('d M Y', strtotime($movie['release_date'])); ?></small>
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
                        <span>Opens: <?php echo date('d M', strtotime($movie['release_date'])); ?></span>
                    </div>
                    <div class="movie-card-footer">
                        <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>" class="btn btn--outline btn--sm btn--block">
                            Movie Details
                        </a>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</section>

<!-- Cinema Perks & Experience Highlights -->
<section class="perks-section">
    <div class="section-header">
        <h2 class="section-title">The CinéPop Experience</h2>
    </div>
    <div class="perks-grid">
        <div class="perk-card">
            <div>
                <h3 class="perk-title">Dolby Atmos 4K Laser</h3>
                <p class="perk-desc">Multi-dimensional audio and crystal clear 4K laser projection delivering breathtaking realism.</p>
            </div>
        </div>
        <div class="perk-card">
            <div>
                <h3 class="perk-title">VIP Gold Class Recliners</h3>
                <p class="perk-desc">Spacious plush leather recliners with USB charging ports and in-hall attendant call button.</p>
            </div>
        </div>
        <div class="perk-card">
            <div>
                <h3 class="perk-title">Multi-Booking Preferences</h3>
                <p class="perk-desc">Rank your ideal showtimes and confirm all your movie plans in a single seamless transaction.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
