<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

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

$resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . '/reset?token=' . rawurlencode($token);

$subject = 'Восстановление пароля на Medikator.ru';
$body = "Здравствуйте!\n\nДля восстановления пароля перейдите по ссылке:\n{$resetUrl}\n\nСсылка действует 1 час.\nЕсли вы не запрашивали восстановление — просто проигнорируйте это письмо.\n";

$headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
$sent = @mail($email, $subject, $body, $headers);

// Log email attempt if table exists.
if (db_table_exists($mysqli, 'email_log')) {
    $status = $sent ? 'sent' : 'error';
    $error = $sent ? null : 'mail() returned false';
    $stmt = $mysqli->prepare("INSERT INTO `email_log` (`to_email`, `subject`, `body`, `status`, `error`) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sssss', $email, $subject, $body, $status, $error);
        $stmt->execute();
        $stmt->close();
    }
}

app_json(['success' => true, 'message' => 'Если e-mail найден, мы отправили письмо со ссылкой.']);

