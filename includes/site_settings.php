<?php

function get_site_settings_path()
{
    return __DIR__ . '/includes/storage/site_settings.json';
}

function get_default_site_settings()
{
    return [
        'contacts' => [
            'phone' => '+7 (800) 123-45-67',
            'email' => 'info@medicator.ru',
            'address' => 'г. Москва, ул. Примерная, 1',
            'work_hours' => 'Пн-Пт: 9:00 — 18:00',
        ],
        'header' => [
            'brand_name' => '7 company',
            'lead_button_text' => 'Оставить заявку',
        ],
        'footer' => [
            'company_description' => 'Профессиональные медикаторы-дозаторы',
            'copyright' => '© 2026 7company. Все права защищены.',
        ],
        'index' => [
            'hero_title_top' => 'МЕДИКАТОРЫ',
            'hero_title_bottom' => 'ДЛЯ ХОЗЯЙСТВ',
            'hero_text' => 'Точное дозирование препаратов и добавок в систему водоснабжения. Надёжные решения для птицеводства, свиноводства и животноводства.',
        ],
        'containers' => [
            'index_cta_title' => 'РАССЧИТАЕМ СТОИМОСТЬ',
            'index_cta_subtitle' => 'ПОД ВАШ ЗАПРОС',
            'contacts_intro_title' => 'СВЯЖИТЕСЬ',
            'contacts_intro_subtitle' => 'С НАМИ',
        ],
    ];
}

function load_site_settings()
{
    $defaults = get_default_site_settings();
    $path = get_site_settings_path();

    if (!file_exists($path)) {
        return $defaults;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    return array_replace_recursive($defaults, $decoded);
}

function save_site_settings(array $settings)
{
    $path = get_site_settings_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }

    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json) !== false;
}

