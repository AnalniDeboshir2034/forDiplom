<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/auth_lib.php';
require_once __DIR__ . '/../includes/mail_lib.php';

$msg = '';

if (!function_exists('app_order_status_label')) {
    function app_order_status_label(string $status): string
    {
        $map = ['new' => 'Новый', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'completed' => 'Завершён'];
        return $map[$status] ?? $status;
    }
}
if (!function_exists('app_order_status_labels')) {
    function app_order_status_labels(): array
    {
        return ['new' => 'Новый', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'completed' => 'Завершён'];
    }
}
if (!function_exists('app_delivery_type_label')) {
    function app_delivery_type_label(string $type): string
    {
        $map = ['courier' => 'Курьером', 'pickup' => 'Самовывоз'];
        return $map[$type] ?? $type;
    }
}
if (!function_exists('app_payment_type_label')) {
    function app_payment_type_label(string $type): string
    {
        $map = ['invoice' => 'Счёт на email', 'card_on_delivery' => 'Картой при получении', 'erip' => 'ЕРИП'];
        return $map[$type] ?? $type;
    }
}

// Разрешённые переходы статусов (только вперёд)
function get_allowed_next_status(?string $currentStatus): array
{
    $allowed = [
        'new' => ['paid'],
        'paid' => ['shipped'],
        'shipped' => ['completed'],
        'completed' => []
    ];
    return $allowed[$currentStatus] ?? [];
}

