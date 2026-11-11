document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('paymentForm');
    if (!form) return;
    const cardholder = form.elements.cardholder;
    const number = form.elements.card_number;
    const expiry = form.elements.expiry;
    const securityCode = form.elements.security_code;

    cardholder.addEventListener('input', function () {
        const name = this.value.replace(/\p{N}/gu, '');
        if (name === this.value) return;

        const cursor = this.value.slice(0, this.selectionStart).replace(/\p{N}/gu, '').length;
        this.value = name;
        this.setSelectionRange(cursor, cursor);
    });
    number.addEventListener('input', function () {
        const digits = this.value.replace(/[^0-9]/g, '').slice(0, 16);
        const digitsBeforeCursor = Math.min(digits.length, this.value.slice(0, this.selectionStart).replace(/[^0-9]/g, '').length);
        const formatted = digits.replace(/([0-9]{4})(?=[0-9])/g, '$1 ');
        if (formatted !== this.value) {
            this.value = formatted;
            const spacesBeforeCursor = Math.floor(Math.max(0, digitsBeforeCursor - 1) / 4);
            const cursor = digitsBeforeCursor + spacesBeforeCursor;
            this.setSelectionRange(cursor, cursor);
        }
    });
    securityCode.addEventListener('input', function () {
        const digits = this.value.replace(/[^0-9]/g, '').slice(0, 3);
        if (this.value !== digits) this.value = digits;
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
        return '';
    }

    function expiryError() {
        const parts = /^(0[1-9]|1[0-2])\/([0-9]{2})$/.exec(expiry.value);
        if (!parts) return 'Enter the expiry date as MM/YY.';
        const endOfMonth = new Date(2000 + Number(parts[2]), Number(parts[1]), 1);
        return endOfMonth <= new Date() ? 'This card has expired. Use another card.' : '';
    }

    const fields = [
        [cardholder, value => {
            if (/\p{N}/u.test(value)) return 'Enter a name without numbers.';
            return value.trim().length < 2 || value.trim().length > 100 ? 'Enter the name shown on your card.' : '';
        }],
        [number, cardError],
        [expiry, expiryError],
        [securityCode, value =>
            /^[0-9]{3}$/.test(value) ? '' : 'Enter exactly 3 digits for the security code.'],
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
