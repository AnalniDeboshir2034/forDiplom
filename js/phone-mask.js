document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('iti-layout-fix')) {
        var layoutStyle = document.createElement('style');
        layoutStyle.id = 'iti-layout-fix';
        layoutStyle.textContent = '' +
            '.iti{width:100%;display:block;}' +
            '.iti input{width:100%!important;}' +
            '.calc-form-row .iti{flex:1;max-width:250px;}' +
            '.calc-form-row .iti input{max-width:100%!important;}' +
            '.contact-form .iti,.contact-form-fields .iti,.header-lead-form .iti,.cart-checkout-form .iti{width:100%;max-width:100%;}' +
            '.cart-checkout-form .iti input{width:100%!important;}' +
            '@media (max-width:992px){.calc-form-row .iti{max-width:100%;width:100%;}}';
        document.head.appendChild(layoutStyle);
    }

    var phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"]');
    if (!phoneInputs.length) return;

    var itiMap = new Map();

    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function extractBelarusNationalDigits(value) {
        var digits = digitsOnly(value);
        if (!digits) return '';

        while (digits.indexOf('375') === 0 && digits.length > 9) {
            digits = digits.slice(3);
        }
        while (digits.indexOf('80') === 0 && digits.length > 9) {
            digits = digits.slice(2);
        }
        if (digits.length > 9) {
            digits = digits.slice(-9);
        }

        return digits.length === 9 ? digits : '';
    }

    function buildBelarusPhone(nationalDigits) {
        return nationalDigits.length === 9 ? ('+375' + nationalDigits) : '';
    }

    function getFullInternationalNumber(input) {
        var national = extractBelarusNationalDigits(input.value);
        var built = buildBelarusPhone(national);
        if (built) return built;

        var iti = itiMap.get(input);
        if (iti && typeof iti.getNumber === 'function') {
            var formatted = String(iti.getNumber() || '').replace(/\s/g, '');
            national = extractBelarusNationalDigits(formatted);
            built = buildBelarusPhone(national);
            if (built) return built;
        }

        return '';
    }

    function isValidBelarusPhone(value) {
        return /^\+375\d{9}$/.test(String(value || ''));
    }

    function isValidPhone(input) {
        var full = getFullInternationalNumber(input);
        return isValidBelarusPhone(full);
    }

    function ensureBelarusCountry(input) {
        var iti = itiMap.get(input);
        if (iti && typeof iti.setCountry === 'function') {
            iti.setCountry('by');
        }
    }

    function setInputToInternationalValue(input) {
        var num = getFullInternationalNumber(input);
        if (!num) return;
        var national = num.slice(4);
        var iti = itiMap.get(input);
        if (iti && typeof iti.setNumber === 'function') {
            iti.setNumber(num, 'by');
            return;
        }
        input.value = national;
    }

    function setInputFullPhoneForSubmit(input) {
        var num = getFullInternationalNumber(input);
        if (num) input.value = num;
    }

    function setPhoneValue(input, value) {
        var iti = itiMap.get(input);
        ensureBelarusCountry(input);
        if (!value) {
            input.value = '';
            return;
        }
        if (iti && typeof iti.setNumber === 'function') {
            iti.setNumber(String(value), 'by');
            return;
        }
        input.value = String(value);
    }

    phoneInputs.forEach(function (input) {
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('inputmode', 'tel');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('autocapitalize', 'off');

        if (typeof window.intlTelInput === 'function') {
            var iti = window.intlTelInput(input, {
                initialCountry: 'by',
                onlyCountries: ['by'],
                separateDialCode: true,
                nationalMode: false,
                autoPlaceholder: 'polite',
                countrySearch: false,
                formatOnDisplay: true,
                allowDropdown: false
            });
            itiMap.set(input, iti);
            ensureBelarusCountry(input);
        }

        input.addEventListener('input', function () {
            input.value = input.value.replace(/[^0-9+()\-\s]/g, '');
            var digits = digitsOnly(input.value);
            if (input.value.indexOf('+') === 0 || digits.indexOf('375') === 0 || (digits.indexOf('80') === 0 && digits.length >= 11)) {
                var national = extractBelarusNationalDigits(input.value);
                if (national) input.value = national;
            }
            input.setCustomValidity('');
            ensureBelarusCountry(input);
        });

        input.addEventListener('blur', function () {
            if (!String(input.value || '').trim()) {
                input.setCustomValidity('');
                return;
            }
            if (!isValidPhone(input)) {
                input.setCustomValidity('Введите корректный белорусский номер (+375 XX XXX XX XX)');
            } else {
                setInputToInternationalValue(input);
                input.setCustomValidity('');
            }
        });
    });

    document.addEventListener(
        'submit',
        function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            var tels = form.querySelectorAll('input[type="tel"], input[name="phone"]');
            if (!tels.length) return;

            for (var i = 0; i < tels.length; i++) {
                var tel = tels[i];
                if (!isValidPhone(tel)) {
                    tel.setCustomValidity('Введите корректный белорусский номер (+375 XX XXX XX XX)');
                    tel.reportValidity();
                    event.preventDefault();
                    return;
                }
                setInputFullPhoneForSubmit(tel);
                tel.setCustomValidity('');
            }
        },
        true
    );

    window.AppPhoneMask = {
        ensureBelarus: ensureBelarusCountry,
        normalize: getFullInternationalNumber,
        isValid: isValidPhone,
        setValue: setPhoneValue
    };
});
