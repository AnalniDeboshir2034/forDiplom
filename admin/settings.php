<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settings_save') {
    $current = load_site_settings();
    $current['contacts']['phone'] = admin_req('contact_phone');
    $current['contacts']['email'] = admin_req('contact_email');
    $current['contacts']['address'] = admin_req('contact_address');
    $current['contacts']['work_hours'] = admin_req('contact_work_hours');
    $current['header']['brand_name'] = admin_req('header_brand_name');
    $current['header']['lead_button_text'] = admin_req('header_lead_button_text');
    $current['footer']['company_description'] = admin_req('footer_company_description');
    $current['footer']['copyright'] = admin_req('footer_copyright');
    $current['index']['hero_title_top'] = admin_req('hero_title_top');
    $current['index']['hero_title_bottom'] = admin_req('hero_title_bottom');
    $current['index']['hero_text'] = admin_req('hero_text');
    $current['containers']['index_cta_title'] = admin_req('index_cta_title');
    $current['containers']['index_cta_subtitle'] = admin_req('index_cta_subtitle');
    $current['containers']['contacts_intro_title'] = admin_req('contacts_intro_title');
    $current['containers']['contacts_intro_subtitle'] = admin_req('contacts_intro_subtitle');
    if (save_site_settings($current)) {
        $success = 'JSON настройки сохранены';
    } else {
        $error = 'Не удалось сохранить JSON. Проверь права на папку /storage.';
    }
}

$settings = load_site_settings();
admin_page_start('Админка: JSON настройки');
if ($success) {
    echo '<div class="msg">' . htmlspecialchars($success) . '</div>';
}
if ($error) {
    echo '<div class="msg" style="border-color:#f5c6cb;color:#721c24;background:#f8d7da;">' . htmlspecialchars($error) . '</div>';
}
?>
<div class="card">
    <h2>Настройки сайта (JSON)</h2>
    <p class="muted">
        Файл настроек: <code>storage/site_settings.json</code><br>
        Изменения применяются к `index.php`, `contacts.php`, `includes/header.php`, `includes/footer.php`.
    </p>
    <form method="post">
        <input type="hidden" name="action" value="settings_save">
        <h3>Контакты</h3>
        <div class="grid">
            <div>
                <label>Телефон</label>
                <input name="contact_phone" value="<?= htmlspecialchars($settings['contacts']['phone']) ?>" placeholder="+7 ...">
            </div>
            <div>
                <label>Email</label>
                <input name="contact_email" value="<?= htmlspecialchars($settings['contacts']['email']) ?>" placeholder="info@...">
            </div>
            <div>
                <label>Адрес</label>
                <input name="contact_address" value="<?= htmlspecialchars($settings['contacts']['address']) ?>" placeholder="г. ...">
            </div>
            <div>
                <label>Часы работы</label>
                <input name="contact_work_hours" value="<?= htmlspecialchars($settings['contacts']['work_hours']) ?>" placeholder="Пн-Пт ...">
            </div>
        </div>

        <h3>Шапка</h3>
        <div class="grid">
            <div>
                <label>Название бренда</label>
                <input name="header_brand_name" value="<?= htmlspecialchars($settings['header']['brand_name']) ?>" placeholder="7 company">
            </div>
            <div>
                <label>Текст кнопки в хедере</label>
                <input name="header_lead_button_text" value="<?= htmlspecialchars($settings['header']['lead_button_text']) ?>" placeholder="Оставить заявку">
            </div>
        </div>

        <h3>Подвал</h3>
        <div class="grid">
            <div>
                <label>Описание компании</label>
                <input name="footer_company_description" value="<?= htmlspecialchars($settings['footer']['company_description']) ?>" placeholder="...">
            </div>
            <div>
                <label>Copyright</label>
                <input name="footer_copyright" value="<?= htmlspecialchars($settings['footer']['copyright']) ?>" placeholder="© ...">
            </div>
        </div>

        <h3>Главная</h3>
        <div class="grid">
            <div>
                <label>Hero title (верх)</label>
                <input name="hero_title_top" value="<?= htmlspecialchars($settings['index']['hero_title_top']) ?>" placeholder="МЕДИКАТОРЫ">
            </div>
            <div>
                <label>Hero title (низ)</label>
                <input name="hero_title_bottom" value="<?= htmlspecialchars($settings['index']['hero_title_bottom']) ?>" placeholder="ДЛЯ ХОЗЯЙСТВ">
            </div>
            <div style="grid-column:1/-1;">
                <label>Текст hero</label>
                <textarea name="hero_text" placeholder="Точное дозирование ..."><?= htmlspecialchars($settings['index']['hero_text']) ?></textarea>
            </div>
        </div>

        <h3>Блоки CTA</h3>
        <div class="grid">
            <div>
                <label>Index CTA title</label>
                <input name="index_cta_title" value="<?= htmlspecialchars($settings['containers']['index_cta_title']) ?>" placeholder="РАССЧИТАЕМ ...">
            </div>
            <div>
                <label>Index CTA subtitle</label>
                <input name="index_cta_subtitle" value="<?= htmlspecialchars($settings['containers']['index_cta_subtitle']) ?>" placeholder="ПОД ВАШ ЗАПРОС ...">
            </div>
            <div>
                <label>Contacts intro title</label>
                <input name="contacts_intro_title" value="<?= htmlspecialchars($settings['containers']['contacts_intro_title']) ?>" placeholder="СВЯЖИТЕСЬ">
            </div>
            <div>
                <label>Contacts intro subtitle</label>
                <input name="contacts_intro_subtitle" value="<?= htmlspecialchars($settings['containers']['contacts_intro_subtitle']) ?>" placeholder="С НАМИ">
            </div>
        </div>

        <button>Сохранить JSON</button>
    </form>
</div>
<?php admin_page_end(); ?>

