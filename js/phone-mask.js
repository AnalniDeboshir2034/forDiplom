document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('iti-layout-fix')) {
        var layoutStyle = document.createElement('style');
        layoutStyle.id = 'iti-layout-fix';
        layoutStyle.textContent = '' +
            '.iti{width:100%;display:block;}' +
            '.iti input{width:100%!important;}' +
            '.calc-form-row .iti{flex:1;max-width:250px;}' +
            '.calc-form-row .iti input{max-width:100%!important;}' +
            '.contact-form .iti,.contact-form-fields .iti,.header-lead-form .iti{width:100%;max-width:100%;}' +
            '@media (max-width:992px){.calc-form-row .iti{max-width:100%;width:100%;}}';
        document.head.appendChild(layoutStyle);
    }

    var phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"]');
    if (!phoneInputs.length) return;

    var itiMap = new Map();
    var countryData = (window.intlTelInputGlobals && typeof window.intlTelInputGlobals.getCountryData === 'function')
        ? window.intlTelInputGlobals.getCountryData()
        : [];

    function normalizeBasicPhone(value) {
        return String(value || '').trim().replace(/[\s()-]/g, '');
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function getDetectedIso2ByDialPrefix(rawValue) {
        if (!rawValue) return '';
        var raw = String(rawValue).trim();
        var digits = '';
        var isInternationalInput = false;

        if (raw.indexOf('+') === 0) {
            digits = raw.slice(1).replace(/\D/g, '');
            isInternationalInput = true;
        } else if (raw.indexOf('00') === 0) {
            digits = raw.slice(2).replace(/\D/g, '');
            isInternationalInput = true;
        } else {
            // Local entry (e.g. 29...) should not reset user-selected country.
            return '';
        }
        if (!digits) return '';
        if (!isInternationalInput || digits.length < 2) return '';

        var best = '';
        var bestLen = 0;
        for (var i = 0; i < countryData.length; i++) {
            var item = countryData[i];
            var code = String(item && item.dialCode ? item.dialCode : '');
            if (!code) continue;
            if (digits.indexOf(code) === 0 && code.length > bestLen) {
                best = String(item.iso2 || '');
                bestLen = code.length;
            }
        }
        return best;
    }

    function getFullInternationalNumber(input) {
        var iti = itiMap.get(input);
        var raw = String(input.value || '').trim();
        var normalized = normalizeBasicPhone(raw);
        if (!normalized && !raw) return '';

        // If user already typed +countryCode..., keep as is.
        if (raw.indexOf('+') === 0 || normalized.indexOf('+') === 0) {
            var intlDigits = digitsOnly(raw);
            return intlDigits ? ('+' + intlDigits) : '';
        }

        var digits = digitsOnly(normalized);
        if (!digits) return '';

        // Belarus local trunk format: 80XXXXXXXXX -> +375XXXXXXXXX
        // Example: 80291234567 -> +375291234567
        if (digits.indexOf('80') === 0 && digits.length === 11) {
            return '+375' + digits.slice(2);
        }

        if (iti && typeof iti.getSelectedCountryData === 'function') {
            var country = iti.getSelectedCountryData();
            var dial = String(country && country.dialCode ? country.dialCode : '');
            if (dial) {
                // If user starts with country code digits, just prepend plus.
                if (digits.indexOf(dial) === 0) {
                    return '+' + digits;
                }
                return '+' + dial + digits;
            }
        }

        return '+' + digits;
    }

    function isValidPhoneBasic(value) {
        return /^\+\d{7,15}$/.test(String(value || ''));
    }

    function isValidPhone(input) {
        var full = getFullInternationalNumber(input);
        return isValidPhoneBasic(full);
    }

    function setInputToInternationalValue(input) {
        var num = getFullInternationalNumber(input);
        if (num) input.value = num;
    }

    phoneInputs.forEach(function (input) {
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('inputmode', 'tel');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('autocapitalize', 'off');

        if (typeof window.intlTelInput === 'function') {
            var iti = window.intlTelInput(input, {
                initialCountry: 'auto',
                separateDialCode: false,
                nationalMode: false,
                autoPlaceholder: 'polite',
                countrySearch: false,
                formatOnDisplay: true,
                geoIpLookup: function (callback) {
                    fetch('https://ipapi.co/json/')
                        .then(function (res) { return res.json(); })
                        .then(function (data) { callback((data && data.country_code ? data.country_code : 'ru')); })
                        .catch(function () { callback('ru'); });
                }
            });
            itiMap.set(input, iti);
        }

        input.addEventListener('input', function () {
            input.value = input.value.replace(/[^0-9+()\-\s]/g, '');
            input.setCustomValidity('');

            // Auto-detect country from typed prefix (+375..., 375..., +7..., etc).
            var iti = itiMap.get(input);
            if (iti && typeof iti.setCountry === 'function') {
                var iso2 = getDetectedIso2ByDialPrefix(input.value);
                if (iso2) {
                    iti.setCountry(iso2);
                }
            }
        });

        input.addEventListener('blur', function () {
            if (!String(input.value || '').trim()) {
                input.setCustomValidity('');
                return;
            }
            if (!isValidPhone(input)) {
                input.setCustomValidity('Номер телефона неправильный');
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
                    tel.setCustomValidity('Номер телефона неправильный');
                    tel.reportValidity();
                    event.preventDefault();
                    return;
                }
                setInputToInternationalValue(tel);
                tel.setCustomValidity('');
            }
        },
        true
    );
});
