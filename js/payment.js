document.addEventListener('DOMContentLoaded', function () {
    const number = document.getElementById('card_number');
    const expiry = document.getElementById('expiry');
    const form = document.getElementById('paymentForm');
    if (!form) return;
    number.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 19).replace(/(.{4})/g, '$1 ').trim();
        this.setCustomValidity('');
    });
    expiry.addEventListener('input', function () {
        const digits = this.value.replace(/\D/g, '').slice(0, 4);
        this.value = digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
    });
    form.addEventListener('submit', function (event) {
        const digits = number.value.replace(/\D/g, '');
        let sum = 0;
        [...digits].reverse().forEach((digit, index) => {
            let value = Number(digit);
            if (index % 2) { value *= 2; if (value > 9) value -= 9; }
            sum += value;
        });
        if (digits.length < 13 || sum === 0 || sum % 10 !== 0) {
            event.preventDefault(); number.setCustomValidity('Check your card number and try again.'); number.reportValidity(); return;
        }
        const button = document.getElementById('payButton');
        button.disabled = true; button.textContent = 'Processing payment…';
    });
});
