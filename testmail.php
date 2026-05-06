<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mail_lib.php';

$error = null;
$sent = app_send_mail('aleksfinski@gmail.ru', 'Тест SMTP', 'Проверка связи', $error);

if ($sent) {
    echo "✅ Письмо отправлено!";
} else {
    echo "❌ Ошибка: " . htmlspecialchars($error);
}