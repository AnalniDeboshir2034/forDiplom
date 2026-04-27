<?php
require_once __DIR__ . '/includes/config.php';
require_once 'includes/site_settings.php';
require_once __DIR__ . '/includes/seo.php';
$siteSettings = load_site_settings();
$BITRIX_WEBHOOK = 'https://k7s.bitrix24.by/rest/25370/o4k69x5rthf0grzi/crm.lead.add.json';

$form_success = false;
$form_error = '';
$form_data = [];

function is_valid_phone_prefix($phone)
{
    $normalized = preg_replace('/[\s\-\(\)]/', '', (string)$phone);
    if ($normalized === '') {
        return false;
    }

    return preg_match('/^(\+\d{6,15}|\d{6,15})$/', $normalized) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));
    
    $form_data = compact('name',  'phone', 'message');
    
    if (empty($name) || empty($phone)) {
        $form_error = 'Пожалуйста, заполните имя и телефон';
    } elseif (!is_valid_phone_prefix($phone)) {
        $form_error = 'Номер телефона невалидный';
    } else {
        $leadData = [
            'fields' => [
                'TITLE' => 'Заявка с сайта Medikator.ru',
                'NAME' => $name,
                'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
                'SOURCE_ID' => 'WEB',
                'SOURCE_DESCRIPTION' => 'Контактная форма сайта',
                'ASSIGNED_BY_ID' => 1,
                'STATUS_ID' => 'NEW',
                'COMMENTS' => "Имя: $name\nТелефон: $phone\nСообщение: $message\n\nДата: " . date('d.m.Y H:i:s'),
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
            date('Y-m-d H:i:s') . " | HTTP: $httpCode\n" .
            "Ответ: " . print_r($result, true) . "\n\n",
            FILE_APPEND
        );
        
        if (isset($result['result'])) {
            $form_success = true;
            $form_data = [];
        } else {
            $form_error = 'Ошибка отправки. Пожалуйста, позвоните нам.';
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
        'title' => 'Контакты | Medikator.ru',
        'description' => 'Контакты Medikator.ru: телефоны, e-mail, адреса в России и Беларуси, форма обратной связи и карта проезда.',
        'canonical' => seo_canonical_url('/contacts'),
        'image' => app_url('products/icon.png'),
    ]); ?>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/contacts.css?v=<?= time() ?>">
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <?php seo_render_organization_jsonld($siteSettings); ?>

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
   <?php  require_once 'includes/header.php';?>

    <main class="main">
        <section class="contacts-info-section">
            <div class="container">
                <div class="contacts-info-card">
                    <h1 class="contacts-info-title">КОНТАКТЫ</h1>
                    <p class="contacts-info-subtitle">Свяжитесь с нами любым удобным способом — мы всегда на связи</p>
                    
                    <div class="contacts-info-list">
                        <div class="contacts-info-item">
                            <div class="contacts-info-item__title">Телефон</div>
                            <div class="contacts-info-item__value"><?= htmlspecialchars($siteSettings['contacts']['phone']) ?></div>
                            <div class="contacts-info-item__desc">Вызов по России</div>
                            <div class="contacts-info-item__value">+375 (33) 680-07-07</div>
                            <div class="contacts-info-item__desc">Вызов по Беларуси</div>
                        </div>
                        
                        <div class="contacts-info-item">
                            <div class="contacts-info-item__title">E-mail</div>
                            <div class="contacts-info-item__value"><?= htmlspecialchars($siteSettings['contacts']['email']) ?></div>
                            <div class="contacts-info-item__desc">Ответим в течение часа</div>
                        </div>
                        
                        <div class="contacts-info-item">
                            <div class="contacts-info-item__title">Адрес</div>
                            <div class="contacts-info-item__value"><?= htmlspecialchars($siteSettings['contacts']['address']) ?></div>
                            <div class="contacts-info-item__desc">Офис и склад</div>
                            <div class="contacts-info-item__value">г.Минск, ул. Толбухина, д.2</div>
                            <div class="contacts-info-item__desc">Офис и склад</div>
                        </div>
                        
                        <div class="contacts-info-item">
                            <div class="contacts-info-item__title">Режим работы</div>
                            <div class="contacts-info-item__value"><?= htmlspecialchars($siteSettings['contacts']['work_hours']) ?></div>
                            <div class="contacts-info-item__desc">Сб-Вс: выходной</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact-form-section">
            <div class="container">
                <div class="contact-form-card">
                    <div class="contact-form-left">
                        <h2 class="contact-form-title"><?= htmlspecialchars($siteSettings['containers']['contacts_intro_title']) ?><br><span class="gradient-text"><?= htmlspecialchars($siteSettings['containers']['contacts_intro_subtitle']) ?></span></h2>
                        <p class="contact-form-text">
                            Оставьте заявку, и наш специалист свяжется с вами для консультации и подбора оборудования.
                        </p>
                        <div class="contact-form-contacts">
                            <p>📞 <?= htmlspecialchars($siteSettings['contacts']['phone']) ?></p>
                            <p>📞 +375 (33) 680-07-07</p>
                            <p>✉️ <?= htmlspecialchars($siteSettings['contacts']['email']) ?></p>
                            <p>📍 <?= htmlspecialchars($siteSettings['contacts']['address']) ?></p>
                            <p>📍 г.Минск, ул. Толбухина, д.2</p>
                        </div>
                    </div>
                    
                    <div class="contact-form-right">
                        <?php if ($form_success): ?>
                            <div class="form-success">
                                ✅ Спасибо! Мы свяжемся с вами в ближайшее время.
                            </div>
                        <?php elseif ($form_error): ?>
                            <div class="form-error">
                                ❌ <?= $form_error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form class="contact-form-fields" id="contact-form" method="POST">
                            <div class="form-field">
                                <label>Ваше имя *</label>
                                <input type="text" name="name" placeholder="Иван Иванов" required value="<?= htmlspecialchars($form_data['name'] ?? '') ?>">
                            </div>
                            <div class="form-field">
                                <label>Телефон *</label>
                                <input type="tel" name="phone" placeholder="(___) ___-__-__" required value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>">
                            </div>
                        
                            <div class="form-field">
                                <label>Сообщение</label>
                                <textarea name="message" rows="4" placeholder="Опишите ваш запрос..."><?= htmlspecialchars($form_data['message'] ?? '') ?></textarea>
                            </div>
                            <label class="form-consent">
                                <input type="checkbox" class="form-consent__check" required>
                                <span>
                                    Отправляя запрос, я соглашаюсь с
                                    <a href="<?= htmlspecialchars(app_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>">правилами обработки персональных данных</a>.
                                </span>
                            </label>
                            <button type="submit" class="submit-btn">ОТПРАВИТЬ ЗАЯВКУ →</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    <section class="map">
    <div class="container">
        <h2 class="section-title">Мы на карте</h2>
        <div class="map-container">
            <div id="map"></div>
        </div>
        <div style="display: flex; justify-content: center; gap: 30px; margin-top: 15px; color: #64748b; flex-wrap: wrap;">
            <p>📍 <strong>Россия, Смоленск:</strong> ул. 2-я Вяземская, д.4</p>
            <p>📍 <strong>Беларусь, Минск:</strong> ул. Толбухина, д.2</p>
        </div>
    </div>
</section>

        <section class="delivery-section">
            <div class="container">
                <div class="delivery-card">
                    <h2 class="delivery-title">УСЛОВИЯ <span class="gradient-text">ДОСТАВКИ</span></h2>
                    <p class="delivery-subtitle">Доставляем по всей России быстро и надёжно</p>
                    
                    <div class="delivery-grid">
                        <div class="delivery-item">
                            <h3 class="delivery-item__title">Доставка по России</h3>
                            <p class="delivery-item__desc">Транспортными компаниями СДЭК, Деловые Линии, ПЭК. Срок — от 3 до 10 рабочих дней в зависимости от региона.</p>
                        </div>
                        
                        <div class="delivery-item">
                            <h3 class="delivery-item__title">Самовывоз</h3>
                            <p class="delivery-item__desc">Вы можете забрать заказ с нашего склада в Москве по предварительной договорённости.</p>
                        </div>
                        
                        <div class="delivery-item">
                            <h3 class="delivery-item__title">Гарантия сохранности</h3>
                            <p class="delivery-item__desc">Все товары надёжно упаковываются. В случае повреждения при доставке — бесплатная замена.</p>
                        </div>
                        
                        <div class="delivery-item">
                            <h3 class="delivery-item__title">Бесплатная доставка</h3>
                            <p class="delivery-item__desc">При заказе от 150 000 ₽ доставка по России за наш счёт.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once 'includes/footer.php'; ?>

 <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    function initMap() {
        if (typeof ymaps === 'undefined') {
            console.log('Yandex Maps API не загружен');
            showFallbackMap();
            return;
        }
        
        try {
            ymaps.ready(function() {
                const mapElement = document.getElementById('map');
                if (!mapElement) return;
                
                // Координаты Смоленска (ул. 2-я Вяземская, д.4)
                const smolenskCoords = [54.782952, 32.026853];
                // Координаты Минска (ул. Толбухина, д.2)
                const minskCoords = [53.917792, 27.554603];
                
                // Центр карты между Смоленском и Минском
                const centerLat = (smolenskCoords[0] + minskCoords[0]) / 2;
                const centerLon = (smolenskCoords[1] + minskCoords[1]) / 2;
                
                const map = new ymaps.Map('map', {
                    center: [centerLat, centerLon],
                    zoom: 7,
                    controls: ['zoomControl', 'fullscreenControl']
                });
                
                // Маркер Смоленск
                const smolenskMarker = new ymaps.Placemark(smolenskCoords, {
                    balloonContentHeader: 'Medikator.ru - Смоленск',
                    balloonContentBody: 'г. Смоленск, ул. 2-я Вяземская, д.4<br>Представительство<br>Режим работы: Пн-Пт 9:00-18:00',
                    balloonContentFooter: '📞 <?= htmlspecialchars($siteSettings['contacts']['phone']) ?>'
                }, {
                    preset: 'islands#blueDotIcon',
                    iconColor: '#0066cc'
                });
                
                // Маркер Минск
                const minskMarker = new ymaps.Placemark(minskCoords, {
                    balloonContentHeader: 'Medikator.ru - Минск',
                    balloonContentBody: 'г. Минск, ул. Толбухина, д.2<br>Офис<br>Режим работы: Пн-Пт 9:00-18:00',
                    balloonContentFooter: '📞 +375 (33) 680-07-07?>'
                }, {
                    preset: 'islands#orangeDotIcon',
                    iconColor: '#f97316'
                });
                
                map.geoObjects.add(smolenskMarker);
                map.geoObjects.add(minskMarker);
                
                // Автоматически подстроить границы карты, чтобы оба маркера были видны
                const bounds = [
                    [Math.min(smolenskCoords[0], minskCoords[0]), Math.min(smolenskCoords[1], minskCoords[1])],
                    [Math.max(smolenskCoords[0], minskCoords[0]), Math.max(smolenskCoords[1], minskCoords[1])]
                ];
                map.setBounds(bounds, {
                    checkZoomRange: true,
                    zoomMargin: 50
                });
            });
        } catch (error) {
            console.log('Ошибка при создании карты:', error);
            showFallbackMap();
        }
    }

    function showFallbackMap() {
        const mapElement = document.getElementById('map');
        if (mapElement) {
            mapElement.innerHTML = `
                <div style="width:100%;height:100%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:20px;text-align:center;border-radius:12px;">
                    <div style="font-size:48px;margin-bottom:16px;">📍</div>
                    <p style="font-size:16px;margin-bottom:10px;color:#1e293b;"><strong>Россия, Смоленск:</strong> ул. 2-я Вяземская, д.4</p>
                    <p style="font-size:16px;margin-bottom:15px;color:#1e293b;"><strong>Беларусь, Минск:</strong> ул. Толбухина, д.2</p>
                    <a href="https://yandex.ru/maps/?text=Смоленск,+2-я+Вяземская+улица,+д.4" 
                       target="_blank" 
                       style="color:#f97316;text-decoration:underline;margin-right:15px;">
                        Смоленск на карте
                    </a>
                    <a href="https://yandex.ru/maps/?text=Минск,+ул.+Толбухина,+д.2" 
                       target="_blank" 
                       style="color:#f97316;text-decoration:underline;">
                        Минск на карте
                    </a>
                </div>
            `;
        }
    }
    
    setTimeout(initMap, 500);
});
</script>
    <script>
        (function(w,d,u){
                var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
                var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
        })(window,document,'https://cdn-ru.bitrix24.by/b15313854/crm/site_button/loader_2_el7etg.js');
</script>
</body>
</html>