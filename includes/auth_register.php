<?php
require_once __DIR__ . '/auth_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

$email = app_normalize_email((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$name = trim((string)($_POST['name'] ?? ''));

if (!app_is_email($email)) {
    app_json(['success' => false, 'message' => 'Введите корректный e-mail'], 422);
}
if (app_strlen($password) < 8) {
    app_json(['success' => false, 'message' => 'Пароль должен быть минимум 8 символов'], 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'user';
$login = $email;

// Ensure migrations ran: columns may not exist yet; fail clearly.
// (We keep using existing columns only: login/email/password/role are in dump.)
$stmt = $mysqli->prepare("SELECT id FROM `user` WHERE login = ? OR email = ? LIMIT 1");
$stmt->bind_param('ss', $login, $email);
$stmt->execute();
$res = $stmt->get_result();
$exists = $res && $res->num_rows > 0;
$stmt->close();
if ($exists) {
    app_json(['success' => false, 'message' => 'Пользователь с таким e-mail уже существует'], 409);
}

$stmt = $mysqli->prepare("INSERT INTO `user` (`login`, `password`, `role`, `email`, `name`, `created_at`) VALUES (?, ?, ?, ?, ?, NOW())");
if ($stmt === false) {
    // fallback for DB before migration (without name/created_at)
    $stmt2 = $mysqli->prepare("INSERT INTO `user` (`login`, `password`, `role`, `email`) VALUES (?, ?, ?, ?)");
    if (!$stmt2) {
        app_json(['success' => false, 'message' => 'Ошибка БД: не выполнены миграции'], 500);
    }
    $stmt2->bind_param('ssss', $login, $hash, $role, $email);
    $ok = $stmt2->execute();
    $uid = (int)$stmt2->insert_id;
    $stmt2->close();
    if (!$ok) app_json(['success' => false, 'message' => 'Ошибка регистрации'], 500);
    app_auth_login(['id' => $uid, 'login' => $login, 'role' => $role]);
    app_json(['success' => true, 'message' => 'Регистрация выполнена']);
}

$stmt->bind_param('sssss', $login, $hash, $role, $email, $name);
$ok = $stmt->execute();
$uid = (int)$stmt->insert_id;
$stmt->close();
if (!$ok) {
    app_json(['success' => false, 'message' => 'Ошибка регистрации'], 500);
}

app_auth_login(['id' => $uid, 'login' => $login, 'role' => $role]);
app_json(['success' => true, 'message' => 'Регистрация выполнена']);

