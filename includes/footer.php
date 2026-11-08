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
                        <li><span><strong>Hall A:</strong> Dolby Atmos 4K Laser (60 Seats)</span></li>
                        <li><span><strong>Hall B:</strong> VIP Premiere Recliners (80 Seats)</span></li>
                        <li><span><strong>Hall C:</strong> Standard Digital Cinema (100 Seats)</span></li>
                    </ul>
                </div>

                <!-- Col 4: Location & Local Mail Notice -->
                <div class="footer-col">
                    <h4 class="footer-heading">Cinema Information</h4>
                    <p class="footer-info"><svg width="18" height="18" viewBox="0 0 24 24"
     fill="none" stroke="white" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round"
     style="width:18px; height:18px; display:inline-block; vertical-align:middle; margin-right:6px; flex-shrink:0;"
     aria-hidden="true">
    <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
    <circle cx="12" cy="10" r="3"/>
</svg>50 Nanyang Avenue, Singapore 639798</p>
                    <p class="footer-info"><svg width="18" height="18" viewBox="0 0 24 24"
     fill="none" stroke="white" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round"
     style="width:18px; height:18px; display:inline-block; vertical-align:middle; margin-right:6px; flex-shrink:0;"
     aria-hidden="true">
    <path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 3.09 5.18 2 2 0 0 1 5.08 3h3a2 2 0 0 1 2 1.72c.12.96.34 1.91.65 2.81a2 2 0 0 1-.45 2.11L9.09 10.91a16 16 0 0 0 4 4l1.27-1.19a2 2 0 0 1 2.11-.45c.9.31 1.85.53 2.81.65A2 2 0 0 1 22 16.92Z"/>
</svg>+65 6791 1744 (10:00 AM – 11:30 PM)</p>
                    <div class="footer-notice-box">
                        <small><strong>Digital Admission:</strong> Successful-booking acknowledgements are delivered to your local mailbox for viewing in Thunderbird.</small>
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
