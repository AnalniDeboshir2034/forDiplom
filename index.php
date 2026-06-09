<?php 
require_once 'includes/config.php';
require_once __DIR__ . '/includes/auth_lib.php';
require_once 'includes/site_settings.php';
require_once __DIR__ . '/includes/seo.php';

// Функция проверки существования таблицы
if (!function_exists('db_table_exists')) {
    function db_table_exists($mysqli, $table_name) {
        $result = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table_name) . "'");
        return $result && $result->num_rows > 0;
    }
}

$siteSettings = load_site_settings();

if (!$mysqli || $mysqli->connect_error) {
    die("❌ Нет соединения с БД");
}

$BITRIX_WEBHOOK = 'https://k7s.bitrix24.by/rest/25370/y91iqahj9bllr1gt/crm.lead.add.json';

$form_success = false;
$form_error = '';
$form_data = [];

function is_valid_phone_prefix($phone)
{
    return app_is_valid_phone(app_normalize_phone((string)$phone));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));
    $form_type = htmlspecialchars(trim($_POST['form_type'] ?? 'Контактная форма'));
    
    $form_data = compact('name', 'phone', 'message', 'form_type');
    
    if (empty($name) || empty($phone)) {
        $form_error = 'Пожалуйста, заполните имя и телефон';
    } elseif (!is_valid_phone_prefix($phone)) {
        $form_error = 'Номер телефона невалидный';
    } else {
        $leadData = [
            'fields' => [
                'TITLE' => 'Заявка с сайта Medikator.ru - ' . $form_type,
                'NAME' => $name,
                'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
                'SOURCE_ID' => 'WEB',
                'SOURCE_DESCRIPTION' => $form_type . ' на сайте',
                'ASSIGNED_BY_ID' => 1,
                'STATUS_ID' => 'NEW',
                'COMMENTS' => "Форма: $form_type\nИмя: $name\nТелефон: $phone\nСообщение: $message\n\nДата: " . date('d.m.Y H:i'),
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $BITRIX_WEBHOOK,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($leadData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        file_put_contents('bitrix_log.txt', 
            date('Y-m-d H:i:s') . " | HTTP: $httpCode | Форма: $form_type\n" .
            "Ответ: " . print_r($result, true) . "\n\n",
            FILE_APPEND
        );
        
        if (isset($result['result'])) {
            $form_success = true;
            $form_data = [];
        } else {
            $form_error = 'Ошибка отправки. Пожалуйста, позвоните нам по телефону.';
        }
    }
}

$homeCanonical = seo_canonical_url('/');
$homeDescription = 'Купить медикаторы-дозаторы для сельского хозяйства: подбор под ферму, поставка по России и СНГ, консультация и быстрая доставка.';

$popular_products = [];
$sql = "SELECT m.*, 
               (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img,
               COALESCE(SUM(mv.view_count), 0) as total_views
        FROM medicator m 
        LEFT JOIN medicator_view mv ON m.id = mv.medicator_id
        GROUP BY m.id
        ORDER BY total_views DESC, m.id ASC
        LIMIT 3";
$result = $mysqli->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $popular_products[] = $row;
    }
}

if (empty($popular_products)) {
    $sql = "SELECT m.*, 
                   (SELECT path_img FROM medicator_img WHERE medicator_id = m.id AND is_Main = 1 LIMIT 1) as main_img
            FROM medicator m 
            ORDER BY m.id 
            LIMIT 3";
    $result = $mysqli->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $popular_products[] = $row;
        }
    }
}

$approved_reviews = [];

