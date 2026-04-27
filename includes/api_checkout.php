<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

app_session_start();

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = app_normalize_email((string)($_POST['email'] ?? ''));
$comment = trim((string)($_POST['message'] ?? ''));

$deliveryType = trim((string)($_POST['delivery_type'] ?? ''));
$deliveryAddress = trim((string)($_POST['delivery_address'] ?? ''));
$pickupPoint = trim((string)($_POST['pickup_point'] ?? ''));
$paymentType = trim((string)($_POST['payment_type'] ?? ''));
$promo = trim((string)($_POST['promo_code'] ?? ''));

$rawItems = (string)($_POST['items'] ?? '');
$items = [];
if ($rawItems !== '') {
    $decoded = json_decode($rawItems, true);
    if (is_array($decoded)) $items = $decoded;
}

if ($name === '' || $phone === '') {
    app_json(['success' => false, 'message' => 'Заполните имя и телефон'], 422);
}
if ($email !== '' && !app_is_email($email)) {
    app_json(['success' => false, 'message' => 'Некорректный e-mail'], 422);
}

$allowedDelivery = ['courier', 'pickup'];
$allowedPayment = ['invoice', 'card_on_delivery', 'erip'];

if (!in_array($deliveryType, $allowedDelivery, true)) {
    app_json(['success' => false, 'message' => 'Выберите способ доставки'], 422);
}
if (!in_array($paymentType, $allowedPayment, true)) {
    app_json(['success' => false, 'message' => 'Выберите способ оплаты'], 422);
}
if ($deliveryType === 'courier' && $deliveryAddress === '') {
    app_json(['success' => false, 'message' => 'Введите адрес доставки'], 422);
}
if ($deliveryType === 'pickup' && $pickupPoint === '') {
    app_json(['success' => false, 'message' => 'Введите пункт самовывоза'], 422);
}

// Normalize items.
$ids = [];
$qtyMap = [];
foreach ($items as $it) {
    $id = isset($it['id']) ? (int)$it['id'] : 0;
    $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
    if ($id < 0) continue;
    if ($qty <= 0) continue;
    $ids[] = $id;
    $qtyMap[$id] = ($qtyMap[$id] ?? 0) + $qty;
}
$ids = array_values(array_unique($ids));
if (count($ids) === 0) {
    app_json(['success' => false, 'message' => 'Корзина пуста'], 422);
}

// Fetch products with prices (NULL => 0).
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = $mysqli->prepare("SELECT id, name, COALESCE(price, 0) AS price FROM medicator WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$res = $stmt->get_result();
$products = [];
while ($res && ($row = $res->fetch_assoc())) {
    $products[(int)$row['id']] = $row;
}
$stmt->close();

$subtotal = 0.0;
$lines = [];
foreach ($qtyMap as $pid => $qty) {
    $p = $products[$pid] ?? null;
    $pname = $p ? (string)$p['name'] : ('Товар #' . $pid);
    $price = $p ? (float)$p['price'] : 0.0;
    $lineTotal = $price * $qty;
    $subtotal += $lineTotal;
    $lines[] = [
        'product_id' => $pid,
        'product_name_snapshot' => $pname,
        'qty' => $qty,
        'unit_price_snapshot' => $price,
        'line_total' => $lineTotal,
    ];
}

// Promo calculation.
$discountTotal = 0.0;
$promoId = null;
$promoApplied = false;
if ($promo !== '' && db_table_exists($mysqli, 'promo_codes')) {
    $promoUpper = mb_strtoupper($promo, 'UTF-8');
    $stmt = $mysqli->prepare("SELECT * FROM promo_codes WHERE code = ? AND active = 1 LIMIT 1");
    $stmt->bind_param('s', $promoUpper);
    $stmt->execute();
    $res = $stmt->get_result();
    $promoRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($promoRow) {
        $now = time();
        $starts = !empty($promoRow['starts_at']) ? strtotime($promoRow['starts_at']) : null;
        $ends = !empty($promoRow['ends_at']) ? strtotime($promoRow['ends_at']) : null;
        if (($starts && $now < $starts) || ($ends && $now > $ends)) {
            $promoRow = null;
        }
        if ($promoRow && isset($promoRow['min_total']) && $promoRow['min_total'] !== null) {
            if ($subtotal < (float)$promoRow['min_total']) $promoRow = null;
        }
        if ($promoRow && isset($promoRow['max_uses']) && $promoRow['max_uses'] !== null) {
            if ((int)$promoRow['uses_count'] >= (int)$promoRow['max_uses']) $promoRow = null;
        }
    }
    if (!empty($promoRow)) {
        $promoId = (int)$promoRow['id'];
        $type = (string)($promoRow['type'] ?? 'percent');
        $value = (float)($promoRow['value'] ?? 0);
        if ($type === 'fixed') {
            $discountTotal = min($subtotal, max(0.0, $value));
        } else {
            $pct = max(0.0, min(100.0, $value));
            $discountTotal = $subtotal * ($pct / 100.0);
        }
        $promoApplied = true;
    }
}

$total = max(0.0, $subtotal - $discountTotal);

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) $userId = null;

// Create order.
$stmt = $mysqli->prepare("INSERT INTO `orders`
    (`customer_id`, `user_id`, `order_date`, `status`, `delivery_type`, `delivery_address`, `pickup_point`, `payment_type`, `promo_code_id`,
     `subtotal`, `discount_total`, `total`, `customer_name`, `customer_phone`, `customer_email`, `comment`, `created_at`)
    VALUES (NULL, ?, CURDATE(), 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    app_json(['success' => false, 'message' => 'Ошибка БД (нужны миграции orders)'], 500);
}

$uidParam = $userId;
$promoParam = $promoId;
$stmt->bind_param(
    'issssidddssss',
    $uidParam,
    $deliveryType,
    $deliveryAddress,
    $pickupPoint,
    $paymentType,
    $promoParam,
    $subtotal,
    $discountTotal,
    $total,
    $name,
    $phone,
    $email,
    $comment
);
$ok = $stmt->execute();
$orderId = (int)$stmt->insert_id;
$stmt->close();
if (!$ok || $orderId <= 0) {
    app_json(['success' => false, 'message' => 'Не удалось создать заказ'], 500);
}

// Insert order items.
foreach ($lines as $ln) {
    $pid = (int)$ln['product_id'];
    $pname = (string)$ln['product_name_snapshot'];
    $qty = (int)$ln['qty'];
    $unit = (float)$ln['unit_price_snapshot'];
    $lineTotal = (float)$ln['line_total'];
    $stmt = $mysqli->prepare("INSERT INTO `order_items` (`order_id`, `product_id`, `product_name_snapshot`, `qty`, `unit_price_snapshot`, `line_total`)
                              VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iisidd', $orderId, $pid, $pname, $qty, $unit, $lineTotal);
        $stmt->execute();
        $stmt->close();
    }
}

// Increment promo uses.
if ($promoApplied && $promoId) {
    $stmt = $mysqli->prepare("UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $promoId);
        $stmt->execute();
        $stmt->close();
    }
}

app_json([
    'success' => true,
    'message' => 'Заказ оформлен',
    'order_id' => $orderId,
    'subtotal' => round($subtotal, 2),
    'discount_total' => round($discountTotal, 2),
    'total' => round($total, 2),
]);

