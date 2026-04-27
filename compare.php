<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seo.php';

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
            'd_dosing' => $row['d_dosing'] ?? '',
            'performance' => $row['performance'] ?? '',
            'pressure' => $row['pressure'] ?? '',
            'temperature' => $row['temperature'] ?? '',
            'connections' => $row['connections'] ?? '',
            'm_seal' => $row['m_seal'] ?? '',
            'm_case' => $row['m_case'] ?? '',
            'dop' => $row['dop'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= htmlspecialchars(app_url('products/favicon.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <?php seo_render_meta([
        'title' => 'Сравнение товаров | Medikator.ru',
        'description' => 'Сравнивайте медикаторы по ключевым характеристикам и выбирайте оптимальную модель.',
        'canonical' => seo_canonical_url('/compare'),
        'robots' => 'noindex,follow',
        'image' => app_url('products/icon.png'),
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/compare.css">
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

    <main class="main compare-page">
        <section class="compare-section">
            <div class="container">
                <div class="compare-head">
                    <h1 class="compare-title">Сравнение медикаторов</h1>
                    <p class="compare-subtitle">Добавьте товары из каталога, главной или карточки товара</p>
                </div>

                <div id="compare-empty" class="compare-empty">
                    <p>Вы пока не добавили товары в сравнение.</p>
                    <a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Перейти в каталог</a>
                </div>

                <div id="compare-table-wrap" class="compare-table-wrap" style="display:none;">
                   

                    <div class="compare-table-scroll">
                        <table class="compare-table" id="compare-table"></table>
                    </div>
                     <div class="compare-actions">
                        <button type="button" class="btn btn-secondary" id="compare-clear">Очистить сравнение</button>
                        <a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Добавить еще товары</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php $mysqli->close(); ?>

    <script>
        window.ALL_PRODUCTS_FOR_COMPARE = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="<?= htmlspecialchars(app_url('js/compare-page.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
        <script>
        (function(w,d,u){
    var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
    var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
    })(window,document,'https://cdn-ru.bitrix24.by/b15313854/crm/site_button/loader_2_el7etg.js');
    </script>
</body>
</html>