// ПРОВЕРКА СУЩЕСТВОВАНИЯ ТАБЛИЦЫ reviews - ИСПРАВЛЕНО
$table_check = $mysqli->query("SHOW TABLES LIKE 'reviews'");
if ($table_check && $table_check->num_rows > 0) {

    $reviewsSql = "SELECT 
                        r.*, 
                        u.login,
                        u.name
                    FROM reviews r
                    LEFT JOIN user u ON u.id = r.user_id
                    WHERE r.status = 'approved'
                    ORDER BY r.id DESC
                    LIMIT 12";

    $reviewsResult = $mysqli->query($reviewsSql);

    if ($reviewsResult && $reviewsResult->num_rows > 0) {

        while ($review = $reviewsResult->fetch_assoc()) {
            $approved_reviews[] = $review;
        }
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
    <?php seo_render_meta([
        'title' => 'Medikator.ru - Медикаторы-дозаторы для сельского хозяйства',
        'description' => $homeDescription,
        'canonical' => $homeCanonical,
        'image' => app_url('products/medicator.png'),
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/index.css">
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
    <script src="js/script.js" defer></script>
    <?php seo_render_organization_jsonld($siteSettings); ?>
</head>
<!-- Yandex.Metrika counter -->
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
<body>
    <?php require_once 'includes/header.php'; ?>

    <main class="main">
       
<section class="hero-products">
    <div class="container">
        <div class="hero-products__wrapper">
            <div class="hero-products__content">
                <div class="hero-products__badge">Поставки по всей России,Беларуси и странам СНГ</div>
                <h1 class="hero-products__title">
                    <?= htmlspecialchars($siteSettings['index']['hero_title_top']) ?> <br>
                   <span class="gradient-text"><?= htmlspecialchars($siteSettings['index']['hero_title_bottom']) ?></span>
                </h1>
                <p class="hero-products__text">
                    <?= htmlspecialchars($siteSettings['index']['hero_text']) ?>
                </p>
                <div class="hero-products__buttons">
                    <a href="#" class="btn btn-primary btn-order open-modal-form" data-form="hero">
                        ОСТАВИТЬ ЗАЯВКУ
                        <span class="btn-order__icon" aria-hidden="true">→</span>
                    </a>
                    <a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline btn-catalog">КАТАЛОГ ПРОДУКЦИИ</a>
                </div>
                <div class="hero-products__stats">
                    <div class="stat-item">
                        <span class="stat-value">10+</span>
                        <span class="stat-label">лет на рынке</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">500+</span>
                        <span class="stat-label">Клиентов</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">15</span>
                        <span class="stat-label">регионов</span>
                    </div>
                </div>
            </div>
            <div class="hero-products__image">
                <img src="products/medicator.png" alt="Медикаторы для хозяйств,для птицеводства, для животноводства, для автомоечных">
            </div>
        </div>
    </div>
</section>

        <section class="categories">
            <div class="container">
                <h2 class="section-title">ПОПУЛЯРНЫЕ <span class="gradient-text">КАТЕГОРИИ</span></h2>
                <p class="section-subtitle">Подберите медикатор-дозатор под задачи вашего хозяйства</p>
                
                <div class="categories__grid">
                    <a href="<?= htmlspecialchars(app_url('catalog.php#cat=master-pro'), ENT_QUOTES, 'UTF-8') ?>" class="category-card category-card--gradient-1">
                        <div class="category-card__image">
                            <img src="products/MASTERPRO.png" alt="Медикаторы Master Pro">
                        </div>
                        <h3 class="category-card__title">Медикаторы Master Pro</h3>
                        <p class="category-card__desc">Профессиональные медикаторы для крупных хозяйств с высокой производительностью</p>
                        <span class="category-card__link">Перейти в каталог →</span>
                    </a>

                    <a href="<?= htmlspecialchars(app_url('catalog.php#cat=dosatron'), ENT_QUOTES, 'UTF-8') ?>" class="category-card category-card--gradient-2">
                        <div class="category-card__image">
                            <img src="products/Dosatron.png" alt="Медикаторы Dosatron">
                        </div>
                        <h3 class="category-card__title">Медикаторы Dosatron</h3>
                        <p class="category-card__desc">Французские дозаторы с мировым именем — точность и надёжность</p>
                        <span class="category-card__link">Перейти в каталог →</span>
                    </a>

                    <a href="<?= htmlspecialchars(app_url('catalog.php#cat=mixrite'), ENT_QUOTES, 'UTF-8') ?>" class="category-card category-card--gradient-3">
                        <div class="category-card__image">
                            <img src="products/Mixrite.png" alt="Медикаторы MixRite">
                        </div>
                        <h3 class="category-card__title">Медикаторы MixRite</h3>
                        <p class="category-card__desc">Израильские медикаторы для интенсивного животноводства и птицеводства</p>
                        <span class="category-card__link">Перейти в каталог →</span>
                    </a>
                </div>
            </div>
        </section>


<section class="how-we-work-steps">
    <div class="container">
        <h2 class="section-title">КАК МЫ РАБОТАЕМ — <span class="gradient-text">ПО ШАГАМ</span> </h2>
        
        <div class="steps-grid">
            <div class="step-item">
                <div class="step-icon">1</div>
                <h3 class="step-item__title">Оставляете заявку</h3>
                <p class="step-item__desc">Мы связываемся с вами в течение 15 минут</p>
            </div>

            <div class="step-item">
                <div class="step-icon">2</div>
                <h3 class="step-item__title">Уточняем детали</h3>
                <p class="step-item__desc">Обсуждаем товар, объём и требования к доставке</p>
            </div>

            <div class="step-item">
                <div class="step-icon">3</div>
                <h3 class="step-item__title">Высылаем КП с расчётами</h3>
                <p class="step-item__desc">Даём прозрачную смету: стоимость, сроки, варианты доставки</p>
            </div>

            <div class="step-item">
                <div class="step-icon">4</div>
                <h3 class="step-item__title">Заключаем договор</h3>
                <p class="step-item__desc">Фиксируем все условия и гарантии</p>
            </div>

            <div class="step-item">
                <div class="step-icon">5</div>
                <h3 class="step-item__title">Отправляем заказ</h3>
                <p class="step-item__desc">Доставляем и при необходимости устанавливаем оборудование</p>
            </div>
        </div>
    </div>
</section>

<section class="calc-section">
    <div class="container">
        <div class="calc-card">
            <div class="calc-content">
             <h2 class="calc-title">
                <?= htmlspecialchars($siteSettings['containers']['index_cta_title']) ?> <br><span class="gradient-text"><?= htmlspecialchars($siteSettings['containers']['index_cta_subtitle']) ?></span>
            </h2>
                <p class="calc-text">
                    Оставьте заявку — подберём оптимальную модель медикатора для вашего хозяйства и рассчитаем коммерческое предложение.
                </p>
                
                <form class="calc-form-row" method="POST">
                    <input type="text" name="name" placeholder="Ваше имя" required>
                    <input type="tel" name="phone" placeholder="Ваш телефон" required>
                    <input type="hidden" name="form_type" value="Расчёт стоимости">
                    <button type="submit" class="calc-btn-row">ОСТАВИТЬ ЗАЯВКУ</button>
                </form>
            </div>
        </div>
    </div>
</section>
     
<section class="products">
    <div class="container">
        <h2 class="section-title">ПОПУЛЯРНЫЕ <span class="gradient-text">ТОВАРЫ</span></h2>
        <div class="products__grid">
            <?php if (!empty($popular_products)): ?>
                <?php foreach ($popular_products as $product): ?>
                    <div class="product-card">
                        <?php if (!empty($product['total_views']) && $product['total_views'] > 0): ?>
                            <div class="popular-badge">🔥 Популярный</div>
                        <?php endif; ?>
                        <a class="product-card__image" href="<?= htmlspecialchars(app_product_url((string)$product['slug']), ENT_QUOTES, 'UTF-8') ?>">
                            <img src="<?= htmlspecialchars($product['main_img'] ?? 'products/medikator.jpg') ?>" 
                                 alt="<?= htmlspecialchars(seo_product_image_alt($product, 'Медикатор')) ?>">
                        </a>
                        <div class="product-card__content">
                            <h3 class="product-card__title"><a href="<?= htmlspecialchars(app_product_url((string)$product['slug']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($product['name']) ?></a></h3>
                            <p class="product-card__desc">
                                <?= htmlspecialchars($product['filtr'] ?? 'Серия медикатора') ?>
                            </p>
                            <div class="product-card__actions">
                                <button
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
                                <a href="<?= htmlspecialchars(app_product_url((string)$product['slug']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Подробнее</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="product-card">
                    <div class="product-card__image">
                        <img src="products/medikator.jpg" alt="Медикатор Master Pro">
                    </div>
                    <div class="product-card__content">
                        <h3 class="product-card__title">Медикатор Master Pro 2000</h3>
                        <p class="product-card__desc">Master Pro</p>
                        <div class="product-card__actions">
                            <button class="btn btn-secondary btn-compare" type="button" disabled>В сравнение</button>
                            <a href="#" class="btn btn-primary">Подробнее</a>
                        </div>
                    </div>
                </div>
          
                <div class="product-card">
                    <div class="product-card__image">
                        <img src="products/medikator.jpg" alt="Медикатор Dosatron">
                    </div>
                    <div class="product-card__content">
                        <h3 class="product-card__title">Dosatron D25RE2</h3>
                        <p class="product-card__desc">Dosatron</p>
                        <div class="product-card__actions">
                            <button class="btn btn-secondary btn-compare" type="button" disabled>В сравнение</button>
                            <a href="#" class="btn btn-primary">Подробнее</a>
                        </div>
                    </div>
                </div>
        
                <div class="product-card">
                    <div class="product-card__image">
                        <img src="products/medikator.jpg" alt="Медикатор MixRite">
                    </div>
                    <div class="product-card__content">
                        <h3 class="product-card__title">MixRite TEFEN</h3>
                        <p class="product-card__desc">MixRite</p>
                        <div class="product-card__actions">
                            <button class="btn btn-secondary btn-compare" type="button" disabled>В сравнение</button>
                            <a href="#" class="btn btn-primary">Подробнее</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center">
            <a href="<?= htmlspecialchars(app_url('catalog.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-large">Весь каталог</a>
        </div>
    </div>
</section>
 
<section class="reviews">
    <div class="container">

        <h2 class="section-title">
            ОТЗЫВЫ <span class="gradient-text">КЛИЕНТОВ</span>
        </h2>

        <p class="section-subtitle">
            Проверенные отзывы после модерации
        </p>

        <?php if (!empty($approved_reviews)): ?>

            <div class="reviews-grid">

                <?php foreach ($approved_reviews as $review): ?>

                    <?php
                        $rating = (int)($review['rating'] ?? 5);
                        $rating = max(1, min(5, $rating));

                        $authorName = trim((string)(
                            $review['name']
                            ?? $review['login']
                            ?? 'Пользователь'
                        ));
                    ?>

                    <div class="review-card">

                        <div class="review-stars">
                            <?= str_repeat('★', $rating) ?>
                        </div>

                        <div class="review-text">
                            <?= nl2br(htmlspecialchars((string)$review['text'])) ?>
                        </div>

                        <div class="review-bottom">

                            <div class="review-user">
                                <?= htmlspecialchars($authorName) ?>
                            </div>

                            <div class="review-status">
                                Проверенный отзыв
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="card" style="padding:40px;text-align:center;">
                <h3>Пока отзывов нет</h3>
                <p>Они появятся после модерации</p>
            </div>

        <?php endif; ?>

    </div>
</section>

   
<section class="contact-section">
    <div class="container">
        <div class="contact-wrapper">
            <div class="contact-info">
                <h2 class="contact-title">СВЯЖИТЕСЬ<br>С НАМИ</h2>
                <p class="contact-text">
                    Оставьте заявку, и наш специалист свяжется с вами для консультации и подбора оборудования.
                </p>
                <ul class="contact-details">
                    <li>
                        <span class="contact-icon">📞</span>
                        <span><?= htmlspecialchars($siteSettings['contacts']['phone']) ?></span>
                    </li>
                    <li>
                        <span class="contact-icon">📞</span>
                        <span> +375 (33) 680-07-07</span>
                    </li>

                    <li>
                        <span class="contact-icon">📧</span>
                        <span><?= htmlspecialchars($siteSettings['contacts']['email']) ?></span>
                    </li>
                    <li>
                        <span class="contact-icon">📍</span>
                        <span><?= htmlspecialchars($siteSettings['contacts']['address']) ?></span>
                    </li>
                         <li>
                        <span class="contact-icon">📍</span>
                        <span>г. Минск, ул. Толбухина, д.2</span>
                    </li>
                </ul>
            </div>
            
            <div class="contact-form-card">
                <form class="contact-form" method="POST">
                    <div class="form-group">
                        <label>Ваше имя</label>
                        <input type="text" name="name" placeholder="Иван Иванов" required>
                    </div>
                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="tel" name="phone" placeholder="+375 (__) ___-__-__" required>
                    </div>
                    <div class="form-group">
                        <label>Сообщение</label>
                        <textarea name="message" rows="4" placeholder="Опишите ваш запрос..."></textarea>
                    </div>
                    <label class="form-consent form-consent--dark">
                        <input type="checkbox" class="form-consent__check" required>
                        <span>
                            Отправляя запрос, я соглашаюсь с
                            <a href="<?= htmlspecialchars(app_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>">правилами обработки персональных данных</a>.
                        </span>
                    </label>
                    <input type="hidden" name="form_type" value="Свяжитесь с нами">
                    <button type="submit" class="contact-btn">ОТПРАВИТЬ ЗАЯВКУ</button>
                </form>
            </div>
        </div>
    </div>
</section>
</main>

<?php if ($form_success || !empty($form_error)): ?>
<div id="notification-modal" class="modal active">
    <div class="modal-content <?= $form_success ? 'success' : 'error' ?>">
        <span class="modal-close" onclick="this.parentElement.parentElement.classList.remove('active')">&times;</span>
        <div class="modal-icon"><?= $form_success ? '✅' : '❌' ?></div>
        <h3 class="modal-title"><?= $form_success ? 'Заявка отправлена!' : 'Ошибка!' ?></h3>
        <p class="modal-message">
            <?= $form_success ? 'Ваша заявка отправлена. Мы свяжемся с вами.' : htmlspecialchars($form_error) ?>
        </p>
        <button class="modal-btn" onclick="this.parentElement.parentElement.classList.remove('active')">Хорошо</button>
    </div>
</div>
<?php else: ?>
<div id="notification-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div class="modal-icon">✅</div>
        <h3 class="modal-title">Успешно!</h3>
        <p class="modal-message">Ваша заявка отправлена. Мы свяжемся с вами.</p>
        <button class="modal-btn">Хорошо</button>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>


<script>
(function(w,d,u){
    var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
    var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
})(window,document,'https://cdn-ru.bitrix24.by/b15313854/crm/site_button/loader_2_el7etg.js');
</script>
    
</body>
</html>