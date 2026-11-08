const accountCard = document.querySelector('.account-card');

function updateAccountTabs() {
    const registerVisible = location.hash === '#register' ||
        (location.hash !== '#sign-in' && accountCard.classList.contains('account-card--register'));

    accountCard.querySelectorAll('.account-tabs a').forEach(link => {
        if ((link.hash === '#register') === registerVisible) {
            link.setAttribute('aria-current', 'location');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

window.addEventListener('hashchange', updateAccountTabs);
updateAccountTabs();

accountCard.querySelectorAll('.account-password-toggle').forEach(button => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    button.hidden = false;

    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.toggleAttribute('data-visible', reveal);
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
});
