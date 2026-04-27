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

$stmt = $mysqli->prepare("SELECT id, status, total, created_at
                          FROM orders
                          WHERE user_id = ?
                          ORDER BY id DESC
                          LIMIT 100");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
while ($res && ($row = $res->fetch_assoc())) {
    $orders[] = $row;
}
$stmt->close();

app_json(['success' => true, 'orders' => $orders]);

