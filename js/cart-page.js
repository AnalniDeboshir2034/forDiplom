document.addEventListener('DOMContentLoaded', function () {
    if (!window.CartStorage) return;

    var products = Array.isArray(window.ALL_PRODUCTS_FOR_CART) ? window.ALL_PRODUCTS_FOR_CART : [];

    var emptyBlock = document.getElementById('cart-empty');
    var listBlock = document.getElementById('cart-list');
    var sidebar = document.getElementById('cart-sidebar');
    var clearBtn = document.getElementById('cart-clear');
    var printBtn = document.getElementById('cart-print');
    var checkoutOpenBtn = document.getElementById('cart-checkout-open');
    var checkoutModal = document.getElementById('cartCheckoutModal');
    var checkoutCloseBtn = document.getElementById('cart-checkout-close');
    var checkoutForm = document.getElementById('cart-checkout-form');
    var checkoutStatus = document.getElementById('cart-checkout-status');
    var subtotalEl = document.getElementById('cartSubtotal');
    var discountEl = document.getElementById('cartDiscount');
    var totalEl = document.getElementById('cartTotal');
    var checkoutTotalValueEl = document.getElementById('cartCheckoutTotalValue');
    var checkoutTotalMetaEl = document.getElementById('cartCheckoutTotalMeta');

    function getCartRows() {
        var cart = window.CartStorage.readItems();
        return cart
            .map(function (item) {
                var product = products.find(function (p) { return String(p.id) === String(item.id); });
                if (!product) return null;
                return { product: product, qty: item.qty };
            })
            .filter(Boolean);
    }

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoneyByn(value) {
        return Number(value || 0).toFixed(2) + ' BYN';
    }

    function updateCheckoutTotalDisplay(subtotal, discount, total) {
        if (checkoutTotalValueEl) {
            checkoutTotalValueEl.textContent = formatMoneyByn(total);
        }
        if (checkoutTotalMetaEl) {
            var discountNum = Number(discount || 0);
            if (discountNum > 0) {
                checkoutTotalMetaEl.textContent = 'Сумма: ' + formatMoneyByn(subtotal) + ' · Скидка: ' + formatMoneyByn(discount);
            } else {
                checkoutTotalMetaEl.textContent = '';
            }
        }
    }

    function render() {
        var rows = getCartRows();
        if (!rows.length) {
            emptyBlock.style.display = 'block';
            listBlock.style.display = 'none';
            sidebar.style.display = 'none';
            listBlock.innerHTML = '';
            if (subtotalEl) subtotalEl.textContent = formatMoneyByn(0);
            if (discountEl) discountEl.textContent = formatMoneyByn(0);
            if (totalEl) totalEl.textContent = formatMoneyByn(0);
            updateCheckoutTotalDisplay(0, 0, 0);
            return;
        }

        emptyBlock.style.display = 'none';
        listBlock.style.display = 'block';
        sidebar.style.display = 'flex';

        var html = '';
        rows.forEach(function (row) {
            var p = row.product;
            var base = window.APP_BASE || '';
            var productHref = base + '/product.php?slug=' + encodeURIComponent(p.slug || '');
            html += '<div class="cart-item">';
            html += '<div class="cart-item__img"><a href="' + esc(productHref) + '"><img src="' + esc(p.image || 'products/medikator.jpg') + '" alt="' + esc(p.name) + '"></a></div>';
            html += '<div class="cart-item__body">';
            html += '<div class="cart-item__name"><a href="' + esc(productHref) + '">' + esc(p.name) + '</a></div>';
            html += '<div class="cart-item__meta">Серия: ' + esc(p.series || '-') + '</div>';
            html += '<div class="cart-item__qty">';
            html += '<button type="button" data-cart-minus="' + esc(p.id) + '">-</button>';
            html += '<span>' + esc(row.qty) + '</span>';
            html += '<button type="button" data-cart-plus="' + esc(p.id) + '">+</button>';
            html += '</div></div>';
            html += '<button type="button" class="cart-item__remove" data-cart-remove="' + esc(p.id) + '">Удалить</button>';
            html += '</div>';
        });
        listBlock.innerHTML = html;
        recalcTotals();
    }

    function getItemsPayload() {
        return window.CartStorage.readItems().map(function (it) {
            return { id: parseInt(it.id, 10) || 0, qty: parseInt(it.qty, 10) || 1 };
        }).filter(function (it) { return it.id >= 0 && it.qty > 0; });
    }

    function recalcTotals(promoCode) {
        if (!subtotalEl || !discountEl || !totalEl) return;
        var items = getItemsPayload();
        var fd = new FormData();
        fd.set('items', JSON.stringify(items));
        if (promoCode) fd.set('promo_code', promoCode);
        fetch('includes/api_promo_calc.php', { method: 'POST', body: fd })
            .then(parseApiResponse)
            .then(function (data) {
                if (!data || !data.success) return;
                subtotalEl.textContent = formatMoneyByn(data.subtotal);
                discountEl.textContent = formatMoneyByn(data.discount_total);
                totalEl.textContent = formatMoneyByn(data.total);
                updateCheckoutTotalDisplay(data.subtotal, data.discount_total, data.total);
            })
            .catch(function () {});
    }

    function orderText() {
        var rows = getCartRows();
        if (!rows.length) return 'Корзина пуста';
        return rows.map(function (row) {
            return '- ' + row.product.name + ' (x' + row.qty + ')';
        }).join('\n');
    }

    function setCheckoutStatus(message, isError) {
        checkoutStatus.textContent = message;
        checkoutStatus.classList.toggle('is-error', !!isError);
        checkoutStatus.classList.toggle('is-success', !isError);
    }

    function parseApiResponse(response) {
        return response.text().then(function (text) {
            var data = null;
            try { data = JSON.parse(text); } catch (e) {}
            if (!data || typeof data !== 'object') {
                data = {
                    success: false,
                    message: text ? text.replace(/<[^>]*>/g, '').trim().slice(0, 250) : 'Сервер вернул некорректный ответ'
                };
            }
            data.__ok = response.ok;
            return data;
        });
    }

    function openCheckout() {
        if (!window.APP_IS_AUTH) {
            var loginUrl = window.APP_LOGIN_URL || '/login.php';
            var sep = loginUrl.indexOf('?') === -1 ? '?' : '&';
            window.location.href = loginUrl + sep + 'redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            return;
        }
        checkoutModal.classList.add('is-active');
        checkoutModal.setAttribute('aria-hidden', 'false');

        var promoInput = checkoutForm ? checkoutForm.querySelector('input[name="promo_code"]') : null;
        recalcTotals(promoInput ? String(promoInput.value || '').trim() : '');

        var phoneInput = checkoutForm ? checkoutForm.querySelector('input[name="phone"]') : null;
        if (phoneInput && window.AppPhoneMask && typeof window.AppPhoneMask.ensureBelarus === 'function') {
            window.AppPhoneMask.ensureBelarus(phoneInput);
        }
    }

    function closeCheckout() {
        checkoutModal.classList.remove('is-active');
        checkoutModal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (event) {
        var removeBtn = event.target.closest('[data-cart-remove]');
        if (removeBtn) {
            window.CartStorage.removeItem(removeBtn.getAttribute('data-cart-remove'));
            window.CartStorage.syncButtons();
            window.CartStorage.updateCounter();
            render();
            return;
        }

        var plusBtn = event.target.closest('[data-cart-plus]');
        if (plusBtn) {
            window.CartStorage.addItem(plusBtn.getAttribute('data-cart-plus'), 1);
            window.CartStorage.syncButtons();
            window.CartStorage.updateCounter();
            render();
            return;
        }

        var minusBtn = event.target.closest('[data-cart-minus]');
        if (minusBtn) {
            var id = minusBtn.getAttribute('data-cart-minus');
            var nextQty = Math.max(0, window.CartStorage.getQtyById(id) - 1);
            window.CartStorage.setItemQty(id, nextQty);
            window.CartStorage.syncButtons();
            window.CartStorage.updateCounter();
            render();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            window.CartStorage.clearCart();
            window.CartStorage.syncButtons();
            window.CartStorage.updateCounter();
            render();
        });
    }

    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    if (checkoutOpenBtn) {
        checkoutOpenBtn.addEventListener('click', openCheckout);
    }
    if (checkoutCloseBtn) {
        checkoutCloseBtn.addEventListener('click', closeCheckout);
    }

    if (checkoutModal) {
        checkoutModal.addEventListener('click', function (event) {
            if (event.target === checkoutModal) closeCheckout();
        });
    }

    if (checkoutForm) {
        var deliveryTypeSelect = checkoutForm.querySelector('select[name="delivery_type"]');
        var deliveryAddressInput = checkoutForm.querySelector('[data-delivery-address]');
        var pickupPointInput = checkoutForm.querySelector('[data-pickup-point]');
        var profile = window.APP_PROFILE || {};
        var nameInput = checkoutForm.querySelector('input[name="name"]');
        var phoneInput = checkoutForm.querySelector('input[name="phone"]');
        var emailInput = checkoutForm.querySelector('input[name="email"]');
        if (nameInput && profile.name) nameInput.value = profile.name;
        if (phoneInput && profile.phone) {
            if (window.AppPhoneMask && typeof window.AppPhoneMask.setValue === 'function') {
                window.AppPhoneMask.setValue(phoneInput, profile.phone);
            } else {
                phoneInput.value = profile.phone;
            }
        }
        if (emailInput && profile.email) emailInput.value = profile.email;

        function syncDeliveryFields() {
            if (!deliveryTypeSelect || !deliveryAddressInput || !pickupPointInput) return;
            var value = String(deliveryTypeSelect.value || '');
            var isCourier = value === 'courier';
            var isPickup = value === 'pickup';
            deliveryAddressInput.style.display = isCourier ? '' : 'none';
            pickupPointInput.style.display = isPickup ? '' : 'none';
            deliveryAddressInput.required = isCourier;
            pickupPointInput.required = isPickup;
            if (isCourier && profile.address && !deliveryAddressInput.value) {
                deliveryAddressInput.value = profile.address;
            }
        }
        if (deliveryTypeSelect) {
            deliveryTypeSelect.addEventListener('change', syncDeliveryFields);
            syncDeliveryFields();
        }

        var promoInput = checkoutForm.querySelector('input[name="promo_code"]');
        if (promoInput) {
            var t = null;
            promoInput.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    recalcTotals(String(promoInput.value || '').trim());
                }, 350);
            });
        }

        checkoutForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var rows = getCartRows();
            if (!rows.length) {
                setCheckoutStatus('Корзина пуста', true);
                return;
            }

            var submitBtn = checkoutForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            setCheckoutStatus('Отправляем заказ...', false);

            var fd = new FormData(checkoutForm);
            fd.set('items', JSON.stringify(getItemsPayload()));
            if (phoneInput && window.AppPhoneMask && typeof window.AppPhoneMask.normalize === 'function') {
                fd.set('phone', window.AppPhoneMask.normalize(phoneInput));
            }

            fetch('includes/api_checkout.php', {
                method: 'POST',
                body: fd
            })
            .then(parseApiResponse)
            .then(function (data) {
                if (data && data.success) {
                    setCheckoutStatus(data.message || 'Заказ отправлен', false);
                    checkoutForm.reset();
                    window.CartStorage.clearCart();
                    window.CartStorage.syncButtons();
                    window.CartStorage.updateCounter();
                    render();
                    setTimeout(closeCheckout, 1000);
                } else {
                    setCheckoutStatus((data && data.message) || (data && data.__ok === false ? 'Ошибка сервера' : 'Ошибка отправки'), true);
                }
            })
            .catch(function () {
                setCheckoutStatus('Ошибка сети. Попробуйте еще раз.', true);
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
        });
    }

    render();
});
