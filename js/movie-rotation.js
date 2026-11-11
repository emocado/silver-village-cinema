(() => {
    const hero = document.querySelector('.movie-rotation');
    const data = document.getElementById('featuredMovieData');
    if (!hero || !data) return;
    const movies = Array.from(data.children, item => item.dataset);
    if (movies.length < 2) return;
    const controls = hero.querySelector('.movie-rotation-controls');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let hovering = false;
    let timer;

    function schedule() {
        clearTimeout(timer);
        if (!reducedMotion.matches && !hovering && !document.hidden && !hero.contains(document.activeElement)) {
            timer = setTimeout(() => show(current + 1), 7000);
        }
    }
    function show(index, announce = false) {
        current = (index + movies.length) % movies.length;
        const movie = movies[current];
        hero.querySelector('.hero-title').textContent = movie.title;
        const metadata = hero.querySelector('.hero-meta').children;
        metadata[0].textContent = movie.rating;
        metadata[1].textContent = movie.durationMinutes + ' Mins';
        metadata[2].textContent = movie.genre;
        hero.querySelector('[data-feature-booking]').href = 'movie_details.php?id=' + Number(movie.movieId);
        hero.querySelector('.hero-backdrop').style.backgroundImage = `url(${CSS.escape(movie.backdrop)})`;
        hero.querySelector('.hero-backdrop').classList.toggle(
            'is-poster',
            movie.isPoster === '1'
        );
        document.querySelector('.featured-synopsis').textContent = movie.synopsis;
        hero.querySelector('[data-feature-position]').textContent = `${current + 1} / ${movies.length}`;
        if (announce) document.getElementById('featuredMovieAnnouncement').textContent = movie.title;
        schedule();
    }
    controls.hidden = false;
    hero.querySelector('[data-feature-prev]').addEventListener('click', () => show(current - 1, true));
    hero.querySelector('[data-feature-next]').addEventListener('click', () => show(current + 1, true));
    hero.addEventListener('mouseenter', () => { hovering = true; schedule(); });
    hero.addEventListener('mouseleave', () => { hovering = false; schedule(); });
    hero.addEventListener('focusin', schedule);
    hero.addEventListener('focusout', () => setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    reducedMotion.addEventListener('change', schedule);
    // Warm the next artwork without fetching anything outside the existing site.
    movies.slice(1).forEach(movie => { const image = new Image(); image.src = movie.backdrop; });
    schedule();
})();
