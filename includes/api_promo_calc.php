<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'message' => 'Неверный метод'], 405);
}

$rawItems = (string)($_POST['items'] ?? '');
$promo = trim((string)($_POST['promo_code'] ?? ''));

$items = [];
if ($rawItems !== '') {
    $decoded = json_decode($rawItems, true);
    if (is_array($decoded)) $items = $decoded;
}

if (!is_array($items) || count($items) === 0) {
    app_json(['success' => true, 'subtotal' => 0, 'discount_total' => 0, 'total' => 0]);
}

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
    app_json(['success' => true, 'subtotal' => 0, 'discount_total' => 0, 'total' => 0]);
}

// Fetch prices (NULL => 0).
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
foreach ($qtyMap as $pid => $qty) {
    $price = isset($products[$pid]) ? (float)$products[$pid]['price'] : 0.0;
    $subtotal += $price * $qty;
}

$discountTotal = 0.0;
$promoRow = null;
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
}

if ($promoRow) {
    $type = (string)($promoRow['type'] ?? 'percent');
    $value = (float)($promoRow['value'] ?? 0);
    if ($type === 'fixed') {
        $discountTotal = min($subtotal, max(0.0, $value));
    } else {
        $pct = max(0.0, min(100.0, $value));
        $discountTotal = $subtotal * ($pct / 100.0);
    }
}

$total = max(0.0, $subtotal - $discountTotal);

app_json([
    'success' => true,
    'subtotal' => round($subtotal, 2),
    'discount_total' => round($discountTotal, 2),
    'total' => round($total, 2),
    'promo_applied' => (bool)$promoRow,
]);

