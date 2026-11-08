document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('paymentForm');
    if (!form) return;
    const number = form.elements.card_number;
    const expiry = form.elements.expiry;

    number.addEventListener('input', function () {
        if (!/^[0-9 ]*$/.test(this.value)) return;

        const digitsBeforeCursor = this.value.slice(0, this.selectionStart).replace(/ /g, '').length;
        const formatted = this.value.replace(/ /g, '').replace(/([0-9]{4})(?=[0-9])/g, '$1 ');
        if (formatted !== this.value) {
            this.value = formatted;
            const spacesBeforeCursor = Math.floor(Math.max(0, digitsBeforeCursor - 1) / 4);
            const cursor = digitsBeforeCursor + spacesBeforeCursor;
            this.setSelectionRange(cursor, cursor);
        }
    });
    expiry.addEventListener('input', function () {
        const digits = this.value.replace(/\D/g, '').slice(0, 4);
        this.value = digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
    });
    function cardError() {
        const digits = number.value.replace(/ /g, '');
        if (number.validity.patternMismatch || !/^[0-9]{16}$/.test(digits)) {
            return 'Enter exactly 16 digits.';
        }
        let sum = 0;
        [...digits].reverse().forEach((digit, index) => {
            let value = Number(digit);
            if (index % 2) { value *= 2; if (value > 9) value -= 9; }
            sum += value;
        });
        return sum === 0 || sum % 10 !== 0 ? 'Check your card number and try again.' : '';
    }

    function expiryError() {
        const parts = /^(0[1-9]|1[0-2])\/([0-9]{2})$/.exec(expiry.value);
        if (!parts) return 'Enter the expiry date as MM/YY.';
        const endOfMonth = new Date(2000 + Number(parts[2]), Number(parts[1]), 1);
        return endOfMonth <= new Date() ? 'This card has expired. Use another card.' : '';
    }

    const fields = [
        [form.elements.cardholder, value =>
            value.trim().length < 2 || value.trim().length > 100 ? 'Enter the name shown on your card.' : ''],
        [number, cardError],
        [expiry, expiryError],
        [form.elements.security_code, value =>
            /^[0-9]{3,4}$/.test(value) ? '' : 'Enter the 3 or 4 digit security code.'],
    ];

    function validate(field, getError) {
        const message = getError(field.value);
        const error = document.getElementById(field.id + '-error');
        field.classList.toggle('is-invalid', Boolean(message));
        field.setAttribute('aria-invalid', message ? 'true' : 'false');
        error.textContent = message;
        error.style.display = message ? 'block' : 'none';
        return !message;
    }

    fields.forEach(([field, getError]) => {
        field.addEventListener('blur', () => validate(field, getError));
        field.addEventListener('input', () => {
            if (field.getAttribute('aria-invalid') === 'true') validate(field, getError);
        });
    });

    form.noValidate = true;
    form.addEventListener('submit', function (event) {
        let firstInvalid;
        fields.forEach(([field, getError]) => {
            if (!validate(field, getError) && !firstInvalid) firstInvalid = field;
        });
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
            return;
        }
        const button = document.getElementById('payButton');
        button.disabled = true; button.textContent = 'Processing payment…';
    });
});
