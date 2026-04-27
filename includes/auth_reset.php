<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($token === '') {
    app_json(['success' => false, 'message' => 'Нет токена'], 422);
}
if (app_strlen($password) < 8) {
    app_json(['success' => false, 'message' => 'Пароль должен быть минимум 8 символов'], 422);
}
if (!db_table_exists($mysqli, 'password_resets')) {
    app_json(['success' => false, 'message' => 'Сервис восстановления недоступен (нужны миграции)'], 500);
}

// Find a non-used, non-expired reset record by verifying token_hash.
$stmt = $mysqli->prepare("SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.used_at
                          FROM `password_resets` pr
                          WHERE pr.used_at IS NULL
                          ORDER BY pr.id DESC
                          LIMIT 50");
$stmt->execute();
$res = $stmt->get_result();
$match = null;
while ($res && ($row = $res->fetch_assoc())) {
    $exp = strtotime((string)$row['expires_at']);
    if ($exp !== false && $exp < time()) continue;
    if (password_verify($token, (string)$row['token_hash'])) {
        $match = $row;
        break;
    }
}
$stmt->close();

if (!$match) {
    app_json(['success' => false, 'message' => 'Ссылка недействительна или истекла'], 400);
}

$uid = (int)$match['user_id'];
$newHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("UPDATE `user` SET `password` = ? WHERE `id` = ? LIMIT 1");
$stmt->bind_param('si', $newHash, $uid);
$stmt->execute();
$stmt->close();

$resetId = (int)$match['id'];
$stmt = $mysqli->prepare("UPDATE `password_resets` SET `used_at` = NOW() WHERE `id` = ? LIMIT 1");
$stmt->bind_param('i', $resetId);
$stmt->execute();
$stmt->close();

app_json(['success' => true, 'message' => 'Пароль обновлён. Теперь можно войти.']);

