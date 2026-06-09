<?php
require_once __DIR__ . '/auth_lib.php';

app_session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    app_json(['success' => false, 'message' => 'Требуется вход'], 401);
}

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    app_json(['success' => false, 'message' => 'Нет order_id'], 422);
}

$stmt = $mysqli->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $uid);
$stmt->execute();
$res = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$order) {
    app_json(['success' => false, 'message' => 'Заказ не найден'], 404);
}

$stmt = $mysqli->prepare("SELECT product_name_snapshot, qty, unit_price_snapshot, line_total
                          FROM order_items
                          WHERE order_id = ?
                          ORDER BY id ASC");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($res && ($row = $res->fetch_assoc())) {
    $items[] = $row;
}
$stmt->close();

app_json(['success' => true, 'order' => app_localize_order_row($order), 'items' => $items]);

