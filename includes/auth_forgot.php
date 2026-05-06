<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/mail_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

app_apply_migrations($mysqli);

$email = app_normalize_email((string)($_POST['email'] ?? ''));
if (!app_is_email($email)) {
    app_json(['success' => false, 'message' => 'Введите корректный e-mail'], 422);
}

$stmt = $mysqli->prepare("SELECT id, email FROM `user` WHERE email = ? OR login = ? LIMIT 1");
$stmt->bind_param('ss', $email, $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

// Do not leak whether user exists.
if (!$user) {
    app_json(['success' => true, 'message' => 'Если e-mail найден, мы отправили письмо со ссылкой.']);
}

// Ensure table exists (migration should create it).
$token = bin2hex(random_bytes(16));
$tokenHash = password_hash($token, PASSWORD_DEFAULT);
$expires = date('Y-m-d H:i:s', time() + 60 * 60); // 1 hour

$stmt = $mysqli->prepare("INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`) VALUES (?, ?, ?)");
if (!$stmt) {
    app_json(['success' => false, 'message' => 'Сервис восстановления пока недоступен (нужны миграции)'], 500);
}
$uid = (int)$user['id'];
$stmt->bind_param('iss', $uid, $tokenHash, $expires);
$stmt->execute();
$stmt->close();

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$resetUrl = $scheme . '://' . $host . app_url('reset.php') . '?token=' . rawurlencode($token);

$subject = 'Восстановление пароля на ' . app_site_host();
$body = "Здравствуйте!\n\nДля восстановления пароля перейдите по ссылке:\n{$resetUrl}\n\nСсылка действует 1 час.\nЕсли вы не запрашивали восстановление — просто проигнорируйте это письмо.\n";

$mailError = null;
$sent = app_send_mail($email, $subject, $body, $mailError);

// Log email attempt if table exists.
app_log_email_attempt($mysqli, $email, $subject, $body, $sent, $mailError);

app_json(['success' => true, 'message' => 'Если e-mail найден, мы отправили письмо со ссылкой.']);

