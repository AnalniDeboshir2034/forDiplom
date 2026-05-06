<?php 
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/bbcode.php';
require_once __DIR__ . '/includes/water_treatment.php';
require_once __DIR__ . '/includes/seo.php';

if (!$mysqli || $mysqli->connect_error) {
    die("❌ Нет соединения с БД");
}

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$waterTreatmentProduct = load_water_treatment_product();
$isWaterTreatmentProduct = is_array($waterTreatmentProduct) && ($slug === ($waterTreatmentProduct['slug'] ?? ''));

$product = null;
if ($isWaterTreatmentProduct) {
    $product = $waterTreatmentProduct;
    $product['filter_name'] = 'Узел водоподготовки';
    $product['filter_slug'] = 'uzel-vodopodgotovki';
} else {
    $stmt = $mysqli->prepare("
        SELECT m.*, 
               f.name as filter_name,
               f.slug as filter_slug
        FROM `medicator` m 
        LEFT JOIN `filter` f ON m.filtr = f.slug
        WHERE m.slug = ?
    ");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
}

if (!$product) {
    header('Location: index.php');
    exit;
}

$productCanonical = seo_canonical_url('/product/' . rawurlencode((string)$product['slug']));
$productDescriptionSource = trim(strip_tags((string)($product['opis'] ?? '')));
if ($productDescriptionSource === '') {
    $productDescriptionSource = 'Медикатор-дозатор для сельского хозяйства: технические характеристики, описание и консультация по подбору.';
}
$productDescription = function_exists('mb_substr')
    ? mb_substr($productDescriptionSource, 0, 180, 'UTF-8')
    : substr($productDescriptionSource, 0, 180);
$productOgImage = (string)($product['main_img'] ?? '/products/medikator.jpg');

$images = [];
if ($isWaterTreatmentProduct) {
    $images[] = ['path_img' => $product['main_img'] ?? '', 'is_Main' => 1];
} else {
    $stmt = $mysqli->prepare("
        SELECT * FROM `medicator_img` 
        WHERE medicator_id = ? 
        ORDER BY sort ASC, id ASC
    ");
    $stmt->bind_param("i", $product['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
}

if (empty($images)) {
    $images[] = ['path_img' => '', 'is_Main' => 1];
}

$views = 0;
if (!$isWaterTreatmentProduct) {
    $stmt = $mysqli->prepare("
        UPDATE medicator_view
        SET view_count = view_count + 1,
            medicator_name = ?
        WHERE medicator_id = ?
          AND DATE(view_data) = CURDATE()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("si", $product['name'], $product['id']);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt = $mysqli->prepare("
            INSERT INTO medicator_view (medicator_id, medicator_name, view_data, view_count)
            VALUES (?, ?, CURDATE(), 1)
        ");
        $stmt->bind_param("is", $product['id'], $product['name']);
        $stmt->execute();
    }

    $stmt = $mysqli->prepare("
        SELECT SUM(view_count) as total_views 
        FROM medicator_view 
        WHERE medicator_id = ?
    ");
    $stmt->bind_param("i", $product['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewsRow = $result->fetch_assoc();
    $views = $viewsRow['total_views'] ?? 0;
}

$relatedProducts = [];
$relatedSeen = [];
$upsellProducts = [];
if (!$isWaterTreatmentProduct) {
    $stmt = $mysqli->prepare("
        SELECT m.*,
               (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img
        FROM medicator m
        WHERE m.id != ? AND m.filtr = ?
        ORDER BY m.id DESC
        LIMIT 8
    ");
    $stmt->bind_param("is", $product['id'], $product['filtr']);
    $stmt->execute();
    $resRelated = $stmt->get_result();
    while ($resRelated && ($row = $resRelated->fetch_assoc())) {
        $rid = (int)$row['id'];
        if (!isset($relatedSeen[$rid])) {
            $relatedSeen[$rid] = true;
            $relatedProducts[] = $row;
        }
    }

    if (count($relatedProducts) < 4) {
        $stmt = $mysqli->prepare("
            SELECT m.*,
                   (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img
            FROM medicator m
            WHERE m.id != ?
            ORDER BY m.id DESC
            LIMIT 8
        ");
        $stmt->bind_param("i", $product['id']);
        $stmt->execute();
        $resAny = $stmt->get_result();
        while ($resAny && ($row = $resAny->fetch_assoc())) {
            $rid = (int)$row['id'];
            if (!isset($relatedSeen[$rid])) {
                $relatedSeen[$rid] = true;
                $relatedProducts[] = $row;
            }
        }
    }
}

if ($isWaterTreatmentProduct) {
    $stmt = $mysqli->prepare("
        SELECT m.*,
               (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img
        FROM medicator m
        ORDER BY m.id DESC
        LIMIT 8
    ");
    $stmt->execute();
    $resAny = $stmt->get_result();
    while ($resAny && ($row = $resAny->fetch_assoc())) {
        $relatedProducts[] = $row;
    }
}

if (is_array($waterTreatmentProduct) && !$isWaterTreatmentProduct) {
    $upsellProducts[] = [
        'id' => 0,
        'name' => $waterTreatmentProduct['name'],
        'slug' => $waterTreatmentProduct['slug'],
        'filtr' => 'Узел водоподготовки',
        'main_img' => $waterTreatmentProduct['main_img'],
        'type' => 'water-treatment',
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
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <?php seo_render_meta([
        'title' => (string)$product['name'] . ' | Medikator.ru',
        'description' => $productDescription,
        'canonical' => $productCanonical,
        'image' => $productOgImage,
        'type' => 'product',
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/product.css">
    <?php seo_render_organization_jsonld(); ?>
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string)($product['name'] ?? ''),
            'description' => $productDescription,
            'image' => [preg_match('#^https?://#i', $productOgImage) ? $productOgImage : seo_site_base_url() . '/' . ltrim($productOgImage, '/')],
            'url' => $productCanonical,
            'sku' => (string)($product['slug'] ?? ''),
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Medikator.ru',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Главная',
                    'item' => seo_site_base_url() . '/',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Каталог',
                    'item' => seo_site_base_url() . '/catalog',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => (string)($product['name'] ?? 'Товар'),
                    'item' => $productCanonical,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
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
        <div class="container">
            <div class="breadcrumbs">
                <a href="<?= htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная</a> /
                <a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>">Каталог</a> /
                <?php if ($product['filter_name']): ?>
                <a href="<?= htmlspecialchars(app_url('catalog.php?category=' . rawurlencode($product['filter_slug'])), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($product['filter_name']) ?>
                </a> /
                <?php endif; ?>
                <span><?= htmlspecialchars($product['name']) ?></span>
            </div>

            <div class="product-page">
                <div class="product-left">
                    <div class="product-gallery">
                        <div class="gallery-main">
                            <?php foreach ($images as $index => $img): ?>
                            <div class="gallery-slide" data-index="<?= $index ?>" style="display: <?= $index === 0 ? 'flex' : 'none' ?>;">
                                <?php if (!empty($img['path_img'])): ?>
                                    <img src="<?= htmlspecialchars($img['path_img']) ?>" 
                                         alt="<?= htmlspecialchars(seo_product_image_alt($product, 'Фото ' . ($index + 1))) ?>">
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <span>📷</span>
                                        <p><?= htmlspecialchars($product['name']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($images) > 1): ?>
                        <div class="gallery-nav">
                            <button class="gallery-prev" id="galleryPrev">‹</button>
                            <div class="gallery-thumbs">
                                <?php foreach ($images as $index => $img): ?>
                                <div class="thumb" data-index="<?= $index ?>">
                                    <?php if (!empty($img['path_img'])): ?>
                                        <img src="<?= htmlspecialchars($img['path_img']) ?>" alt="<?= htmlspecialchars(seo_product_image_alt($product, 'Миниатюра ' . ($index + 1))) ?>">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">📷</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="gallery-next" id="galleryNext">›</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-tabs">
                        <div class="tabs-header">
                            <button class="tab-btn active" data-tab="description">Описание</button>
                            <?php if (!$isWaterTreatmentProduct): ?>
                            <button class="tab-btn" data-tab="docs">Документация</button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tabs-content">
                            <div class="tab-pane active" id="tab-description">
                                <div class="product-description">
                                    <?php if (!empty($product['opis'])): ?>
                                        <?= bbcode_to_html($product['opis']) ?>
                                    <?php else: ?>
                                        <p>Описание товара отсутствует</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!$isWaterTreatmentProduct): ?>
                            <div class="tab-pane" id="tab-docs">
                                <div class="product-docs">
                                    <?php if (!empty($product['passport']) || !empty($product['user_pass'])): ?>
                                        <div class="docs-list">
                                            <?php if (!empty($product['passport'])): ?>
                                            <a href="<?= htmlspecialchars($product['passport']) ?>" class="doc-item" target="_blank">
                                                <div class="doc-icon">📄</div>
                                                <div class="doc-info">
                                                    <div class="doc-title">Паспорт изделия</div>
                                                    <div class="doc-size">PDF документ</div>
                                                </div>
                                                <div class="doc-download">Скачать</div>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($product['user_pass'])): ?>
                                            <a href="<?= htmlspecialchars($product['user_pass']) ?>" class="doc-item" target="_blank">
                                                <div class="doc-icon">📖</div>
                                                <div class="doc-info">
                                                    <div class="doc-title">Руководство пользователя</div>
                                                    <div class="doc-size">PDF документ</div>
                                                </div>
                                                <div class="doc-download">Скачать</div>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>Документация отсутствует</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="product-right">
                    <div class="product-badge">
                        <?= htmlspecialchars($product['filter_name'] ?? 'Медикатор') ?>
                    </div>
                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                    <?php $productPrice = isset($product['price']) ? (float)$product['price'] : 0; ?>
                    <div class="product-price-box">
                        <?= $productPrice > 0 ? (htmlspecialchars(number_format($productPrice, 2, '.', ' ')) . ' BYN') : 'Цена по запросу' ?>
                    </div>
                                        

                    <div class="product-specs">
                        <h3>Технические характеристики</h3>
                        <div class="specs-grid">
                            <?php if ($isWaterTreatmentProduct): ?>
                                <?php foreach (($product['table_rows'] ?? []) as $row): ?>
                                    <?php if (($row['label'] ?? '') === 'давление воды, устанавливаемое регулятором, кгс/см'): ?>
                                    <div class="spec-item spec-item--header">
                                        <span class="spec-label"><?= htmlspecialchars($row['label']) ?></span>
                                        <span class="spec-value"></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="spec-item">
                                        <span class="spec-label"><?= htmlspecialchars((string)($row['label'] ?? '')) ?></span>
                                        <span class="spec-value"><?= htmlspecialchars((string)($row['value'] ?? '')) ?></span>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (!empty($product['d_dosing'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Диапазон дозирования</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['d_dosing']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['performance'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Производительность</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['performance']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['pressure'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Рабочее давление</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['pressure']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['temperature'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Температура жидкости</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['temperature']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['connections'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Тип подключения</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['connections']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['m_seal'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Материал уплотнений</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['m_seal']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['m_case'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Материал корпуса</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['m_case']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['dop'])): ?>
                                <div class="spec-item">
                                    <span class="spec-label">Дополнительно</span>
                                    <span class="spec-value"><?= htmlspecialchars($product['dop']) ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-actions">
                        <a href="#" class="btn btn-order open-modal-form" data-form="hero">Заказать</a>
                        <?php if (!$isWaterTreatmentProduct): ?>
                            <button
                                type="button"
                                class="btn btn-secondary btn-compare"
                                data-compare-id="<?= (int)$product['id'] ?>"
                            >
                                В сравнение
                            </button>
                            <button
                                type="button"
                                class="btn btn-secondary btn-cart"
                                data-cart-add
                                data-cart-id="<?= (int)$product['id'] ?>"
                            >
                                В корзину
                            </button>
                        <?php else: ?>
                            <button
                                type="button"
                                class="btn btn-secondary btn-cart"
                                data-cart-add
                                data-cart-id="0"
                            >
                                В корзину
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if (!$isWaterTreatmentProduct && !empty($relatedProducts)): ?>
    <section class="related-products">
        <div class="container">
            <h2 class="section-title">Похожие <span class="gradient-text">товары</span></h2>
            <div class="related-products__list">
                <?php foreach ($relatedProducts as $rp): ?>
                    <article class="related-product-card">
                        <div class="related-product-card__image">
                            <img src="<?= htmlspecialchars($rp['main_img'] ?? 'products/medikator.jpg') ?>" alt="<?= htmlspecialchars(seo_product_image_alt($rp, 'Похожий товар')) ?>">
                        </div>
                        <div class="related-product-card__body">
                            <h3><?= htmlspecialchars($rp['name']) ?></h3>
                            <p><?= htmlspecialchars($rp['filtr'] ?? 'Серия') ?></p>
                            <a class="btn btn-primary" href="<?= htmlspecialchars(app_product_url((string)$rp['slug']), ENT_QUOTES, 'UTF-8') ?>">Подробнее</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($isWaterTreatmentProduct && !empty($relatedProducts)): ?>
    <section class="related-products">
        <div class="container">
            <h2 class="section-title">
                Рекомендуем вместе с этим <span class="gradient-text">товаром</span>
            </h2>
            <div class="related-products__list">
                <?php foreach ($relatedProducts as $rp): ?>
                    <article class="related-product-card">
                        <div class="related-product-card__image">
                            <img src="<?= htmlspecialchars($rp['main_img'] ?? 'products/medikator.jpg') ?>" alt="<?= htmlspecialchars(seo_product_image_alt($rp, 'Рекомендуемый товар')) ?>">
                        </div>
                        <div class="related-product-card__body">
                            <h3><?= htmlspecialchars($rp['name']) ?></h3>
                            <p><?= htmlspecialchars($rp['filtr'] ?? 'Серия') ?></p>
                            <a class="btn btn-primary" href="<?= htmlspecialchars(app_product_url((string)$rp['slug']), ENT_QUOTES, 'UTF-8') ?>">Подробнее</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$isWaterTreatmentProduct && !empty($upsellProducts)): ?>
    <section class="related-products">
        <div class="container">
            <h2 class="section-title">Вместе с этим <span class="gradient-text">берут</span></h2>
            <div class="related-products__list">
                <?php foreach ($upsellProducts as $rp): ?>
                    <article class="related-product-card">
                        <div class="related-product-card__image">
                            <img src="<?= htmlspecialchars($rp['main_img'] ?? 'products/medikator.jpg') ?>" alt="<?= htmlspecialchars(seo_product_image_alt($rp, 'Вместе с этим товаром')) ?>">
                        </div>
                        <div class="related-product-card__body">
                            <h3><?= htmlspecialchars($rp['name']) ?></h3>
                            <p><?= htmlspecialchars($rp['filtr'] ?? 'Серия') ?></p>
                            <a class="btn btn-primary" href="<?= htmlspecialchars(app_product_url((string)$rp['slug']), ENT_QUOTES, 'UTF-8') ?>">Подробнее</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php $mysqli->close(); ?>
    
    <script src="<?= htmlspecialchars(app_url('js/product.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        (function(w,d,u){
                var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
                var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
        })(window,document,'https://cdn-ru.bitrix24.by/b15313854/crm/site_button/loader_2_el7etg.js');
</script>
</body>
</html>