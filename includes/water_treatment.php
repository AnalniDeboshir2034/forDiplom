<?php

function get_water_treatment_path()
{
    return __DIR__ . '/../storage/water-treatment.json';
}

function slugify_ru_text($text)
{
    $text = (string)$text;
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $text = trim($text);
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim((string)$text, '-');

    return $text !== '' ? $text : 'water-treatment';
}

function load_water_treatment_product()
{
    $path = get_water_treatment_path();
    if (!file_exists($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded)) {
        return null;
    }

    $entry = reset($decoded);
    if (!is_array($entry)) {
        return null;
    }

    $name = trim((string)($entry['Имя'] ?? 'Узел водоподготовки'));
    $slug = slugify_ru_text($name);

    $mainImg = (string)($entry['img'] ?? '');
    if ($mainImg !== '' && strpos($mainImg, 'product/') === 0) {
        $mainImg = 'products/' . substr($mainImg, strlen('product/'));
    }

    return [
        'type' => 'water-treatment',
        'id' => 0,
        'name' => $name,
        'slug' => $slug,
        'filtr' => $name,
        'opis' => (string)($entry['описание'] ?? ''),
        'main_img' => $mainImg,
        'table_rows' => [
            ['label' => 'температура воздуха, °С', 'value' => (string)($entry['температура воздуха, °С'] ?? '')],
            ['label' => 'относительная влажность при 20° С', 'value' => (string)($entry['относительная влажность при 20° С'] ?? '')],
            ['label' => 'давление воды, устанавливаемое регулятором, кгс/см', 'value' => ''],
            ['label' => 'при работе с медикатором', 'value' => (string)($entry['при работе с медикатором'] ?? '')],
            ['label' => 'при работе без медикатора', 'value' => (string)($entry['при работе без медикатора'] ?? '')],
            ['label' => 'габаритные размеры системы водоподготовки, мм, не более', 'value' => (string)($entry['габаритные размеры системы водоподготовки, мм, не более'] ?? '')],
        ],
    ];
}

