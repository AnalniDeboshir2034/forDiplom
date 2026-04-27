<?php
require_once __DIR__ . '/auth_lib.php';

app_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

$login = trim((string)($_POST['login'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($login === '' || $password === '') {
    app_json(['success' => false, 'message' => 'Введите логин и пароль'], 422);
}

$loginNorm = app_is_email($login) ? app_normalize_email($login) : $login;

$stmt = $mysqli->prepare("SELECT id, login, email, password, role FROM `user` WHERE login = ? OR email = ? LIMIT 1");
$stmt->bind_param('ss', $loginNorm, $loginNorm);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    app_json(['success' => false, 'message' => 'Неверный логин или пароль'], 401);
}

if (!app_verify_password_and_upgrade($mysqli, (int)$user['id'], $password, (string)$user['password'])) {
    app_json(['success' => false, 'message' => 'Неверный логин или пароль'], 401);
}

app_auth_login($user);
app_json(['success' => true, 'message' => 'Вход выполнен']);

