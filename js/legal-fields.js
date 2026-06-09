document.addEventListener('DOMContentLoaded', function () {
    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function isLegalForm(form) {
        var typeSelect = form.querySelector('[name="account_type"]');
        return typeSelect && typeSelect.value === 'legal';
    }

    function bindUnpInput(input) {
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('maxlength', '9');
        input.setAttribute('pattern', '\\d{9}');
        input.setAttribute('autocomplete', 'off');
        if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', 'УНП (9 цифр)');
        }

        input.addEventListener('input', function () {
            var digits = digitsOnly(input.value).slice(0, 9);
            if (input.value !== digits) {
                input.value = digits;
            }
            input.setCustomValidity('');
        });

        input.addEventListener('blur', function () {
            if (!input.required && !String(input.value || '').trim()) {
                input.setCustomValidity('');
                return;
            }
            if (digitsOnly(input.value).length !== 9) {
                input.setCustomValidity('УНП должен содержать ровно 9 цифр');
            } else {
                input.setCustomValidity('');
            }
        });
    }

    document.querySelectorAll('input[name="unp"]').forEach(bindUnpInput);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !isLegalForm(form)) {
            return;
        }

        var unpInput = form.querySelector('input[name="unp"]');
        if (unpInput) {
            unpInput.value = digitsOnly(unpInput.value).slice(0, 9);
            if (digitsOnly(unpInput.value).length !== 9) {
                unpInput.setCustomValidity('УНП должен содержать ровно 9 цифр');
                unpInput.reportValidity();
                event.preventDefault();
                return;
            }
            unpInput.setCustomValidity('');
        }

        var phoneInput = form.querySelector('input[type="tel"], input[name="phone"]');
        if (phoneInput) {
            phoneInput.dispatchEvent(new Event('blur', { bubbles: true }));
            if (!phoneInput.checkValidity()) {
                phoneInput.reportValidity();
                event.preventDefault();
            }
        }
    }, true);
});
