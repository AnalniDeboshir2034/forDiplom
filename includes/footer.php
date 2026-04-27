<?php
require_once __DIR__ . '/site_settings.php';
$siteSettings = $siteSettings ?? load_site_settings();
?>
<footer class="footer">
        <div class="container">
            <div class="footer__content">
                <div class="footer__col">
                    <a href="<?= htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="footer-logo"><img src="<?= htmlspecialchars(app_url('products/footer-icon.png'), ENT_QUOTES, 'UTF-8') ?>"></a>
                    <p class="footer__text"><?= htmlspecialchars($siteSettings['footer']['company_description']) ?></p>
                </div>
                <div class="footer__col">
                    <h3 class="footer__title">Контакты</h3>
                    <ul class="footer__list">
                        <li>📞 <?= htmlspecialchars($siteSettings['contacts']['phone']) ?></li>
                        <li>📞 +375 (33) 680-07-07</li>
                        <li>✉️ <?= htmlspecialchars($siteSettings['contacts']['email']) ?></li>
                        <li>📍 <?= htmlspecialchars($siteSettings['contacts']['address']) ?></li>
                        <li>📍 г. Минск, ул. Толбухина, д.2</li>
                    </ul>
                </div>
                <div class="footer__col">
                    <h3 class="footer__title">Навигация</h3>
                    <ul class="footer__list">
                        <li><a href="<?= htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a></li>
                        <li><a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>">Каталог</a></li>
                        <li><a href="<?= htmlspecialchars(app_url('contacts.php'), ENT_QUOTES, 'UTF-8') ?>">Контакты</a></li>
                        <li><a href="<?= htmlspecialchars(app_url('compare.php'), ENT_QUOTES, 'UTF-8') ?>">Сравнение</a></li>
                        <li><a href="<?= htmlspecialchars(app_url('cart.php'), ENT_QUOTES, 'UTF-8') ?>">Корзина</a></li>
                    </ul>
                </div>
                <div class="footer__col">
                    <h3 class="footer__title">Часы работы</h3>
                    <ul class="footer__list">
                        <li><?= htmlspecialchars($siteSettings['contacts']['work_hours']) ?></li>
                        <li>Сб-Вс: Выходной</li>
                    </ul>
                </div>
            </div>
            <div class="footer__bottom">
                <p><?= htmlspecialchars($siteSettings['footer']['copyright']) ?></p>
            </div>
        </div>
    </footer>