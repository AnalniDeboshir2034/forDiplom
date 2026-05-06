<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function app_mail_from_address(): string
{
    $cfg = app_mail_config();
    return (string)($cfg['from_address'] ?? ('no-reply@' . app_site_host()));
}

function app_mail_headers(): string
{
    $from = app_mail_from_address();
    return "From: {$from}\r\n" .
           "Reply-To: {$from}\r\n" .
           "MIME-Version: 1.0\r\n" .
           "Content-Type: text/plain; charset=UTF-8\r\n";
}

function app_send_mail(string $to, string $subject, string $body, ?string &$error = null): bool
{
    $error = null;
    $cfg = app_mail_config();
    $transport = strtolower((string)($cfg['transport'] ?? 'smtp'));

    // ============================================================
    // ВРЕМЕННО: принудительно отключаем SMTP, используем только mail()
    // Если захочешь вернуть SMTP — поменяй false на true
    // ============================================================
$forceUseMail = false; // Разрешаем SMTP, у нас теперь есть рабочие порты    
    $canUseSmtp = !$forceUseMail && $transport === 'smtp'
        && !empty($cfg['host'])
        && !empty($cfg['username'])
        && !empty($cfg['password']);

    if ($canUseSmtp) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string)$cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = (string)$cfg['username'];
            $mail->Password = (string)$cfg['password'];
            $mail->Port = (int)$cfg['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = (int)$cfg['timeout'];

            $secure = strtolower((string)($cfg['secure'] ?? 'ssl'));
            if ($secure === 'tls' || $secure === 'starttls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secure === 'ssl' || $secure === 'smtps') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = false;
            }

            $from = app_mail_from_address();
            $fromName = (string)($cfg['from_name'] ?? app_site_name());
            $mail->setFrom($from, $fromName);
            if (!empty($cfg['reply_to'])) {
                $mail->addReplyTo((string)$cfg['reply_to'], $fromName);
            }
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            $mail->send();
            return true;
        } catch (Exception $e) {
            $error = 'SMTP error: ' . $e->getMessage();
        } catch (Throwable $e) {
            $error = 'SMTP error: ' . $e->getMessage();
        }
    }

    // ============================================================
    // FALLBACK: используем встроенную PHP-функцию mail()
    // ============================================================
    
    // Дополнительные заголовки для mail()
    $headers = app_mail_headers();
    
    // Добавляем дополнительные заголовки для лучшей доставляемости
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    
    // Для mail() важно правильно закодировать тему (если есть русские буквы)
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    error_clear_last();
    
    // Пробуем отправить
    $ok = @mail($to, $encodedSubject, $body, $headers);
    
    if ($ok) {
        return true;
    }
    
    // Если не отправилось — собираем ошибку
    $last = error_get_last();
    $fallbackError = (is_array($last) && !empty($last['message'])) ? (string)$last['message'] : 'mail() returned false';
    $error = ($error ? ($error . '; ') : '') . 'mail() failed: ' . $fallbackError;
    
    // Дополнительная диагностика
    error_log("MAIL DEBUG: to={$to}, subject={$subject}, error={$fallbackError}");
    
    return false;
}

function app_log_email_attempt(mysqli $mysqli, string $to, string $subject, string $body, bool $sent, ?string $error = null): void
{
    if (!function_exists('db_table_exists') || !db_table_exists($mysqli, 'email_log')) {
        return;
    }

    $status = $sent ? 'sent' : 'error';
    $stmt = $mysqli->prepare("INSERT INTO email_log (to_email, subject, body, status, error) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sssss', $to, $subject, $body, $status, $error);
        $stmt->execute();
        $stmt->close();
    }
}