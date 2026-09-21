// assets/js/main.js

// Prevent "Confirm Form Resubmission" on browser Back/Forward navigation
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Disable navigating back into protected dashboard sessions from Login page
if (window.location.pathname.includes('/auth/login.php') || window.location.pathname.endsWith('/login.php')) {
    if (window.history && window.history.pushState) {
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function () {
            window.history.pushState(null, "", window.location.href);
        };
    }
}

// Safely revalidate and force server check if page is restored from in-memory bfcache snapshot or back-forward navigation
window.addEventListener('pageshow', function (event) {
    var navEntries = (window.performance && window.performance.getEntriesByType) ? window.performance.getEntriesByType('navigation') : null;
    var isBackForward = event.persisted || 
        (window.performance && window.performance.navigation && window.performance.navigation.type === 2) ||
        (navEntries && navEntries.length > 0 && navEntries[0].type === 'back_forward');
    
    if (isBackForward) {
        window.location.replace(window.location.href);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Reload captcha on click
    const captchaImgs = document.querySelectorAll('.captcha-img');
    if (captchaImgs) {
        captchaImgs.forEach(img => {
            img.addEventListener('click', function() {
                this.src = '/placement-management-system/includes/captcha.php?' + new Date().getTime();
            });
        });
    }

    // Auto-dismiss alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // Password visibility toggle
    const toggleBtns = document.querySelectorAll('.toggle-password');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // 1. Strict Name input restriction (Letters A-Z, a-z and spaces only)
    const alphaInputs = document.querySelectorAll('.alpha-only-input, input[name="name"]');
    alphaInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Strip any non-alphabetical characters (except space)
            const cleanVal = this.value.replace(/[^a-zA-Z\s]/g, '');
            if (this.value !== cleanVal) {
                this.value = cleanVal;
            }
        });
    });

    // 2. Strict Indian Phone Number input restriction (Numbers only, max 10 digits)
    const phoneInputs = document.querySelectorAll('.phone-input, input[name="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Allow only digits
            let cleanVal = this.value.replace(/[^0-9]/g, '');
            // Limit to 10 digits
            if (cleanVal.length > 10) {
                cleanVal = cleanVal.slice(0, 10);
            }
            this.value = cleanVal;
        });
    });

    // 3. Interactive Password Requirements Checklist
    const pwdInputs = document.querySelectorAll('#reg_password, input[name="password"]');
    pwdInputs.forEach(pwdInput => {
        const form = pwdInput.closest('form');
        if (!form) return;

        const ruleLength = form.querySelector('#rule-length');
        const ruleUpper = form.querySelector('#rule-upper');
        const ruleLower = form.querySelector('#rule-lower');
        const ruleNumber = form.querySelector('#rule-number');
        const ruleSpecial = form.querySelector('#rule-special');

        if (ruleLength && ruleUpper && ruleLower && ruleNumber && ruleSpecial) {
            const updateRule = (el, isValid) => {
                const icon = el.querySelector('i');
                if (isValid) {
                    el.classList.add('text-success', 'fw-semibold');
                    el.classList.remove('text-muted');
                    if (icon) {
                        icon.className = 'fa-solid fa-circle-check text-success me-1';
                    }
                } else {
                    el.classList.remove('text-success', 'fw-semibold');
                    el.classList.add('text-muted');
                    if (icon) {
                        icon.className = 'fa-regular fa-circle text-muted me-1';
                    }
                }
            };

            pwdInput.addEventListener('input', function() {
                const val = this.value;
                updateRule(ruleLength, val.length >= 8);
                updateRule(ruleUpper, /[A-Z]/.test(val));
                updateRule(ruleLower, /[a-z]/.test(val));
                updateRule(ruleNumber, /[0-9]/.test(val));
                updateRule(ruleSpecial, /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~`]/.test(val));
            });
        }
    });
});

