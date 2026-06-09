<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site_settings.php';

function admin_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '/admin/index.php';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base === '') {
            $base = '/admin';
        }
    }

    $path = ltrim($path, '/');
    if ($path === '') {
        return $base . '/';
    }

    return $base . '/' . $path;
}

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
        <link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
        <link rel="stylesheet" href="<?= htmlspecialchars(app_url('admin/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js" defer></script>
        <script src="<?= htmlspecialchars(app_url('js/phone-mask.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    </head>
    <body class="admin-page">
    <main class="main">
    <div class="container wrap">
        <div class="top">
            <h1><?= htmlspecialchars($title) ?></h1>
            <form method="post">
                <button type="submit" name="admin_logout" value="1" class="danger">Выйти</button>
            </form>
        </div>
        <div class="nav">
            <a href="<?= htmlspecialchars(admin_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Главная админ.панели</a>
            <a href="<?= htmlspecialchars(admin_url('medicators.php'), ENT_QUOTES, 'UTF-8') ?>">Медикаторы</a>
            <a href="<?= htmlspecialchars(admin_url('filters.php'), ENT_QUOTES, 'UTF-8') ?>">Фильтры / Субфильтры</a>
            <a href="<?= htmlspecialchars(admin_url('settings.php'), ENT_QUOTES, 'UTF-8') ?>">Данные на сайте</a>
            <a href="<?= htmlspecialchars(admin_url('orders.php'), ENT_QUOTES, 'UTF-8') ?>">Заказы</a>
            <a href="<?= htmlspecialchars(admin_url('users.php'), ENT_QUOTES, 'UTF-8') ?>">Пользователи</a>
            <a href="<?= htmlspecialchars(admin_url('promo_codes.php'), ENT_QUOTES, 'UTF-8') ?>">Промокоды</a>
            <a href="<?= htmlspecialchars(admin_url('reviews.php'), ENT_QUOTES, 'UTF-8') ?>">Отзывы</a>
            <a href="<?= htmlspecialchars(admin_url('reports.php'), ENT_QUOTES, 'UTF-8') ?>">Отчеты</a>
        </div>
    <?php
}

function admin_page_end()
{
    echo '</div></main></body></html>';
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