if (isset($_POST['set_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim((string)($_POST['status'] ?? ''));
    
    // Получаем текущий статус и email одним запросом
    $stmt = $mysqli->prepare("SELECT status, customer_email FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $currentRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$currentRow) {
        $msg = 'Заказ не найден';
    } else {
        $currentStatus = $currentRow['status'] ?? '';
        $customerEmail = $currentRow['customer_email'] ?? '';
        $allowedNext = get_allowed_next_status($currentStatus);
        
        if (in_array($newStatus, $allowedNext, true)) {
            $stmt = $mysqli->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
            $stmt->bind_param('si', $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();
            $msg = 'Статус обновлён';

            // Отправка письма (ТВОЙ КОД)
            $to = app_normalize_email((string)$customerEmail);
            if ($to !== '' && app_is_email($to)) {
                $subject = 'Статус заказа #' . $orderId . ' обновлен';
                $body = "Здравствуйте!\n\nСтатус вашего заказа #{$orderId}: " . app_order_status_label($newStatus) . ".\n\nСпасибо за покупку!";
                $mailError = null;
                $sent = app_send_mail($to, $subject, $body, $mailError);
                app_log_email_attempt($mysqli, $to, $subject, $body, $sent, $mailError);
                if ($sent) {
                    $msg .= ' Письмо отправлено.';
                } else {
                    $msg .= ' Но письмо не отправилось: ' . ($mailError ?: 'ошибка отправки');
                }
            } else {
                $msg .= ' Письмо не отправлено (email отсутствует или некорректен).';
            }
        } elseif ($newStatus !== $currentStatus) {
            $msg = 'Ошибка: нельзя изменить статус с "' . app_order_status_label($currentStatus) . '" на "' . app_order_status_label($newStatus) . '"';
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
    } else {
        $msg = 'Заполните все поля корректно';
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

$orders = [];
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    $msg = $msg ? $msg : ('Ошибка загрузки заказов: ' . $mysqli->error);
} else {
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
    while ($res && ($row = $res->fetch_assoc())) {
        $orders[] = $row;
    }
    $stmt->close();
}

function admin_load_order_with_items(mysqli $mysqli, int $orderId): array
{
    $openOrder = null;
    $openItems = [];
    if ($orderId <= 0) {
        return [$openOrder, $openItems];
    }

    $stmt = $mysqli->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $openOrder = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($openOrder) {
        $stmt = $mysqli->prepare("SELECT product_name_snapshot, qty, unit_price_snapshot, line_total FROM order_items WHERE order_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $openItems[] = $row;
        }
        $stmt->close();
    }

    return [$openOrder, $openItems];
}

function admin_render_order_detail_html(array $openOrder, array $openItems): void
{
    $currentStatus = (string)($openOrder['status'] ?? '');
    $allowedNext = get_allowed_next_status($currentStatus);
    $statuses = app_order_status_labels();
    $orderId = (int)$openOrder['id'];
    ?>
    <p class="muted">Контакты: <?= htmlspecialchars((string)($openOrder['customer_name'] ?? '')) ?> · <?= htmlspecialchars((string)($openOrder['customer_phone'] ?? '')) ?> · <?= htmlspecialchars((string)($openOrder['customer_email'] ?? '')) ?></p>
    <p class="muted">Доставка: <?= htmlspecialchars(app_delivery_type_label((string)($openOrder['delivery_type'] ?? ''))) ?> · <?= htmlspecialchars((string)($openOrder['delivery_address'] ?? '')) ?> <?= htmlspecialchars((string)($openOrder['pickup_point'] ?? '')) ?></p>
    <p class="muted">Оплата: <?= htmlspecialchars(app_payment_type_label((string)($openOrder['payment_type'] ?? ''))) ?></p>
    <p class="muted">Сумма: <?= htmlspecialchars((string)($openOrder['total'] ?? '')) ?> BYN</p>

    <h3>Позиции</h3>
    <div style="overflow-x: auto; overflow-y: clip;">
        <table style="min-width: 500px;">
            <thead><tr><th>Товар</th><th>Кол-во, шт.</th><th>Цена, BYN</th><th>Сумма, BYN</th></tr></thead>
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
    </div>

    <h3>Статус</h3>
    <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="order_id" value="<?= $orderId ?>">
        <select name="status" required style="max-width:280px;">
            <option value="<?= htmlspecialchars($currentStatus) ?>" selected><?= htmlspecialchars($statuses[$currentStatus] ?? $currentStatus) ?> (текущий)</option>
            <?php foreach ($allowedNext as $nextStatus): ?>
                <option value="<?= htmlspecialchars($nextStatus) ?>"><?= htmlspecialchars($statuses[$nextStatus] ?? $nextStatus) ?></option>
            <?php endforeach; ?>
            <?php if ($currentStatus === 'completed'): ?>
                <option disabled>— заказ завершён, изменение невозможно —</option>
            <?php endif; ?>
        </select>
        <?php if ($currentStatus !== 'completed' && !empty($allowedNext)): ?>
            <button type="submit" name="set_status" value="1">Сохранить</button>
        <?php else: ?>
            <button type="button" disabled style="opacity:0.5;">Недоступно</button>
        <?php endif; ?>
    </form>

    <h3>Написать покупателю</h3>
    <form method="post">
        <input type="hidden" name="order_id" value="<?= $orderId ?>">
        <label>Кому</label>
        <input type="email" name="to_email" value="<?= htmlspecialchars((string)($openOrder['customer_email'] ?? '')) ?>" required>
        <label>Тема</label>
        <input type="text" name="subject" value="Статус заказа #<?= $orderId ?> на <?= htmlspecialchars(app_site_host(), ENT_QUOTES, 'UTF-8') ?>" required>
        <label>Сообщение</label>
        <textarea name="body" rows="6" required>Здравствуйте!

Статус вашего заказа #<?= $orderId ?>: <?= htmlspecialchars(app_order_status_label((string)($openOrder['status'] ?? ''))) ?>.
</textarea>
        <button type="submit" name="send_email" value="1">Отправить письмо</button>
    </form>
    <?php
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'order') {
    header('Content-Type: application/json; charset=utf-8');
    $orderId = (int)($_GET['id'] ?? 0);
    [$openOrder, $openItems] = admin_load_order_with_items($mysqli, $orderId);
    if (!$openOrder) {
        echo json_encode(['success' => false, 'message' => 'Заказ не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ob_start();
    admin_render_order_detail_html($openOrder, $openItems);
    echo json_encode([
        'success' => true,
        'title' => 'Заказ #' . $orderId,
        'html' => ob_get_clean(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$autoOpenOrderId = (int)($_GET['id'] ?? 0);

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
        <a href="<?= htmlspecialchars(admin_url('orders.php?status=completed'), ENT_QUOTES, 'UTF-8') ?>">Завершён</a>
    </p>
    <p class="muted">Всего: <?= (int)$totalRows ?> · Страница <?= (int)$page ?> из <?= (int)$totalPages ?></p>
    <div style="overflow-x: auto; overflow-y: clip;">
        <table style="min-width: 800px;">
            <thead>
            <tr>
                <th>Номер</th>
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
                    <td><?= htmlspecialchars(app_order_status_label((string)$o['status'])) ?></td>
                    <td><?= htmlspecialchars((string)$o['total']) ?> BYN</td>
                    <td>
                        <?= htmlspecialchars((string)($o['customer_name'] ?? '')) ?><br>
                        <span class="muted"><?= htmlspecialchars((string)($o['customer_phone'] ?? '')) ?></span><br>
                        <span class="muted"><?= htmlspecialchars((string)($o['customer_email'] ?? '')) ?></span>
                    </td>
                    <td>
                        <span class="muted"><?= htmlspecialchars(app_delivery_type_label((string)($o['delivery_type'] ?? ''))) ?></span><br>
                        <span class="muted"><?= htmlspecialchars(app_payment_type_label((string)($o['payment_type'] ?? ''))) ?></span>
                    </td>
                    <td class="muted"><?= htmlspecialchars(app_format_datetime((string)($o['created_at'] ?? ''))) ?></td>
                    <td><button type="button" class="order-open-btn" data-order-id="<?= (int)$o['id'] ?>">Открыть</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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

<style>
.order-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    z-index: 1200;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.order-modal-overlay.active { display: flex; }
.order-modal {
    background: #fff;
    border-radius: 16px;
    width: min(920px, 100%);
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
}
.order-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 1;
}
.order-modal-header h2 { margin: 0; font-size: 20px; }
.order-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    color: #64748b;
    box-shadow: none;
}
.order-modal-body { padding: 20px; }
.order-open-btn { padding: 6px 12px; font-size: 13px; }
</style>

<div class="order-modal-overlay" id="orderModalOverlay" aria-hidden="true">
    <div class="order-modal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
        <div class="order-modal-header">
            <h2 id="orderModalTitle">Заказ</h2>
            <button type="button" class="order-modal-close" id="orderModalClose" aria-label="Закрыть">&times;</button>
        </div>
        <div class="order-modal-body" id="orderModalBody">
            <p class="muted">Загрузка...</p>
        </div>
    </div>
</div>

<script>
(function() {
    var overlay = document.getElementById('orderModalOverlay');
    var body = document.getElementById('orderModalBody');
    var title = document.getElementById('orderModalTitle');
    var closeBtn = document.getElementById('orderModalClose');
    var ordersUrl = <?= json_encode(admin_url('orders.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function closeModal() {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        body.innerHTML = '<p class="muted">Загрузка...</p>';
    }

    function openOrderModal(orderId) {
        if (!orderId) return;
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        body.innerHTML = '<p class="muted">Загрузка...</p>';
        fetch(ordersUrl + '?ajax=order&id=' + encodeURIComponent(orderId), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success) {
                    body.innerHTML = '<p class="msg" style="color:#b00020;">' + ((data && data.message) || 'Не удалось загрузить заказ') + '</p>';
                    return;
                }
                title.textContent = data.title || ('Заказ #' + orderId);
                body.innerHTML = data.html || '';
            })
            .catch(function() {
                body.innerHTML = '<p class="msg" style="color:#b00020;">Ошибка сети</p>';
            });
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.order-open-btn');
        if (btn) {
            openOrderModal(btn.getAttribute('data-order-id'));
            return;
        }
        if (e.target === overlay) closeModal();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
    });

    var autoOpenId = <?= (int)$autoOpenOrderId ?>;
    if (autoOpenId > 0) openOrderModal(String(autoOpenId));
})();
</script>

<?php admin_page_end(); ?>