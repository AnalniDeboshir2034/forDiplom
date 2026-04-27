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

$orderId = (int)($_POST['order_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 5);
$text = trim((string)($_POST['text'] ?? ''));

if ($orderId <= 0) {
    app_json(['success' => false, 'message' => 'Нет order_id'], 422);
}
$rating = max(1, min(5, $rating));
if ($text === '' || app_strlen($text) < 5) {
    app_json(['success' => false, 'message' => 'Введите текст отзыва (минимум 5 символов)'], 422);
}

// Order must belong to user and be completed.
$stmt = $mysqli->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$res = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$order) {
    app_json(['success' => false, 'message' => 'Заказ не найден'], 404);
}
if ((string)$order['status'] !== 'completed') {
    app_json(['success' => false, 'message' => 'Отзыв можно оставить только после завершения заказа'], 409);
}

// Only one review per order per user.
$stmt = $mysqli->prepare("SELECT id FROM reviews WHERE order_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$res = $stmt->get_result();
$exists = $res && $res->num_rows > 0;
$stmt->close();
if ($exists) {
    app_json(['success' => false, 'message' => 'Отзыв по этому заказу уже отправлен'], 409);
}

$status = 'pending';
$stmt = $mysqli->prepare("INSERT INTO reviews (order_id, user_id, product_id, rating, text, status) VALUES (?, ?, NULL, ?, ?, ?)");
if (!$stmt) {
    app_json(['success' => false, 'message' => 'Ошибка БД (нужны миграции reviews)'], 500);
}
$stmt->bind_param('iiiss', $orderId, $uid, $rating, $text, $status);
$stmt->execute();
$stmt->close();

app_json(['success' => true, 'message' => 'Отзыв отправлен на модерацию']);

