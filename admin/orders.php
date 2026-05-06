<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/auth_lib.php';
require_once __DIR__ . '/../includes/mail_lib.php';

$msg = '';
function admin_order_status_label(string $status): string
{
    $map = [
        'new' => 'Новый',
        'paid' => 'Оплачен',
        'shipped' => 'Отправлен',
        'completed' => 'Завершен',
    ];
    return $map[$status] ?? $status;
}

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

        $stmt = $mysqli->prepare("SELECT customer_email FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $to = app_normalize_email((string)($row['customer_email'] ?? ''));
        if ($to !== '' && app_is_email($to)) {
            $subject = 'Статус заказа #' . $orderId . ' обновлен';
            $body = "Здравствуйте!\n\nСтатус вашего заказа #{$orderId}: " . admin_order_status_label($status) . ".\n\nСпасибо за покупку!";
            $mailError = null;
            $sent = app_send_mail($to, $subject, $body, $mailError);
            app_log_email_attempt($mysqli, $to, $subject, $body, $sent, $mailError);
        }
    }
}

if (isset($_POST['send_email'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $to = app_normalize_email((string)($_POST['to_email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    if ($orderId > 0 && $to !== '' && app_is_email($to) && $subject !== '' && $body !== '') {
        $mailError = null;
        $sent = app_send_mail($to, $subject, $body, $mailError);
        app_log_email_attempt($mysqli, $to, $subject, $body, $sent, $mailError);
        $msg = $sent ? 'Письмо отправлено' : ('Не удалось отправить письмо: ' . ($mailError ?: 'mail() returned false'));
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

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) AS cnt FROM orders $where";
$countStmt = $mysqli->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countRes = $countStmt->get_result();
$totalRows = (int)(($countRes ? $countRes->fetch_assoc()['cnt'] : 0) ?? 0);
$countStmt->close();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT id, status, total, customer_name, customer_phone, customer_email, delivery_type, payment_type, created_at
        FROM orders
        $where
        ORDER BY id DESC
        LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($sql);
if ($types !== '') {
    $typesWithPage = $types . 'ii';
    $paramsWithPage = $params;
    $paramsWithPage[] = $perPage;
    $paramsWithPage[] = $offset;
    $stmt->bind_param($typesWithPage, ...$paramsWithPage);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
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
        <a href="<?= htmlspecialchars(admin_url('orders.php'), ENT_QUOTES, 'UTF-8') ?>">все</a> ·
        <a href="<?= htmlspecialchars(admin_url('orders.php?status=new'), ENT_QUOTES, 'UTF-8') ?>">Новый</a> ·
        <a href="<?= htmlspecialchars(admin_url('orders.php?status=paid'), ENT_QUOTES, 'UTF-8') ?>">Оплачен</a> ·
        <a href="<?= htmlspecialchars(admin_url('orders.php?status=shipped'), ENT_QUOTES, 'UTF-8') ?>">Отправлен</a> ·
        <a href="<?= htmlspecialchars(admin_url('orders.php?status=completed'), ENT_QUOTES, 'UTF-8') ?>">Завершен</a>
    </p>
    <p class="muted">Всего: <?= (int)$totalRows ?> · Страница <?= (int)$page ?> из <?= (int)$totalPages ?></p>
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
                <td><?= htmlspecialchars(admin_order_status_label((string)$o['status'])) ?></td>
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
                <td><a href="<?= htmlspecialchars(admin_url('orders.php?id=' . (int)$o['id']), ENT_QUOTES, 'UTF-8') ?>">Открыть</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <?php
        $buildOrdersPageUrl = function (int $targetPage) use ($statusFilter): string {
            $qs = ['page' => $targetPage];
            if ($statusFilter !== '') {
                $qs['status'] = $statusFilter;
            }
            return admin_url('orders.php?' . http_build_query($qs));
        };
        ?>
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($buildOrdersPageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">← Назад</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildOrdersPageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Вперед →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
                <option value="<?= $st ?>" <?= ((string)$openOrder['status'] === $st) ? 'selected' : '' ?>><?= htmlspecialchars(admin_order_status_label($st)) ?></option>
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
        <input type="text" name="subject" value="Статус заказа #<?= (int)$openOrder['id'] ?> на <?= htmlspecialchars(app_site_host(), ENT_QUOTES, 'UTF-8') ?>" required>
        <label>Сообщение</label>
        <textarea name="body" rows="6" required>Здравствуйте!

Статус вашего заказа #<?= (int)$openOrder['id'] ?>: <?= htmlspecialchars(admin_order_status_label((string)($openOrder['status'] ?? ''))) ?>.
</textarea>
        <button type="submit" name="send_email" value="1">Отправить письмо</button>
    </form>
</div>
<?php endif; ?>

<?php admin_page_end(); ?>

