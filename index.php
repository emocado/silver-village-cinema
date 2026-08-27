<?php
$pageTitle = "Silver Village Cinema - Your Seat, Your Show, Your Way";
require_once __DIR__ . '/includes/header.php';

// Fetch Featured Movie (Spider-Man: Brand New Day)
$featuredStmt = $conn->prepare("SELECT * FROM movies WHERE status = 'now_showing' AND title LIKE '%Spider-Man%' LIMIT 1");
$featuredStmt->execute();
$featuredMovie = $featuredStmt->get_result()->fetch_assoc();
$featuredStmt->close();

if (!$featuredMovie) {
    $fallbackStmt = $conn->query("SELECT * FROM movies WHERE status = 'now_showing' LIMIT 1");
    $featuredMovie = $fallbackStmt->fetch_assoc();
}

// Fetch Now Showing Movies
$nowShowingResult = $conn->query("SELECT * FROM movies WHERE status = 'now_showing' ORDER BY release_date DESC LIMIT 6");

// Fetch Coming Soon Movies
$comingSoonResult = $conn->query("SELECT * FROM movies WHERE status = 'coming_soon' ORDER BY release_date ASC LIMIT 4");
?>

<!-- Hero Banner -->
<?php if ($featuredMovie): ?>
<section class="hero-section">
    <div class="hero-backdrop" style="background-image: url('images/hero_spiderman.jpg');"></div>
    <div class="hero-overlay">
        <span class="hero-badge">★ Featured Premiere of the Month</span>
        <h1 class="hero-title"><?php echo htmlspecialchars($featuredMovie['title']); ?></h1>
        <div class="hero-meta">
            <span class="pill-rating"><?php echo htmlspecialchars($featuredMovie['rating']); ?></span>
            <span class="pill-tag"><?php echo htmlspecialchars($featuredMovie['duration_minutes']); ?> Mins</span>
            <span class="pill-tag"><?php echo htmlspecialchars($featuredMovie['genre']); ?></span>
            <span class="pill-tag">Dolby Atmos 4K Laser</span>
        </div>
        <p class="hero-synopsis">
            <?php echo htmlspecialchars($featuredMovie['synopsis']); ?>
        </p>
        <div class="hero-actions">
            <a href="movie_details.php?id=<?php echo (int)$featuredMovie['movie_id']; ?>" class="btn btn--primary btn--lg">
                🎟️ Book Tickets & Choose Seats
            </a>
            <a href="movies.php" class="btn btn--outline btn--lg">
                Explore All Movies
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Quick Preference Highlight Callout -->
<div class="alert alert--info" style="margin-bottom: 40px; justify-content: space-between; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 24px;">📋</span>
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
        <a href="movies.php?filter=now_showing" class="btn btn--ghost btn--sm">View All Now Showing &rarr;</a>
    </div>

    <div class="movie-grid">
        <?php while ($movie = $nowShowingResult->fetch_assoc()): ?>
            <article class="movie-card">
                <div class="movie-poster-wrap">
                    <span class="card-rating-badge"><?php echo htmlspecialchars($movie['rating']); ?></span>
                    <?php if (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])): ?>
                        <img src="images/posters/<?php echo htmlspecialchars($movie['poster_image']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?> Poster" loading="lazy">
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
        <h2 class="section-title">Coming Soon to Silver Village</h2>
        <a href="movies.php?filter=coming_soon" class="btn btn--ghost btn--sm">View All Coming Soon &rarr;</a>
    </div>

    <div class="movie-grid">
        <?php while ($movie = $comingSoonResult->fetch_assoc()): ?>
            <article class="movie-card">
                <div class="movie-poster-wrap">
                    <span class="card-rating-badge" style="background:#4f46e5;"><?php echo htmlspecialchars($movie['rating']); ?></span>
                    <?php if (!empty($movie['poster_image']) && file_exists(__DIR__ . '/images/posters/' . $movie['poster_image'])): ?>
                        <img src="images/posters/<?php echo htmlspecialchars($movie['poster_image']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?> Poster" loading="lazy">
                    <?php else: ?>
                        <div class="poster-fallback" style="background: linear-gradient(135deg, #1e1b4b, #0a0e17);">
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
        <h2 class="section-title">The Silver Village Experience</h2>
    </div>
    <div class="perks-grid">
        <div class="perk-card">
            <div class="perk-icon">🔊</div>
            <div>
                <h3 class="perk-title">Dolby Atmos 4K Laser</h3>
                <p class="perk-desc">Multi-dimensional audio and crystal clear 4K laser projection delivering breathtaking realism.</p>
            </div>
        </div>
        <div class="perk-card">
            <div class="perk-icon">👑</div>
            <div>
                <h3 class="perk-title">VIP Gold Class Recliners</h3>
                <p class="perk-desc">Spacious plush leather recliners with USB charging ports and in-hall attendant call button.</p>
            </div>
        </div>
        <div class="perk-card">
            <div class="perk-icon">📋</div>
            <div>
                <h3 class="perk-title">Multi-Booking Preferences</h3>
                <p class="perk-desc">Rank your ideal showtimes and confirm all your movie plans in a single seamless transaction.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
