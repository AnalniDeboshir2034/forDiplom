<?php 
$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'medicator';

$__scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
$__baseDir = str_replace('\\', '/', dirname($__scriptName));
$__baseDir = $__baseDir === '/' ? '' : rtrim($__baseDir, '/');
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

$mysqli = new mysqli($host, $user, $pass, $db_name);

if ($mysqli->connect_error) {
    die("❌ Ошибка подключения к БД: " . $mysqli->connect_error . 
        "<br>Проверь:<br>" .
        "Хост: $host<br>" .
        "БД: $db_name<br>" .
        "Пользователь: $user");
}

$mysqli->set_charset("utf8mb4");

$pdo = null;

?>