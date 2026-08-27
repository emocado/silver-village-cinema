/**
 * Silver Village Cinema - Client-Side Form & Interaction Validation
 * Fulfills IE4727 Project Requirement: Vanilla JS Form Validation
 */

document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------------------------
    // 1. Registration Form Validation
    // ----------------------------------------------------------------------
    const regForm = document.getElementById('registerForm');
    if (regForm) {
        const fullNameInput = document.getElementById('full_name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const dobInput = document.getElementById('date_of_birth');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const termsCheckbox = document.getElementById('terms');

        // Real-time password strength meter
        if (passwordInput) {
            passwordInput.addEventListener('input', function () {
                updatePasswordStrength(this.value);
                if (confirmPasswordInput && confirmPasswordInput.value) {
                    validateConfirmPassword();
                }
            });
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        }

        if (fullNameInput) fullNameInput.addEventListener('blur', validateFullName);
        if (emailInput) emailInput.addEventListener('blur', validateEmail);
        if (phoneInput) phoneInput.addEventListener('blur', validatePhone);
        if (dobInput) dobInput.addEventListener('blur', validateDob);

        regForm.addEventListener('submit', function (e) {
            let isValid = true;

            if (!validateFullName()) isValid = false;
            if (!validateEmail()) isValid = false;
            if (!validatePhone()) isValid = false;
            if (!validateDob()) isValid = false;
            if (!validatePassword()) isValid = false;
            if (!validateConfirmPassword()) isValid = false;

            if (termsCheckbox && !termsCheckbox.checked) {
                showError(termsCheckbox, 'You must accept the terms of service to register.');
                isValid = false;
            } else if (termsCheckbox) {
                clearError(termsCheckbox);
            }

            if (!isValid) {
                e.preventDefault();
                // Scroll to first invalid element
                const firstInvalid = regForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });

        function validateFullName() {
            const val = fullNameInput.value.trim();
            const nameRegex = /^[a-zA-Z\s'-]{2,50}$/;
            if (!val) {
                return showError(fullNameInput, 'Full legal name is required.');
            }
            if (!nameRegex.test(val)) {
                return showError(fullNameInput, 'Please enter a valid name (letters, spaces, hyphens only).');
            }
            return showSuccess(fullNameInput);
        }

        function validateEmail() {
            const val = emailInput.value.trim();
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!val) {
                return showError(emailInput, 'Email address is required.');
            }
            if (!emailRegex.test(val)) {
                return showError(emailInput, 'Please enter a valid email address (e.g. user@domain.com).');
            }
            return showSuccess(emailInput);
        }

        function validatePhone() {
            const val = phoneInput.value.trim().replace(/\s+/g, '');
            // SG Phone numbers start with 6, 8, or 9 and are 8 digits
            const phoneRegex = /^[689]\d{7}$/;
            if (!val) {
                return showError(phoneInput, 'Mobile phone number is required.');
            }
            if (!phoneRegex.test(val)) {
                return showError(phoneInput, 'Please enter a valid 8-digit Singapore phone number (starts with 6, 8, or 9).');
            }
            return showSuccess(phoneInput);
        }

        function validateDob() {
            const val = dobInput.value;
            if (!val) {
                return showError(dobInput, 'Date of birth is required.');
            }
            const dob = new Date(val);
            const today = new Date();
            if (dob >= today) {
                return showError(dobInput, 'Date of birth must be a past date.');
            }
            // Age calculation
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            if (age < 13) {
                return showError(dobInput, 'You must be at least 13 years old to create an account.');
            }
            return showSuccess(dobInput);
        }

        function validatePassword() {
            const val = passwordInput.value;
            if (!val) {
                return showError(passwordInput, 'Password is required.');
            }
            if (val.length < 8) {
                return showError(passwordInput, 'Password must be at least 8 characters long.');
            }
            if (!/[A-Z]/.test(val)) {
                return showError(passwordInput, 'Password must contain at least 1 uppercase letter.');
            }
            if (!/[0-9]/.test(val)) {
                return showError(passwordInput, 'Password must contain at least 1 number.');
            }
            return showSuccess(passwordInput);
        }

        function validateConfirmPassword() {
            const val = confirmPasswordInput.value;
            const pwdVal = passwordInput.value;
            if (!val) {
                return showError(confirmPasswordInput, 'Please confirm your password.');
            }
            if (val !== pwdVal) {
                return showError(confirmPasswordInput, 'Passwords do not match.');
            }
            return showSuccess(confirmPasswordInput);
        }

        function updatePasswordStrength(pwd) {
            const fill = document.getElementById('pwdStrengthFill');
            const text = document.getElementById('pwdStrengthText');
            if (!fill || !text) return;

            let score = 0;
            if (pwd.length >= 8) score++;
            if (/[A-Z]/.test(pwd)) score++;
            if (/[0-9]/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;

            if (pwd.length === 0) {
                fill.style.width = '0%';
                fill.style.background = '#31353f';
                text.textContent = 'Password strength';
                text.style.color = '#6b7280';
            } else if (score <= 1) {
                fill.style.width = '25%';
                fill.style.background = '#ef4444';
                text.textContent = 'Weak (Needs 8+ chars, 1 uppercase, 1 digit)';
                text.style.color = '#ef4444';
            } else if (score === 2) {
                fill.style.width = '50%';
                fill.style.background = '#f59e0b';
                text.textContent = 'Moderate';
                text.style.color = '#f59e0b';
            } else if (score === 3) {
                fill.style.width = '75%';
                fill.style.background = '#10b981';
                text.textContent = 'Strong';
                text.style.color = '#10b981';
            } else {
                fill.style.width = '100%';
                fill.style.background = '#f2ca50';
                text.textContent = 'Very Strong';
                text.style.color = '#f2ca50';
            }
        }
    }

    // ----------------------------------------------------------------------
    // 2. Feedback Form Validation
    // ----------------------------------------------------------------------
    const feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        const ratingInputs = feedbackForm.querySelectorAll('input[name="rating"]');
        const reviewText = document.getElementById('review_text');

        feedbackForm.addEventListener('submit', function (e) {
            let isValid = true;
            let ratingSelected = false;

            ratingInputs.forEach(input => {
                if (input.checked) ratingSelected = true;
            });

            const ratingError = document.getElementById('ratingError');
            if (!ratingSelected) {
                if (ratingError) ratingError.style.display = 'block';
                isValid = false;
            } else {
                if (ratingError) ratingError.style.display = 'none';
            }

            if (reviewText) {
                if (reviewText.value.trim().length < 10) {
                    showError(reviewText, 'Please provide a review of at least 10 characters.');
                    isValid = false;
                } else {
                    showSuccess(reviewText);
                }
            }

            if (!isValid) e.preventDefault();
        });
    }

    // ----------------------------------------------------------------------
    // 3. Contact Form Validation
    // ----------------------------------------------------------------------
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            let isValid = true;
            const name = document.getElementById('contact_name');
            const email = document.getElementById('contact_email');
            const message = document.getElementById('contact_message');

            if (name && !name.value.trim()) {
                showError(name, 'Your name is required.');
                isValid = false;
            } else if (name) showSuccess(name);

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                showError(email, 'A valid email address is required.');
                isValid = false;
            } else if (email) showSuccess(email);

            if (message && message.value.trim().length < 10) {
                showError(message, 'Please enter a message of at least 10 characters.');
                isValid = false;
            } else if (message) showSuccess(message);

            if (!isValid) e.preventDefault();
        });
    }

    // ----------------------------------------------------------------------
    // Utility Helper Functions
    // ----------------------------------------------------------------------
    function showError(inputElement, message) {
        inputElement.classList.add('is-invalid');
        inputElement.classList.remove('is-valid');

        const parent = inputElement.closest('.form-group') || inputElement.parentElement;
        let errorDiv = parent.querySelector('.form-error-msg');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'form-error-msg';
            parent.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        return false;
    }

    function clearError(inputElement) {
        inputElement.classList.remove('is-invalid');
        const parent = inputElement.closest('.form-group') || inputElement.parentElement;
        const errorDiv = parent.querySelector('.form-error-msg');
        if (errorDiv) errorDiv.style.display = 'none';
    }

    function showSuccess(inputElement) {
        inputElement.classList.remove('is-invalid');
        inputElement.classList.add('is-valid');
        const parent = inputElement.closest('.form-group') || inputElement.parentElement;
        const errorDiv = parent.querySelector('.form-error-msg');
        if (errorDiv) errorDiv.style.display = 'none';
        return true;
    }
});
