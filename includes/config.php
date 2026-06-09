<?php 
$host = 'localhost';
$user = 'medicator';
$pass = 'medicator';
$db_name = 'medicator';

$__scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
$__scriptDirWeb = str_replace('\\', '/', dirname($__scriptName));
$__scriptDirWeb = $__scriptDirWeb === '/' ? '' : rtrim($__scriptDirWeb, '/');

// Resolve project base URL even when current script is in /includes or /admin.
$__projectRootAbs = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
$__scriptFileAbs = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_FILENAME']) : '';
$__scriptDirAbs = $__scriptFileAbs !== '' ? str_replace('\\', '/', dirname($__scriptFileAbs)) : '';
$__baseDir = $__scriptDirWeb;
if ($__scriptDirAbs !== '' && str_starts_with($__scriptDirAbs, $__projectRootAbs)) {
    $__relative = trim(substr($__scriptDirAbs, strlen($__projectRootAbs)), '/');
    if ($__relative !== '') {
        $suffix = '/' . str_replace('\\', '/', $__relative);
        if (str_ends_with($__baseDir, $suffix)) {
            $__baseDir = substr($__baseDir, 0, -strlen($suffix));
        }
    }
}
$__baseDir = ($__baseDir === '/' || $__baseDir === false) ? '' : rtrim((string)$__baseDir, '/');
if (!defined('APP_BASE')) {
    define('APP_BASE', $__baseDir); // e.g. "" or "/MedicatorRu"
}
if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $base = APP_BASE;
        if ($path === '') {
            return ($base === '' ? '/' : $base . '/');
        }
        return ($base === '' ? '/' . $path : $base . '/' . $path);
    }
}
if (!function_exists('app_product_url')) {
    function app_product_url(string $slug): string
    {
        return app_url('product.php?slug=' . rawurlencode($slug));
    }
}

if (!function_exists('app_env')) {
    function app_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        $trimmed = trim((string)$value);
        return $trimmed === '' ? $default : $trimmed;
    }
}

if (!function_exists('app_site_url')) {
    function app_site_url(): string
    {
        $fromEnv = app_env('APP_URL');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $isHttps ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        return $scheme . '://' . $host . APP_BASE;
    }
}

if (!function_exists('app_site_host')) {
    function app_site_host(): string
    {
        $host = parse_url(app_site_url(), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}

if (!function_exists('app_site_name')) {
    function app_site_name(): string
    {
        return (string)(app_env('APP_SITE_NAME', 'Medikator') ?? 'Medikator');
    }
}

if (!function_exists('app_mail_config')) {
    function app_mail_config(): array
    {
        return [
            'transport'      => 'smtp',
            'host'           => 'smtp.hoster.by',     // официальный SMTP-шлюз
            'port'           => 465,                   // порт с SSL (как в документации)
            'secure'         => 'ssl',                 // SSL-шифрование
            'username'       => 'info@diplomkbip.xyz',
            'password'       => '210203MaKs_',
            'from_address'   => 'info@diplomkbip.xyz',
            'from_name'      => 'DiplomKbip',
            'reply_to'       => 'info@diplomkbip.xyz',
            'timeout'        => 30,
        ];
    }
}
// Prevent uncaught mysqli exceptions from turning API responses into fatal HTML.
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($host, $user, $pass, $db_name);

if (!$mysqli || $mysqli->connect_error) {
    $dbErrorMessage = 'Ошибка подключения к БД. Проверь, что в XAMPP запущен MySQL и существует база "' . $db_name . '".';
    $isApiRequest = isset($_SERVER['SCRIPT_NAME']) && strpos((string)$_SERVER['SCRIPT_NAME'], '/includes/') !== false;
    if ($isApiRequest) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $dbErrorMessage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    die("❌ " . $dbErrorMessage .
        "<br>Проверь:<br>" .
        "Хост: $host<br>" .
        "БД: $db_name<br>" .
        "Пользователь: $user");
}

$mysqli->set_charset("utf8mb4");

$pdo = null;

$labelsFile = __DIR__ . '/labels.php';
if (is_file($labelsFile)) {
    require_once $labelsFile;
}

if (!function_exists('app_order_status_label')) {
    function app_order_status_label(string $status): string
    {
        $map = ['new' => 'Новый', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'completed' => 'Завершён'];
        return $map[$status] ?? $status;
    }
}
if (!function_exists('app_order_status_labels')) {
    function app_order_status_labels(): array
    {
        return ['new' => 'Новый', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'completed' => 'Завершён'];
    }
}
if (!function_exists('app_delivery_type_label')) {
    function app_delivery_type_label(string $type): string
    {
        $map = ['courier' => 'Курьером', 'pickup' => 'Самовывоз'];
        return $map[$type] ?? $type;
    }
}
if (!function_exists('app_payment_type_label')) {
    function app_payment_type_label(string $type): string
    {
        $map = ['invoice' => 'Счёт на email', 'card_on_delivery' => 'Картой при получении', 'erip' => 'ЕРИП'];
        return $map[$type] ?? $type;
    }
}
if (!function_exists('app_review_status_label')) {
    function app_review_status_label(string $status): string
    {
        $map = ['pending' => 'На модерации', 'approved' => 'Одобрен', 'rejected' => 'Отклонён'];
        return $map[$status] ?? $status;
    }
}
if (!function_exists('app_promo_type_label')) {
    function app_promo_type_label(string $type): string
    {
        $map = ['percent' => 'Процент', 'fixed' => 'Фиксированная сумма'];
        return $map[$type] ?? $type;
    }
}
if (!function_exists('app_user_role_label')) {
    function app_user_role_label(string $role): string
    {
        $map = ['admin' => 'Админ', 'user' => 'Пользователь'];
        return $map[$role] ?? $role;
    }
}
if (!function_exists('app_account_type_label')) {
    function app_account_type_label(string $type): string
    {
        $map = ['individual' => 'Физлицо', 'legal' => 'Юрлицо'];
        return $map[$type] ?? $type;
    }
}
if (!function_exists('app_category_label')) {
    function app_category_label($mysqli, string $slug): string
    {
        $slug = trim($slug);
        return $slug === '' ? 'Без категории' : $slug;
    }
}
if (!function_exists('app_format_datetime')) {
    function app_format_datetime(?string $value): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? $value : date('d.m.Y H:i', $ts);
    }
}
if (!function_exists('app_format_date')) {
    function app_format_date(?string $value): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? $value : date('d.m.Y', $ts);
    }
}
if (!function_exists('app_localize_order_row')) {
    function app_localize_order_row(array $order): array
    {
        $order['status_label'] = app_order_status_label((string)($order['status'] ?? ''));
        $order['delivery_type_label'] = app_delivery_type_label((string)($order['delivery_type'] ?? ''));
        $order['payment_type_label'] = app_payment_type_label((string)($order['payment_type'] ?? ''));
        if (!empty($order['created_at'])) {
            $order['created_at_label'] = app_format_datetime((string)$order['created_at']);
        }
        return $order;
    }
}
