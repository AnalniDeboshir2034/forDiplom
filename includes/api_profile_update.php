<?php
require_once __DIR__ . '/auth_lib.php';

app_session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    app_json(['success' => false, 'message' => 'Требуется вход'], 401);
}

$name = trim((string)($_POST['name'] ?? ''));
$email = app_normalize_email((string)($_POST['email'] ?? ''));
$unp = trim((string)($_POST['unp'] ?? ''));
$newPassword = (string)($_POST['new_password'] ?? '');

if ($email !== '' && !app_is_email($email)) {
    app_json(['success' => false, 'message' => 'Некорректный e-mail'], 422);
}

// Update basics (only if columns exist; migrations should add them).
$stmt = $mysqli->prepare("UPDATE `user` SET `name` = ?, `email` = ?, `unp` = ? WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('sssi', $name, $email, $unp, $uid);
    $stmt->execute();
    $stmt->close();
} else {
    // Fallback for pre-migration DB: update email only.
    $stmt2 = $mysqli->prepare("UPDATE `user` SET `email` = ? WHERE id = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('si', $email, $uid);
        $stmt2->execute();
        $stmt2->close();
    }
}

if ($newPassword !== '') {
    if (app_strlen($newPassword) < 8) {
        app_json(['success' => false, 'message' => 'Новый пароль должен быть минимум 8 символов'], 422);
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE `user` SET `password` = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param('si', $hash, $uid);
    $stmt->execute();
    $stmt->close();
}

app_json(['success' => true, 'message' => 'Профиль обновлён']);

