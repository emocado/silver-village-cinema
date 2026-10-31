(() => {
    const hero = document.querySelector('.movie-rotation');
    const data = document.getElementById('featuredMovieData');
    if (!hero || !data) return;
    const movies = JSON.parse(data.textContent);
    if (movies.length < 2) return;
    const controls = hero.querySelector('.movie-rotation-controls');
    const pause = hero.querySelector('[data-feature-pause]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let paused = reducedMotion.matches;
    let hovering = false;
    let timer;

    function schedule() {
        clearTimeout(timer);
        if (!paused && !hovering && !document.hidden && !hero.contains(document.activeElement)) {
            timer = setTimeout(() => show(current + 1), 7000);
        }
    }
    function show(index, announce = false) {
        current = (index + movies.length) % movies.length;
        const movie = movies[current];
        hero.querySelector('.hero-title').textContent = movie.title;
        const metadata = hero.querySelector('.hero-meta').children;
        metadata[0].textContent = movie.rating;
        metadata[1].textContent = movie.duration_minutes + ' Mins';
        metadata[2].textContent = movie.genre;
        hero.querySelector('[data-feature-booking]').href = 'movie_details.php?id=' + Number(movie.movie_id);
        hero.querySelector('.hero-backdrop').style.backgroundImage = `url(${JSON.stringify(movie.backdrop)})`;
        hero.querySelector('.hero-backdrop').classList.toggle('is-poster', !movie.backdrop.includes('hero_spiderman'));
        document.querySelector('.featured-synopsis').textContent = movie.synopsis;
        hero.querySelector('[data-feature-position]').textContent = `${current + 1} / ${movies.length}`;
        if (announce) document.getElementById('featuredMovieAnnouncement').textContent = movie.title;
        schedule();
    }
    controls.hidden = false;
    pause.textContent = paused ? 'Resume rotation' : 'Pause rotation';
    hero.querySelector('[data-feature-prev]').addEventListener('click', () => show(current - 1, true));
    hero.querySelector('[data-feature-next]').addEventListener('click', () => show(current + 1, true));
    pause.addEventListener('click', () => {
        paused = !paused;
        pause.textContent = paused ? 'Resume rotation' : 'Pause rotation';
        schedule();
    });
    hero.addEventListener('mouseenter', () => { hovering = true; schedule(); });
    hero.addEventListener('mouseleave', () => { hovering = false; schedule(); });
    hero.addEventListener('focusin', schedule);
    hero.addEventListener('focusout', () => setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    reducedMotion.addEventListener('change', () => {
        paused = reducedMotion.matches;
        pause.textContent = paused ? 'Resume rotation' : 'Pause rotation';
        schedule();
    });
    // Warm the next artwork without fetching anything outside the existing site.
    movies.slice(1).forEach(movie => { const image = new Image(); image.src = movie.backdrop; });
    schedule();
})();
