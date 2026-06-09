document.addEventListener('DOMContentLoaded', function () {
    var STORAGE_KEY = 'medicator_cart_items';

    function readItems() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : [];

            if (!Array.isArray(parsed)) return [];

            return parsed
                .filter(function (item) {
                    return item && item.id !== undefined && item.id !== null && String(item.id) !== '';
                })
                .map(function (item) {
                    return {
                        id: String(item.id),
                        qty: Math.max(1, parseInt(item.qty, 10) || 1)
                    };
                });

        } catch (e) {
            return [];
        }
    }

    function saveItems(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }

    function getQtyById(id) {
        var match = readItems().find(function (item) {
            return String(item.id) === String(id);
        });

        return match ? match.qty : 0;
    }

    function addItem(id, qty) {
        var items = readItems();

        var index = items.findIndex(function (item) {
            return String(item.id) === String(id);
        });

        if (index === -1) {
            items.push({
                id: String(id),
                qty: Math.max(1, qty || 1)
            });
        } else {
            items[index].qty += Math.max(1, qty || 1);
        }

        saveItems(items);
    }

    function setItemQty(id, qty) {
        var items = readItems();

        var index = items.findIndex(function (item) {
            return String(item.id) === String(id);
        });

        if (index === -1) return;

        if (qty <= 0) {
            items.splice(index, 1);
        } else {
            items[index].qty = qty;
        }

        saveItems(items);
    }

    function removeItem(id) {
        var items = readItems().filter(function (item) {
            return String(item.id) !== String(id);
        });

        saveItems(items);
    }

    function clearCart() {
        saveItems([]);
    }

    function getTotalCount() {
        return readItems().reduce(function (acc, item) {
            return acc + item.qty;
        }, 0);
    }

    function updateCounter() {
        var total = getTotalCount();

        var counters = document.querySelectorAll('[data-cart-count]');

        counters.forEach(function (counter) {
            counter.textContent = String(total);
        });
    }

    function syncButtons() {
        var buttons = document.querySelectorAll('[data-cart-add]');

        buttons.forEach(function (button) {
            var id = button.getAttribute('data-cart-id');
            var qty = getQtyById(id);

            if (qty > 0) {
                button.classList.add('is-in-cart');
                button.textContent = 'В корзине (' + qty + ')';
            } else {
                button.classList.remove('is-in-cart');
                button.textContent = 'В корзину';
            }
        });
    }

    function showAuthModal() {
        var existing = document.querySelector('.auth-required-modal');

        if (existing) {
            existing.remove();
        }

        var modal = document.createElement('div');

        modal.className = 'auth-required-modal';

        modal.innerHTML = `
            <div class="auth-required-modal__backdrop"></div>

            <div class="auth-required-modal__content">
                <button class="auth-required-modal__close">
                    ×
                </button>

                <h3>Требуется авторизация</h3>

                <p>
                    Для добавления товара в корзину
                    войдите в аккаунт или зарегистрируйтесь
                </p>

                <div class="auth-required-modal__actions">
                    <button class="auth-login-btn">
                        Войти
                    </button>

                    <button class="auth-register-btn">
                        Регистрация
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        var loginUrl = window.APP_LOGIN_URL || '/login.php';
        var registerUrl = window.APP_REGISTER_URL || '/register.php';

        var redirect = encodeURIComponent(
            window.location.pathname + window.location.search
        );

        function closeModal() {
            modal.remove();
        }

        modal.querySelector('.auth-required-modal__close')
            .addEventListener('click', closeModal);

        modal.querySelector('.auth-required-modal__backdrop')
            .addEventListener('click', closeModal);

        modal.querySelector('.auth-login-btn')
            .addEventListener('click', function () {
                window.location.href =
                    loginUrl +
                    (loginUrl.indexOf('?') === -1 ? '?' : '&') +
                    'redirect=' + redirect;
            });

        modal.querySelector('.auth-register-btn')
            .addEventListener('click', function () {
                window.location.href =
                    registerUrl +
                    (registerUrl.indexOf('?') === -1 ? '?' : '&') +
                    'redirect=' + redirect;
            });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-cart-add]');

        if (!button) return;

        event.preventDefault();

        if (!window.APP_IS_AUTH) {

            if (window.AppToast && typeof window.AppToast.show === 'function') {
                window.AppToast.show(
                    'Для добавления в корзину войдите или зарегистрируйтесь',
                    'error'
                );
            }

            setTimeout(function () {
                showAuthModal();
            }, 150);

            return;
        }

        var id = button.getAttribute('data-cart-id');

        if (!id) return;

        addItem(id, 1);

        syncButtons();
        updateCounter();

        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(
                'Товар добавлен в корзину',
                'success'
            );
        }
    });

    window.CartStorage = {
        key: STORAGE_KEY,
        readItems: readItems,
        saveItems: saveItems,
        addItem: addItem,
        setItemQty: setItemQty,
        removeItem: removeItem,
        clearCart: clearCart,
        getQtyById: getQtyById,
        getTotalCount: getTotalCount,
        updateCounter: updateCounter,
        syncButtons: syncButtons
    };

    syncButtons();
    updateCounter();
});