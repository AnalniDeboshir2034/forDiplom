<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/auth_lib.php';

$msg = '';

if (isset($_POST['set_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $allowed = ['new', 'paid', 'shipped', 'completed'];
    if ($orderId > 0 && in_array($status, $allowed, true)) {
        $stmt = $mysqli->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $stmt->bind_param('si', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        $msg = 'Статус обновлён';
    }
}

if (isset($_POST['send_email'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $to = app_normalize_email((string)($_POST['to_email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    if ($orderId > 0 && $to !== '' && app_is_email($to) && $subject !== '' && $body !== '') {
        $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
        $sent = @mail($to, $subject, $body, $headers);
        if (db_table_exists($mysqli, 'email_log')) {
            $status = $sent ? 'sent' : 'error';
            $error = $sent ? null : 'mail() returned false';
            $stmt = $mysqli->prepare("INSERT INTO email_log (to_email, subject, body, status, error) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sssss', $to, $subject, $body, $status, $error);
                $stmt->execute();
                $stmt->close();
            }
        }
        $msg = $sent ? 'Письмо отправлено' : 'Не удалось отправить письмо (mail())';
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$where = '';
$params = [];
$types = '';
if ($statusFilter !== '') {
    $where = "WHERE status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = "SELECT id, status, total, customer_name, customer_phone, customer_email, delivery_type, payment_type, created_at
        FROM orders
        $where
        ORDER BY id DESC
        LIMIT 200";

$stmt = $mysqli->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
while ($res && ($row = $res->fetch_assoc())) $orders[] = $row;
$stmt->close();

$openId = (int)($_GET['id'] ?? 0);
$openOrder = null;
$openItems = [];
if ($openId > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $openId);
    $stmt->execute();
    $res = $stmt->get_result();
    $openOrder = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($openOrder) {
        $stmt = $mysqli->prepare("SELECT product_name_snapshot, qty, unit_price_snapshot, line_total FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $openId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) $openItems[] = $row;
        $stmt->close();
    }
}

admin_page_start('Заказы');
?>

<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
    <h2>Список заказов</h2>
    <p class="muted">Фильтр по статусу:
        <a href="/admin/orders">все</a> ·
        <a href="/admin/orders?status=new">new</a> ·
        <a href="/admin/orders?status=paid">paid</a> ·
        <a href="/admin/orders?status=shipped">shipped</a> ·
        <a href="/admin/orders?status=completed">completed</a>
    </p>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Статус</th>
            <th>Сумма</th>
            <th>Покупатель</th>
            <th>Доставка/Оплата</th>
            <th>Дата</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td>#<?= (int)$o['id'] ?></td>
                <td><?= htmlspecialchars((string)$o['status']) ?></td>
                <td><?= htmlspecialchars((string)$o['total']) ?></td>
                <td>
                    <?= htmlspecialchars((string)($o['customer_name'] ?? '')) ?><br>
                    <span class="muted"><?= htmlspecialchars((string)($o['customer_phone'] ?? '')) ?></span><br>
                    <span class="muted"><?= htmlspecialchars((string)($o['customer_email'] ?? '')) ?></span>
                </td>
                <td>
                    <span class="muted"><?= htmlspecialchars((string)($o['delivery_type'] ?? '')) ?></span><br>
                    <span class="muted"><?= htmlspecialchars((string)($o['payment_type'] ?? '')) ?></span>
                </td>
                <td class="muted"><?= htmlspecialchars((string)($o['created_at'] ?? '')) ?></td>
                <td><a href="/admin/orders?id=<?= (int)$o['id'] ?>">Открыть</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($openOrder): ?>
<div class="card">
    <h2>Заказ #<?= (int)$openOrder['id'] ?></h2>
    <p class="muted">Контакты: <?= htmlspecialchars((string)($openOrder['customer_name'] ?? '')) ?> · <?= htmlspecialchars((string)($openOrder['customer_phone'] ?? '')) ?> · <?= htmlspecialchars((string)($openOrder['customer_email'] ?? '')) ?></p>
    <p class="muted">Доставка: <?= htmlspecialchars((string)($openOrder['delivery_type'] ?? '')) ?> · <?= htmlspecialchars((string)($openOrder['delivery_address'] ?? '')) ?> <?= htmlspecialchars((string)($openOrder['pickup_point'] ?? '')) ?></p>
    <p class="muted">Оплата: <?= htmlspecialchars((string)($openOrder['payment_type'] ?? '')) ?></p>
    <p class="muted">Сумма: <?= htmlspecialchars((string)($openOrder['total'] ?? '')) ?></p>

    <h3>Позиции</h3>
    <table>
        <thead><tr><th>Товар</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr></thead>
        <tbody>
        <?php foreach ($openItems as $it): ?>
            <tr>
                <td><?= htmlspecialchars((string)$it['product_name_snapshot']) ?></td>
                <td><?= (int)$it['qty'] ?></td>
                <td><?= htmlspecialchars((string)$it['unit_price_snapshot']) ?></td>
                <td><?= htmlspecialchars((string)$it['line_total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Статус</h3>
    <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="order_id" value="<?= (int)$openOrder['id'] ?>">
        <select name="status" required style="max-width:280px;">
            <?php foreach (['new','paid','shipped','completed'] as $st): ?>
                <option value="<?= $st ?>" <?= ((string)$openOrder['status'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="set_status" value="1">Сохранить</button>
    </form>

    <h3>Написать покупателю</h3>
    <form method="post">
        <input type="hidden" name="order_id" value="<?= (int)$openOrder['id'] ?>">
        <label>Кому</label>
        <input type="email" name="to_email" value="<?= htmlspecialchars((string)($openOrder['customer_email'] ?? '')) ?>" required>
        <label>Тема</label>
        <input type="text" name="subject" value="Статус заказа #<?= (int)$openOrder['id'] ?> на Medikator.ru" required>
        <label>Сообщение</label>
        <textarea name="body" rows="6" required>Здравствуйте!

Статус вашего заказа #<?= (int)$openOrder['id'] ?>: <?= htmlspecialchars((string)($openOrder['status'] ?? '')) ?>.
</textarea>
        <button type="submit" name="send_email" value="1">Отправить письмо</button>
    </form>
</div>
<?php endif; ?>

<?php admin_page_end(); ?>

