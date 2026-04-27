<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site_settings.php';

function admin_req($key)
{
    return trim($_POST[$key] ?? '');
}

if (isset($_POST['admin_logout'])) {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_login']);
    header('Location: index.php');
    exit;
}

if (isset($_POST['admin_login'])) {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    require_once __DIR__ . '/../includes/auth_lib.php';

    $stmt = $mysqli->prepare("SELECT id, login, password, role FROM `user` WHERE login = ? OR email = ? LIMIT 1");
    $stmt->bind_param('ss', $login, $login);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($user && (($user['role'] ?? '') === 'admin') && app_verify_password_and_upgrade($mysqli, (int)$user['id'], $password, (string)$user['password'])) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_login'] = $user['login'] ?? $login;
        header('Location: index.php');
        exit;
    }
    $_SESSION['admin_login_error'] = 'Неверный логин или пароль';
    header('Location: index.php');
    exit;
}

function admin_require_auth()
{
    if (empty($_SESSION['is_admin'])) {
        header('Location: index.php');
        exit;
    }
}

function admin_page_start($title)
{
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            body { font-family: Inter, Arial, sans-serif; background: #f5f7fb; margin: 0; color: #1f2937; }
            .wrap { max-width: 1280px; margin: 0 auto; padding: 20px; }
            .top { display: flex; justify-content: space-between; align-items: center; gap: 10px; background:#fff; padding:14px 16px; border-radius: 14px; border:1px solid #e5e7eb; position: sticky; top: 8px; z-index: 11; }
            .card { background: #fff; border-radius: 14px; padding: 18px; margin: 16px 0; border: 1px solid #e5e7eb; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); }
            .grid { display: grid; grid-template-columns: repeat(2, minmax(260px,1fr)); gap: 12px; }
            label { display:block; font-size:13px; margin-bottom: 4px; color:#475569; font-weight:600; }
            input, textarea, select { width: 100%; padding: 10px 12px; margin: 4px 0; box-sizing: border-box; border:1px solid #dbe1ea; border-radius: 10px; background:#fff; }
            input:focus, textarea:focus, select:focus { outline: none; border-color:#f97316; box-shadow:0 0 0 3px rgba(249,115,22,.15); }
            table { width: 100%; border-collapse: collapse; font-size: 14px; }
            td, th { border: 1px solid #edf0f5; padding: 8px; text-align: left; vertical-align: top; }
            th { background: #f8fafc; font-weight: 700; }
            button { padding: 9px 13px; border: 0; border-radius: 10px; background: #1f5cff; color: #fff; cursor: pointer; font-weight:600; }
            .danger { background: #c62828; }
            .muted { color: #666; font-size: 13px; }
            .nav { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
            .nav a { text-decoration: none; background: #fff; color: #1f5cff; border-radius: 999px; padding: 8px 12px; border: 1px solid #d6ddff; font-weight:600; }
            .nav a:hover { border-color:#1f5cff; }
            .msg { background: #e9fff2; border: 1px solid #c6f3da; color: #075f33; padding: 10px; border-radius: 10px; margin: 10px 0; }
            details > summary { list-style: none; padding: 8px 0; }
            details > summary::-webkit-details-marker { display:none; }
            @media (max-width: 900px){ .grid{grid-template-columns:1fr;} .top{position:static;} }
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="top">
            <h1><?= htmlspecialchars($title) ?></h1>
            <form method="post">
                <button type="submit" name="admin_logout" value="1" class="danger">Выйти</button>
            </form>
        </div>
        <div class="nav">
            <a href="/admin">Главная админки</a>
            <a href="/admin/medicators">Медикаторы</a>
            <a href="/admin/filters">Фильтры / Субфильтры</a>
            <a href="/admin/settings">JSON настройки</a>
            <a href="/admin/orders">Заказы</a>
            <a href="/admin/users">Пользователи</a>
            <a href="/admin/promo_codes">Промокоды</a>
            <a href="/admin/reviews">Отзывы</a>
            <a href="/admin/migrate">Миграции БД</a>
        </div>
    <?php
}

function admin_page_end()
{
    echo '</div></body></html>';
}

function slugify_ru_to_en($text, $fallback = 'item')
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    if ($text === '') {
        return $fallback;
    }

    // Transliteration Russian -> Latin (only letters; everything else handled below).
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $slug = strtr($text, $map);

    // Everything that's not latin letters/numbers becomes a hyphen.
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : $fallback;
}

function admin_sanitize_slug($slug, $fallback = 'item')
{
    // Always produce Latin-only slug to avoid URL/DB issues.
    return slugify_ru_to_en($slug, $fallback);
}

function admin_ensure_unique_slug($mysqli, $table, $slug, $excludeId = null)
{
    $slug = admin_sanitize_slug($slug, $table === 'medicator' ? 'product' : 'item');

    $base = $slug;
    $counter = 1;

    // Only allow known tables to prevent SQL injection via $table.
    if ($table === 'medicator') {
        $table = 'medicator';
    } elseif ($table === 'filter') {
        $table = 'filter';
    } elseif ($table === 'subfilter') {
        $table = 'subfilter';
    } else {
        throw new RuntimeException('Unknown table for slug uniqueness');
    }

    while (true) {
        if ($excludeId !== null) {
            $stmt = $mysqli->prepare("SELECT id FROM {$table} WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->bind_param('si', $slug, $excludeId);
        } else {
            $stmt = $mysqli->prepare("SELECT id FROM {$table} WHERE slug = ? LIMIT 1");
            $stmt->bind_param('s', $slug);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            return $slug;
        }

        $counter++;
        $slug = $base . '-' . $counter;
        if ($counter > 100) {
            return $slug;
        }
    }
}

