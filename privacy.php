<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/site_settings.php';
require_once __DIR__ . '/includes/seo.php';
$siteSettings = load_site_settings();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <base href="<?= htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= htmlspecialchars(app_url('products/favicon.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <?php seo_render_meta([
        'title' => 'Политика обработки персональных данных | Medikator.ru',
        'description' => 'Политика обработки персональных данных сайта Medikator.ru.',
        'canonical' => seo_canonical_url('/privacy'),
        'image' => app_url('products/icon.png'),
    ]); ?>
    <link rel="stylesheet" href="css/style.css">
    <meta name="yandex-verification" content="94250c2328fa6f0f" />
    <link rel="stylesheet" href="css/privacy.css">
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

    <main class="main privacy-page">
        <section class="privacy-hero">
            <div class="container">
                <div class="privacy-hero__card">
                    <p class="privacy-hero__badge">Юридическая информация</p>
                    <h1 class="privacy-hero__title">
                        Политика обработки <span class="gradient-text">персональных данных</span>
                    </h1>
                    <p class="privacy-hero__subtitle">
                        Этот документ описывает, какие данные мы получаем, зачем их используем
                        и как защищаем информацию пользователей сайта.
                    </p>
                </div>
            </div>
        </section>

        <section class="privacy-content">
            <div class="container">
                <div class="privacy-doc">
                    <section class="privacy-block">
                        <h2>1. Общие положения</h2>
                        <p>
                            Настоящая Политика определяет порядок обработки и защиты персональных данных
                            пользователей сайта <strong>Medikator.ru</strong>.
                        </p>
                        <p>
                            Оператор обрабатывает данные в соответствии с требованиями законодательства РФ,
                            включая Федеральный закон от 27.07.2006 N 152-ФЗ "О персональных данных".
                        </p>
                    </section>

                    <section class="privacy-block">
                        <h2>2. Какие данные мы можем получать</h2>
                        <ul>
                            <li>Имя пользователя.</li>
                            <li>Номер телефона.</li>
                            <li>Адрес электронной почты (если указан).</li>
                            <li>Содержание сообщения, отправленного через форму.</li>
                        </ul>
                    </section>

                    <section class="privacy-block">
                        <h2>3. Цели обработки персональных данных</h2>
                        <ul>
                            <li>Обратная связь с пользователем по заявке.</li>
                            <li>Подготовка коммерческого предложения и консультации.</li>
                            <li>Информирование о статусе обращения.</li>
                            <li>Улучшение качества сервиса и работы сайта.</li>
                        </ul>
                    </section>

                    <section class="privacy-block">
                        <h2>4. Правовые основания обработки</h2>
                        <p>
                            Основанием обработки является добровольное предоставление данных пользователем
                            через формы на сайте и выражение согласия с настоящей Политикой.
                        </p>
                    </section>

                    <section class="privacy-block">
                        <h2>5. Передача и хранение данных</h2>
                        <p>
                            Персональные данные не передаются третьим лицам, за исключением случаев,
                            предусмотренных законодательством РФ, либо когда такая передача необходима
                            для исполнения обращения пользователя.
                        </p>
                        <p>
                            Оператор принимает необходимые организационные и технические меры для защиты
                            данных от неправомерного доступа, изменения, раскрытия или уничтожения.
                        </p>
                    </section>

                    <section class="privacy-block">
                        <h2>6. Права пользователя</h2>
                        <ul>
                            <li>Получать информацию об обработке своих персональных данных.</li>
                            <li>Требовать уточнения, блокирования или удаления данных.</li>
                            <li>Отозвать согласие на обработку персональных данных.</li>
                        </ul>
                    </section>

                    <section class="privacy-block">
                        <h2>7. Контакты оператора</h2>
                        <p>
                            По вопросам обработки персональных данных вы можете связаться с нами:
                        </p>
                        <div class="privacy-contacts">
                            <p><strong>Телефон:</strong> <?= htmlspecialchars($siteSettings['contacts']['phone']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($siteSettings['contacts']['email']) ?></p>
                            <p><strong>Адрес:</strong> <?= htmlspecialchars($siteSettings['contacts']['address']) ?></p>
                        </div>
                    </section>

                    <p class="privacy-updated">
                        Актуальная версия Политики размещена по адресу:
                        <a href="<?= htmlspecialchars(app_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>">medikator.ru/privacy</a>
                    </p>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
