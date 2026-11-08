    </main>


    <?php if(($currentPage??'')==='index.php'):
        $quickFilms=$conn->query("SELECT movie_id,title FROM movies WHERE status='now_showing' ORDER BY title"); ?>
    <aside class="quick-book" aria-label="Quick booking">
        <form method="get" action="movie_details.php">
            <h2>Book a film</h2>
            <div><label for="quickFilm">Choose your film</label><select id="quickFilm" name="id" required><option value="">Select a film</option><?php while($quickFilm=$quickFilms->fetch_assoc()): ?><option value="<?= (int)$quickFilm['movie_id'] ?>"><?= htmlspecialchars($quickFilm['title']) ?></option><?php endwhile; ?></select></div>
            <div class="quick-book-date"><label for="quickDate">Choose your date</label><input id="quickDate" name="date" type="date" min="<?= date('Y-m-d') ?>" aria-label="Screening date, optional"></div>
            <button class="btn btn--primary">Find showtimes</button>
        </form>
    </aside>
    <?php endif; ?>

    <!-- Site Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <!-- Col 1: Cinema Identity -->
                <div class="footer-col footer-about">
                    <div class="footer-logo">
                        <span class="logo-title">CinéPop</span>
                        <span class="logo-sub">CINEMA</span>
                    </div>
                    <p class="footer-tagline">"Your Seat, Your Show, Your Way."</p>
                    <p class="footer-desc">
                        Singapore's premier cinematic destination featuring Dolby Atmos 4K Laser projection, VIP Gold Class recliners, and personalized multi-booking preferences.
                    </p>
                </div>

                <!-- Col 2: Quick Links -->
                <div class="footer-col">
                    <h4 class="footer-heading">Quick Navigation</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="movies.php">Now Showing & Coming Soon</a></li>
                        <li><a href="wishlist.php">Booking Wishlist & Preferences</a></li>
                        <li><a href="feedback.php">Customer Reviews & Feedback</a></li>
                        <li><a href="about.php">About Us & Cinema Halls</a></li>
                    </ul>
                </div>

                <!-- Col 3: Cinema Halls & Experience -->
                <div class="footer-col">
                    <h4 class="footer-heading">Our Screening Halls</h4>
                    <ul class="footer-links">
                        <li><span>🎭 <strong>Hall A:</strong> Dolby Atmos 4K Laser (60 Seats)</span></li>
                        <li><span>👑 <strong>Hall B:</strong> VIP Premiere Recliners (80 Seats)</span></li>
                        <li><span>🎬 <strong>Hall C:</strong> Standard Digital Cinema (100 Seats)</span></li>
                    </ul>
                </div>

                <!-- Col 4: Location & Local Mail Notice -->
                <div class="footer-col">
                    <h4 class="footer-heading">Cinema Information</h4>
                    <p class="footer-info">📍 50 Nanyang Avenue, Singapore 639798</p>
                    <p class="footer-info">📞 +65 6791 1744 (10:00 AM – 11:30 PM)</p>
                    <div class="footer-notice-box">
                        <small>🎟️ <strong>Digital Admission:</strong> Successful-booking acknowledgements are delivered to your local mailbox for viewing in Thunderbird.</small>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> CinéPop. All Rights Reserved.</p>
                <div class="footer-tech-stack">
                    <span>Dolby Atmos • 4K Laser Projection • VIP Gold Class</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Client-Side Form Validation JS (Project requirement) -->
    <script src="js/validation.js<?= $currentPage === 'account.php' ? '?v=registration-gmail' : '' ?>"></script><script src="js/site.js"></script>
</body>
</html>
