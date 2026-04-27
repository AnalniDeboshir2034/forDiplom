<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/water_treatment.php';
require_once __DIR__ . '/includes/seo.php';
$siteSettings = load_site_settings();

if (!$mysqli || $mysqli->connect_error) {
    die("Нет соединения с БД");
}

$products = [];
$sql = "
    SELECT m.*,
           (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img
    FROM medicator m
    ORDER BY m.id
";
$result = $mysqli->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'slug' => $row['slug'] ?? '',
            'name' => $row['name'] ?? '',
            'image' => $row['main_img'] ?? '',
            'series' => $row['filtr'] ?? '',
        ];
    }
}

$waterTreatmentProduct = load_water_treatment_product();
if (is_array($waterTreatmentProduct)) {
    $products[] = [
        'id' => 0,
        'slug' => $waterTreatmentProduct['slug'] ?? 'water-treatment',
        'name' => $waterTreatmentProduct['name'] ?? 'Узел водоподготовки',
        'image' => $waterTreatmentProduct['main_img'] ?? '',
        'series' => 'Узел водоподготовки',
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= htmlspecialchars(app_url('products/favicon.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <?php seo_render_meta([
        'title' => 'Корзина | Medikator.ru',
        'description' => 'Оформление заказа медикаторов и комплектующих.',
        'canonical' => seo_canonical_url('/cart'),
        'robots' => 'noindex,follow',
        'image' => app_url('products/icon.png'),
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <link rel="stylesheet" href="css/cart.css">
    <script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=108462270', 'ym');

    ym(108462270, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/108462270" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
 <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-8J625SZ5ZB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-8J625SZ5ZB');
</script>
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="main cart-page">
        <section class="cart-print-header" aria-hidden="true">
            <div class="container">
                <div class="cart-print-brand">
                    <img src="/products/icon.png" alt="Medikator.ru">
                    <h1>Заказ Medikator.ru</h1>
                </div>
                <div class="cart-print-contacts">
                    <span>Телефон: <?= htmlspecialchars($siteSettings['contacts']['phone'] ?? '') ?></span>
                    <span>Email: <?= htmlspecialchars($siteSettings['contacts']['email'] ?? '') ?></span>
                </div>
            </div>
        </section>
        <section class="cart-section">
            <div class="container">
                <h1 class="cart-title">Корзина</h1>

                <div class="cart-grid">
                    <div>
                        <div id="cart-empty" class="cart-empty">
                            <p>Корзина пуста. Добавьте товары из каталога.</p>
                            <a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Перейти в каталог</a>
                        </div>
                        <div id="cart-list" class="cart-list" style="display:none;"></div>
                    </div>

                    <aside id="cart-sidebar" class="cart-sidebar" style="display:none;">
                        <div class="cart-totals" style="width:100%; margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; gap:10px; margin:6px 0;">
                                <span class="muted">Сумма</span>
                                <strong id="cartSubtotal">0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:10px; margin:6px 0;">
                                <span class="muted">Скидка</span>
                                <strong id="cartDiscount">0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:10px; margin:6px 0;">
                                <span class="muted">Итого</span>
                                <strong id="cartTotal">0.00</strong>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" id="cart-checkout-open">Оформить заказ</button>
                        <button type="button" class="btn btn-secondary" id="cart-print">Распечатать заказ</button>
                        <button type="button" class="cart-clear" id="cart-clear">Очистить корзину</button>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <div class="cart-modal" id="cartCheckoutModal" aria-hidden="true">
        <div class="cart-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cartCheckoutTitle">
            <button type="button" class="cart-modal__close" id="cart-checkout-close" aria-label="Закрыть форму">&times;</button>
            <h3 id="cartCheckoutTitle">Оформление заказа</h3>
            <p>Заполните контакты, доставку и оплату.</p>

            <form id="cart-checkout-form" class="cart-checkout-form">
                <input type="text" name="name" placeholder="Ваше имя" required>
                <input type="tel" name="phone" placeholder="Ваш телефон" required>
                <input type="email" name="email" placeholder="E-mail (для статуса заказа)">
                <select name="delivery_type" required>
                    <option value="" selected disabled>Способ доставки</option>
                    <option value="courier">Курьер</option>
                    <option value="pickup">Самовывоз</option>
                </select>
                <input type="text" name="delivery_address" placeholder="Адрес доставки (если курьер)">
                <input type="text" name="pickup_point" placeholder="Пункт самовывоза (если самовывоз)">
                <select name="payment_type" required>
                    <option value="" selected disabled>Способ оплаты</option>
                    <option value="invoice">Юр. лицо — накладная</option>
                    <option value="card_on_delivery">Физ. лицо — картой при получении</option>
                    <option value="erip">ЕРИП</option>
                </select>
                <input type="text" name="promo_code" placeholder="Промокод (если есть)">
                <textarea name="message" rows="4" placeholder="Комментарий к заказу"></textarea>
                <label class="form-consent">
                    <input type="checkbox" class="form-consent__check" required>
                    <span>
                        Отправляя запрос, я соглашаюсь с
                        <a href="<?= htmlspecialchars(app_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>">правилами обработки персональных данных</a>.
                    </span>
                </label>
                <button type="submit" class="btn btn-primary">Отправить заказ</button>
            </form>
            <p id="cart-checkout-status" class="cart-checkout-status" aria-live="polite"></p>
        </div>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php $mysqli->close(); ?>

    <script>
        window.ALL_PRODUCTS_FOR_CART = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="js/cart-page.js" defer></script>
        <script>
        (function(w,d,u){
    var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
    var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
})(window,document,'https://cdn-ru.bitrix24.by/b15313854/crm/site_button/loader_2_el7etg.js');
    </script>
</body>
</html>
