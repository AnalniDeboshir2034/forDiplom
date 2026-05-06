<?php
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/auth_lib.php';
$siteSettings = $siteSettings ?? load_site_settings();
$currentUser = null;
try {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $currentUser = app_current_user($mysqli);
    }
} catch (Throwable $e) {
    $currentUser = null;
}

$headerCatalogFilters = [];
if (isset($mysqli) && $mysqli instanceof mysqli) {
    try {
        if (@$mysqli->ping()) {
            $res = $mysqli->query("SELECT name, slug FROM `filter` ORDER BY id ASC LIMIT 3");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $headerCatalogFilters[] = $row;
                }
                $res->free();
            }
        }
    } catch (Throwable $e) {
    }
}
?>
<header class="header">
    <div class="container">
        <div class="header__inner">
            <div class="header-left">
                <button type="button" class="header-burger-btn" id="headerBurgerBtn" aria-label="Открыть меню" aria-expanded="false">
                    ☰
                </button>
            <a href="<?= htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="logo">
                <span class="logo__img">
                    <img src="<?= htmlspecialchars(app_url('products/icon.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Medikator.ru">
                </span>
            </a>
            </div>

            <nav class="nav" id="siteMainNav">
                <button type="button" class="header-nav-close" id="headerNavClose" aria-label="Закрыть меню">×</button>
                <ul class="nav__list">                    <li class="nav__item nav-catalog">
                        <a href="/catalog" class="nav__link nav-catalog__toggle" id="headerCatalogToggle" aria-expanded="false">
                            Каталог <span class="nav-catalog__arrow">▾</span>
                        </a>
                        <ul class="nav-catalog__dropdown" id="headerCatalogDropdown">
                            <li><a href="/catalog">Все товары</a></li>
                            <?php foreach ($headerCatalogFilters as $f): ?>
                                <li><a href="/catalog#cat=<?= rawurlencode($f['slug']) ?>"><?= htmlspecialchars($f['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav__item"><a href="/compare" class="nav__link nav__link--compare">Сравнение <span class="nav-cart-count" data-compare-count>0</span></a></li>
                    <li class="nav__item"><a href="/contacts" class="nav__link">Контакты</a></li>
                </ul>
            </nav>  

            <form class="header-search" id="headerSearchForm">
                <input id="headerSearchInput" name="q" type="text" placeholder="Поиск товара...">
                <button type="submit" aria-label="Поиск"><img src="<?= htmlspecialchars(app_url('products/search.png'), ENT_QUOTES, 'UTF-8') ?>" alt=""></button>
            </form>

            <div class="header-right">
                <button type="button" class="header-search-mobile-btn" id="headerSearchMobileBtn" aria-label="Поиск">
                    <img src="<?= htmlspecialchars(app_url('products/search.png'), ENT_QUOTES, 'UTF-8') ?>" alt="">
                </button>
                <a href="<?= htmlspecialchars($currentUser ? app_url('account.php') : app_url('login.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav__link" style="margin-right:12px; font-weight:700;">
                    <?= $currentUser ? 'Кабинет' : 'Войти' ?>
                </a>
                <a href="<?= htmlspecialchars(app_url('cart.php'), ENT_QUOTES, 'UTF-8') ?>" class="header-cart-btn" aria-label="Корзина">
                    <img src="<?= htmlspecialchars(app_url('products/cart.png'), ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <span class="header-cart-btn__count" data-cart-count>0</span>
                </a>
            </div>

            <button type="button" class="btn btn-primary header-lead-open" data-lead-open>
                <?= htmlspecialchars($siteSettings['header']['lead_button_text']) ?>
            </button>
        </div>
    </div>
</header>
<div class="header-spacer" aria-hidden="true"></div>
<div class="header-menu-overlay" id="headerMenuOverlay" aria-hidden="true"></div>
<div class="header-search-modal" id="headerSearchModal" aria-hidden="true">
    <div class="header-search-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="headerSearchModalTitle">
        <button type="button" class="header-search-modal__close" id="headerSearchModalClose" aria-label="Закрыть поиск">×</button>
        <h3 id="headerSearchModalTitle">Поиск по каталогу</h3>
        <form class="header-search-modal__form" id="headerSearchModalForm">
            <input id="headerSearchModalInput" type="text" name="q" placeholder="Введите название товара...">
            <button type="submit">Найти</button>
        </form>
    </div>
</div>

    <div class="header-lead-modal" id="headerLeadModal" aria-hidden="true">
        <div class="header-lead-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="headerLeadTitle">
            <button type="button" class="header-lead-modal__close" data-lead-close aria-label="Закрыть форму">&times;</button>
            <h3 class="header-lead-modal__title" id="headerLeadTitle">Оставить заявку</h3>
            <p class="header-lead-modal__subtitle">Оставьте контакты, и мы свяжемся с вами.</p>

            <form id="headerLeadForm" class="header-lead-form">
                <input type="text" name="name" placeholder="Ваше имя" required>
                <input type="tel" name="phone" placeholder="Ваш телефон" required>
                <input type="text" name="callback_time" placeholder="Удобное время для звонка (например, 14:00-16:00)">
                <textarea name="message" rows="4" placeholder="Сообщение (необязательно)"></textarea>
                <label class="form-consent">
                    <input type="checkbox" class="form-consent__check" required>
                    <span>
                        Отправляя запрос, я соглашаюсь с
                        <a href="<?= htmlspecialchars(app_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>">правилами обработки персональных данных</a>.
                    </span>
                </label>
                <input type="hidden" name="form_type" value="Модальная форма в хедере">
                <button type="submit" class="btn btn-primary header-lead-form__submit">Отправить</button>
            </form>

            <p id="headerLeadStatus" class="header-lead-form__status" aria-live="polite"></p>
        </div>
    </div>

    <script>
        window.APP_BASE = <?= json_encode(APP_BASE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.APP_IS_AUTH = <?= $currentUser ? 'true' : 'false' ?>;
        window.APP_LOGIN_URL = <?= json_encode(app_url('login.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.APP_REGISTER_URL = <?= json_encode(app_url('register.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js" defer></script>
    <script src="<?= htmlspecialchars(app_url('js/header.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(app_url('js/compare-storage.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(app_url('js/cart-storage.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(app_url('js/toast.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(app_url('js/phone-mask.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>