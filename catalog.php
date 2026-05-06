<?php 
require_once 'includes/config.php';
require_once __DIR__ . '/includes/water_treatment.php';
require_once __DIR__ . '/includes/seo.php';

if (!$mysqli || $mysqli->connect_error) {
    die("❌ Нет соединения с БД");
}

$catalogCanonical = seo_canonical_url('/catalog');
$catalogDescription = 'Каталог медикаторов-дозаторов для животноводства и птицеводства: фильтры по сериям, технические характеристики и быстрый переход в карточки товаров.';

$filters = [];
$result = $mysqli->query("SELECT * FROM `filter` ORDER BY `id`");
while ($row = $result->fetch_assoc()) {
    $filters[] = $row;
}

$subfilters = [];
$result = $mysqli->query("
    SELECT fr.*, f.name as filter_name, f.slug as filter_slug, 
           sf.name as subfilter_name, sf.slug as subfilter_slug 
    FROM `filter_Relationships` fr 
    JOIN `filter` f ON fr.filter_id = f.id 
    JOIN `subfilter` sf ON fr.subfilter_id = sf.id 
    ORDER BY f.id, sf.id
");
while ($row = $result->fetch_assoc()) {
    $subfilters[] = $row;
}

$products = [];
$result = $mysqli->query("
    SELECT m.*,
           sf.slug AS subfilter_slug,
           sf.name AS subfilter_name,
           f.slug AS filter_slug,
           (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img
    FROM `medicator` m
    LEFT JOIN `subfilter` sf
        ON LOWER(TRIM(sf.slug)) = LOWER(TRIM(m.filtr))
        OR LOWER(TRIM(sf.name)) = LOWER(TRIM(m.filtr))
    LEFT JOIN `filter_Relationships` fr ON fr.subfilter_id = sf.id
    LEFT JOIN `filter` f ON f.id = fr.filter_id
    ORDER BY m.id
");
while ($row = $result->fetch_assoc()) {
    $images = [];
    $imgResult = $mysqli->query("SELECT * FROM medicator_img WHERE medicator_id = {$row['id']} ORDER BY sort ASC");
    while ($imgRow = $imgResult->fetch_assoc()) {
        $images[] = $imgRow;
    }
    $row['images'] = $images;
    $products[] = $row;
}

$waterTreatmentProduct = load_water_treatment_product();
if (is_array($waterTreatmentProduct)) {
    $waterTreatmentProduct['filter_slug'] = 'uzel-vodopodgotovki';
    $waterTreatmentProduct['subfilter_slug'] = 'uzel-vodopodgotovki';
    $waterTreatmentProduct['price'] = null;
    $waterTreatmentProduct['manufacturer'] = 'Medikator.ru';
    $products[] = $waterTreatmentProduct;
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <link rel="icon" href="<?= htmlspecialchars(app_url('products/favicon.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <?php seo_render_meta([
        'title' => 'Каталог медикаторов-дозаторов | Medikator.ru',
        'description' => $catalogDescription,
        'canonical' => $catalogCanonical,
        'image' => app_url('products/medikator.jpg'),
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/catalog.css">
    <script src="js/catalog.js" defer></script>
    <?php seo_render_organization_jsonld(); ?>
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

    <main class="main">
        <section class="catalog-hero">
            <div class="container">
                <div class="catalog-hero__content">
                    <h1 class="catalog-hero__title"><span class='gradient-text'>КАТАЛОГ</span></h1>
                    <p class="catalog-hero__subtitle">Каталог медикаторов-дозаторов для сельского хозяйства</p>
                </div>
            </div>
        </section>

        <section class="catalog-categories">
            <div class="container">
                <div class="catalog-categories__list">
                    <button class="catalog-filter-toggle" id="catalogFilterToggle" type="button" aria-expanded="false">☰ Фильтры</button>
                    <button class="category-btn active" data-category="all">Все</button>
                    <?php foreach ($filters as $filter): ?>
                    <button class="category-btn" data-category="<?= htmlspecialchars($filter['slug']) ?>">
                        <?= htmlspecialchars($filter['name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="catalog-main">
            <div class="container">
                <div class="catalog-grid">
                    <aside class="catalog-filters" id="catalogFiltersPanel">
                        <button class="catalog-filters__close" id="catalogFiltersClose" type="button" aria-label="Закрыть фильтры">×</button>
                        <div class="filter-group">
                            <button class="filter-dropdown" data-filter="uzel-vodopodgotovki">
                                Узел водоподготовки
                                <span class="filter-arrow">▼</span>
                            </button>
                            <div class="filter-submenu" data-submenu="uzel-vodopodgotovki">
                                <label>
                                    <input type="checkbox" value="uzel-vodopodgotovki" data-category="all">
                                    Узел водоподготовки
                                </label>
                            </div>
                        </div>
                        <?php if (!empty($subfilters)): ?>
                            <?php 
                            $current_filter = null;
                            foreach ($subfilters as $subfilter):
                                if ($current_filter !== $subfilter['filter_name']):
                                    if ($current_filter !== null): ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="filter-group">
                                        <button class="filter-dropdown" data-filter="<?= htmlspecialchars($subfilter['filter_slug']) ?>">
                                            <?= htmlspecialchars($subfilter['filter_name']) ?>
                                            <span class="filter-arrow">▼</span>
                                        </button>
                                        <div class="filter-submenu" data-submenu="<?= htmlspecialchars($subfilter['filter_slug']) ?>">
                                <?php 
                                $current_filter = $subfilter['filter_name'];
                                endif; 
                                ?>
                                <?php if (($subfilter['subfilter_slug'] ?? '') === 'uzel-vodopodgotovki' || ($subfilter['subfilter_slug'] ?? '') === 'water-treatment') { continue; } ?>
                                <label>
                                    <input type="checkbox" value="<?= htmlspecialchars($subfilter['subfilter_slug']) ?>" 
                                           data-category="<?= htmlspecialchars($subfilter['filter_slug']) ?>">
                                    <?= htmlspecialchars($subfilter['subfilter_name']) ?>
                                </label>
                            <?php endforeach; ?>
                                        </div>
                                    </div>
                        <?php else: ?>
                            <p>Фильтры не найдены</p>
                        <?php endif; ?>
                        <div class="filter-group open">
                            <button class="filter-dropdown" type="button">
                                Цена
                                <span class="filter-arrow">▼</span>
                            </button>
                            <div class="filter-submenu" style="display:block;">
                                <input id="catalogPriceMin" type="number" min="0" step="0.01" placeholder="Цена от">
                                <input id="catalogPriceMax" type="number" min="0" step="0.01" placeholder="Цена до" style="margin-top:8px;">
                            </div>
                        </div>
                        
                        <button class="filter-reset" id="reset-filters">Сбросить фильтры</button>
                    </aside>

                    <div class="catalog-products">
                        <div class="products-header">
                            <span class="products-count" id="products-count">Найдено: <?= count($products) ?> товаров</span>
                            <div class="catalog-sort-bar">
                                <select id="catalogSort">
                                    <option value="default">Сортировка: по умолчанию</option>
                                    <option value="name_asc">По названию (А-Я)</option>
                                    <option value="name_desc">По названию (Я-А)</option>
                                    <option value="price_asc">По цене (возр.)</option>
                                    <option value="price_desc">По цене (убыв.)</option>
                                </select>
                            </div>
                        </div>

                        <div class="products-grid" id="products-grid">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                <?php $isWaterTreatment = ($product['type'] ?? '') === 'water-treatment'; ?>
                                <?php $priceValue = isset($product['price']) ? (float)$product['price'] : 0; ?>
                                <?php $manufacturerValue = trim((string)($product['manufacturer'] ?? '')); ?>
                                <div class="product-card" data-category="<?= htmlspecialchars($product['filter_slug'] ?? '') ?>" 
                                     data-subcategory="<?= htmlspecialchars($product['subfilter_slug'] ?? $product['filtr']) ?>"
                                     data-price="<?= htmlspecialchars((string)$priceValue) ?>"
                                     data-manufacturer="<?= htmlspecialchars($manufacturerValue) ?>">
                                    <div class="product-card__content">
                                        <?php if (!empty($product['main_img'])): ?>
                                        <a class="product-image" href="<?= htmlspecialchars(app_product_url((string)$product['slug']), ENT_QUOTES, 'UTF-8') ?>">
                                            <img src="<?= htmlspecialchars($product['main_img']) ?>" 
                                                 alt="<?= htmlspecialchars(seo_product_image_alt($product, 'Фото товара')) ?>">
                                        </a>
                                        <?php endif; ?>
                                        <h3 class="product-title"><a href="<?= htmlspecialchars(app_product_url((string)$product['slug']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($product['name']) ?></a></h3>
                                        <?php
                                            $waterDescription = (string)($product['opis'] ?? '');
                                            if ($isWaterTreatment && $waterDescription !== '') {
                                                $waterDescription = function_exists('mb_substr')
                                                    ? mb_substr($waterDescription, 0, 120, 'UTF-8')
                                                    : substr($waterDescription, 0, 120);
                                                $waterDescription .= '...';
                                            }
                                        ?>
                                        <p class="product-desc">
                                            <?= htmlspecialchars($isWaterTreatment ? $waterDescription : ($product['filtr'] ?? '')) ?>
                                        </p>
                                        <p class="product-price">
                                            <?= $priceValue > 0 ? (htmlspecialchars(number_format($priceValue, 2, '.', ' ')) . ' BYN') : 'Цена по запросу' ?>
                                        </p>
                                        
                                        <div class="product-specs">
                                            <?php if (!empty($product['d_dosing'])): ?>
                                            <span class="spec">Дозирование: <?= htmlspecialchars($product['d_dosing']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($product['performance'])): ?>
                                            <span class="spec">Производительность: <?= htmlspecialchars($product['performance']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($product['pressure'])): ?>
                                            <span class="spec">Давление: <?= htmlspecialchars($product['pressure']) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="product-actions<?= $isWaterTreatment ? ' product-actions--single' : '' ?>">
                                            <?php if (!$isWaterTreatment): ?>
                                                <button
                                                    type="button"
                                                    class="product-btn product-btn--compare"
                                                    data-compare-id="<?= (int)$product['id'] ?>"
                                                > В сравнение</button>
                                                <button
                                                    type="button"
                                                    class="product-btn product-btn--cart"
                                                    data-cart-add
                                                    data-cart-id="<?= (int)$product['id'] ?>">
                                                    В корзину</button>
                                            <?php else: ?>
                                                <button
                                                    type="button"
                                                    class="product-btn product-btn--cart"
                                                    data-cart-add
                                                    data-cart-id="0">
                                                    В корзину</button>
                                            <?php endif; ?>
                                            <button type="button" class="product-btn product-btn--details" data-href="<?= htmlspecialchars(app_product_url((string)$product['slug']), ENT_QUOTES, 'UTF-8') ?>">Подробнее</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Товары не найдены</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <div class="catalog-filters-overlay" id="catalogFiltersOverlay" aria-hidden="true"></div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php $mysqli->close(); ?>
    <script>
        (function(w,d,u){
    var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
    var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
})(window,document,'https://cdn-ru.bitrix24.by/b15313854/crm/site_button/loader_2_el7etg.js');
    </script>
</body>
</html>