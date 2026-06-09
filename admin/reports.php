<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';

$adminName = 'Administrator';
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    $stmt = $mysqli->prepare("SELECT name FROM user WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $adminName = !empty($row['name']) ? $row['name'] : 'Administrator';
        }
        $stmt->close();
    }
}

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$export = isset($_GET['export']) ? 1 : 0;
$report = trim((string)($_GET['report'] ?? ''));

$ordersWhere = [];
$ordersParams = [];
$ordersTypes = '';

if ($dateFrom !== '') {
    $ordersWhere[] = 'o.created_at >= ?';
    $ordersParams[] = $dateFrom . ' 00:00:00';
    $ordersTypes .= 's';
}
if ($dateTo !== '') {
    $ordersWhere[] = 'o.created_at <= ?';
    $ordersParams[] = $dateTo . ' 23:59:59';
    $ordersTypes .= 's';
}
if ($status !== '') {
    $ordersWhere[] = 'o.status = ?';
    $ordersParams[] = $status;
    $ordersTypes .= 's';
}
$ordersWhereSql = $ordersWhere ? ('WHERE ' . implode(' AND ', $ordersWhere)) : '';

$categories = [];
$resCat = $mysqli->query("SELECT DISTINCT filtr FROM medicator WHERE filtr IS NOT NULL AND filtr <> '' ORDER BY filtr ASC");
while ($resCat && ($row = $resCat->fetch_assoc())) {
    $categories[] = (string)$row['filtr'];
}

// 1) Статистика по заказам
$byCategory = [];
$sqlByCategory = "SELECT COALESCE(m.filtr, 'Без категории') AS category_slug, COUNT(DISTINCT o.id) AS orders_count, SUM(oi.line_total) AS amount
                  FROM orders o
                  LEFT JOIN order_items oi ON oi.order_id = o.id
                  LEFT JOIN medicator m ON m.id = oi.product_id
                  $ordersWhereSql
                  GROUP BY category_slug
                  ORDER BY amount DESC";
$stmt = $mysqli->prepare($sqlByCategory);
if ($stmt) {
    if ($ordersTypes !== '') {
        $stmt->bind_param($ordersTypes, ...$ordersParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) $byCategory[] = $row;
    $stmt->close();
}

// 2) Топ продаваемых товаров
$topProducts = [];
$sqlTopProducts = "SELECT oi.product_name_snapshot AS product_name, SUM(oi.qty) AS total_qty, SUM(oi.line_total) AS total_amount
                   FROM orders o
                   JOIN order_items oi ON oi.order_id = o.id
                   $ordersWhereSql
                   GROUP BY oi.product_name_snapshot
                   ORDER BY total_qty DESC, total_amount DESC
                   LIMIT 30";
$stmt = $mysqli->prepare($sqlTopProducts);
if ($stmt) {
    if ($ordersTypes !== '') {
        $stmt->bind_param($ordersTypes, ...$ordersParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) $topProducts[] = $row;
    $stmt->close();
}

// 3) Регистрации
$registrations = [];
$userWhere = [];
$userParams = [];
$userTypes = '';
if ($dateFrom !== '') {
    $userWhere[] = 'created_at >= ?';
    $userParams[] = $dateFrom . ' 00:00:00';
    $userTypes .= 's';
}
if ($dateTo !== '') {
    $userWhere[] = 'created_at <= ?';
    $userParams[] = $dateTo . ' 23:59:59';
    $userTypes .= 's';
}
$userWhereSql = $userWhere ? ('WHERE ' . implode(' AND ', $userWhere)) : '';

$sqlRegistrations = "SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS period_day, COUNT(*) AS registrations_count
                     FROM `user`
                     $userWhereSql
                     GROUP BY period_day
                     ORDER BY period_day ASC";
$stmt = $mysqli->prepare($sqlRegistrations);
if ($stmt) {
    if ($userTypes !== '') {
        $stmt->bind_param($userTypes, ...$userParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) $registrations[] = $row;
    $stmt->close();
}

if (empty($registrations) && $dateFrom === '' && $dateTo === '') {
    $resLast30 = $mysqli->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS period_day, COUNT(*) AS registrations_count
                                 FROM `user`
                                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                 GROUP BY period_day
                                 ORDER BY period_day ASC");
    $registrations = [];
    while ($resLast30 && ($row = $resLast30->fetch_assoc())) {
        $registrations[] = $row;
    }
}

// 4) Промокоды
$promoUsage = [];
$sqlPromo = "SELECT COALESCE(pc.code, 'Без промокода') AS promo_code, COUNT(o.id) AS orders_count, SUM(o.discount_total) AS discount_sum
             FROM orders o
             LEFT JOIN promo_codes pc ON pc.id = o.promo_code_id
             $ordersWhereSql
             GROUP BY promo_code
             ORDER BY discount_sum DESC, orders_count DESC";
$stmt = $mysqli->prepare($sqlPromo);
if ($stmt) {
    if ($ordersTypes !== '') {
        $stmt->bind_param($ordersTypes, ...$ordersParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) $promoUsage[] = $row;
    $stmt->close();
}

// 5) Повторные покупатели
$repeatUsersDetail = [];
$sqlRepeatUsersDetail = "
    SELECT 
        u.name AS user_name,
        DATE_FORMAT(o.created_at, '%d.%m.%Y %H:%i') AS order_date,
        GROUP_CONCAT(DISTINCT oi.product_name_snapshot SEPARATOR ', ') AS products,
        (SELECT COUNT(*) FROM orders WHERE user_id = o.user_id) AS orders_count,
        SUM(oi.line_total) AS total_spent
    FROM orders o
    JOIN `user` u ON u.id = o.user_id
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id IS NOT NULL 
        AND o.user_id IN (
            SELECT user_id 
            FROM orders 
            WHERE user_id IS NOT NULL 
            $ordersWhereSql
            GROUP BY user_id 
            HAVING COUNT(*) > 1
        )
        $ordersWhereSql
    GROUP BY o.user_id, o.id
    ORDER BY u.name, o.created_at DESC
";
$stmt = $mysqli->prepare($sqlRepeatUsersDetail);
if ($stmt) {
    $params = array_merge($ordersParams, $ordersParams);
    $types = $ordersTypes . $ordersTypes;
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) $repeatUsersDetail[] = $row;
    $stmt->close();
}

$repeatUsersCount = 0;
$sqlRepeatUsersCount = "SELECT COUNT(DISTINCT o.user_id) AS cnt FROM orders o WHERE o.user_id IS NOT NULL $ordersWhereSql GROUP BY o.user_id HAVING COUNT(*) > 1";
$res = $mysqli->query($sqlRepeatUsersCount);
if ($res && $row = $res->fetch_assoc()) {
    $repeatUsersCount = (int)$row['cnt'];
}

if (!function_exists('app_order_status_labels')) {
    function app_order_status_labels(): array
    {
        return ['new' => 'Новый', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'completed' => 'Завершён'];
    }
}
if (!function_exists('app_category_label')) {
    function app_category_label($mysqli, string $slug): string
    {
        return $slug === '' ? 'Без категории' : $slug;
    }
}
$statusLabels = app_order_status_labels();

function report_format_money(float $amount): string
{
    return number_format($amount, 2, '.', ' ') . ' BYN';
}

// Функция экспорта
function export_html_report(string $filename, string $title, array $headers, array $rows, string $adminName, array $totals = [])
{
    global $dateFrom, $dateTo, $status, $category, $statusLabels, $mysqli;
    
    $statusText = ($status && isset($statusLabels[$status])) ? $statusLabels[$status] : 'все';
    $categoryText = $category ? app_category_label($mysqli, $category) : 'все';
    $periodText = ($dateFrom ?: 'с начала') . ' — ' . ($dateTo ?: 'по текущий');
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Inter, "Segoe UI", Arial, sans-serif; font-size: 14px; padding: 20px; background: white; color: #333; }
            .card { background: white; border-radius: 8px; padding: 20px; max-width: 1200px; margin: 0 auto; }
            .card h2 { margin-top: 0; margin-bottom: 16px; font-size: 18px; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px; }
            .report-meta { margin-bottom: 16px; font-size: 13px; color: #666; line-height: 1.6; }
            .report-meta p { margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
            th { background: #f5f5f5; font-weight: 600; }
            .total-row { background: #f0f0f0; font-weight: bold; }
        </style>
    </head>
    <body>
    <div class="card">
        <h2>' . htmlspecialchars($title) . '</h2>
        <div class="report-meta">
            <p>Кем создан: ' . htmlspecialchars($adminName) . '</p>
            <p>Дата создания: ' . date('d.m.Y H:i') . '</p>
            <p>Период: ' . htmlspecialchars($periodText) . '</p>
            <p>Статус заказа: ' . htmlspecialchars($statusText) . '</p>
            <p>Категория: ' . htmlspecialchars($categoryText) . '</p>
        </div>
        <table>
            <thead><tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . htmlspecialchars($h) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    if (!empty($totals)) {
        $html .= '<tr class="total-row">';
        foreach ($totals as $totalCell) {
            $html .= '<td><strong>' . htmlspecialchars((string)$totalCell) . '</strong></td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
        </table>
    </div>
    </body>
    </html>';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo $html;
    exit;
}

// Экспорт
if ($export && $report !== '') {
    if ($report === 'by_category') {
        $rows = [];
        $totalOrders = 0;
        $totalAmount = 0;
        foreach ($byCategory as $r) {
            $ordersCount = (int)$r['orders_count'];
            $amount = (float)$r['amount'];
            $rows[] = [app_category_label($mysqli, (string)$r['category_slug']), $ordersCount . ' шт.', report_format_money($amount)];
            $totalOrders += $ordersCount;
            $totalAmount += $amount;
        }
        export_html_report('orders_by_category', 'Заказы по категориям', 
            ['Категория', 'Заказов, шт.', 'Сумма, BYN'], $rows, $adminName,
            ['ИТОГО', $totalOrders . ' шт.', report_format_money($totalAmount)]);
    }
    if ($report === 'top_products') {
        $rows = [];
        $totalQty = 0;
        $totalAmount = 0;
        foreach ($topProducts as $r) {
            $qty = (int)$r['total_qty'];
            $amount = (float)$r['total_amount'];
            $rows[] = [$r['product_name'], $qty . ' шт.', report_format_money($amount)];
            $totalQty += $qty;
            $totalAmount += $amount;
        }
        export_html_report('top_products', 'Самые продаваемые товары', 
            ['Товар', 'Продано, шт.', 'Сумма, BYN'], $rows, $adminName,
            ['ИТОГО', $totalQty . ' шт.', report_format_money($totalAmount)]);
    }
    if ($report === 'registrations') {
        $rows = [];
        $totalRegistrations = 0;
        foreach ($registrations as $r) {
            $regCount = (int)$r['registrations_count'];
            $rows[] = [app_format_date((string)$r['period_day']), $regCount . ' чел.'];
            $totalRegistrations += $regCount;
        }
        export_html_report('registrations', 'Регистрации по дням', 
            ['Дата', 'Регистраций, чел.'], $rows, $adminName,
            ['ИТОГО', $totalRegistrations . ' чел.']);
    }
    if ($report === 'promo_usage') {
        $rows = [];
        $totalOrders = 0;
        $totalDiscount = 0;
        foreach ($promoUsage as $r) {
            $ordersCount = (int)$r['orders_count'];
            $discount = (float)$r['discount_sum'];
            $rows[] = [$r['promo_code'], $ordersCount . ' шт.', report_format_money($discount)];
            $totalOrders += $ordersCount;
            $totalDiscount += $discount;
        }
        export_html_report('promo_usage', 'Использование промокодов', 
            ['Промокод', 'Заказов, шт.', 'Скидка, BYN'], $rows, $adminName,
            ['ИТОГО', $totalOrders . ' шт.', report_format_money($totalDiscount)]);
    }
    if ($report === 'repeat_users') {
        $rows = [];
        $totalSpent = 0;
        foreach ($repeatUsersDetail as $r) {
            $spent = (float)$r['total_spent'];
            $rows[] = [
                $r['user_name'],
                $r['order_date'],
                mb_substr($r['products'], 0, 150),
                (int)$r['orders_count'] . ' шт.',
                report_format_money($spent)
            ];
            $totalSpent += $spent;
        }
        export_html_report('repeat_users', 'Повторные покупатели (детальный отчет)', 
            ['Имя пользователя', 'Дата заказа', 'Товары', 'Всего заказов, шт.', 'Сумма, BYN'], $rows, $adminName,
            ['ИТОГО сумма', '', '', '', report_format_money($totalSpent)]);
    }
}

// HTML страница
admin_page_start('Отчеты');
$baseQs = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'status' => $status, 'category' => $category];

$sumOrders = 0;
$sumRevenue = 0;
foreach ($byCategory as $r) {
    $sumOrders += (int)$r['orders_count'];
    $sumRevenue += (float)$r['amount'];
}
?>
<style>
    .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .card h2 { margin-top: 0; margin-bottom: 16px; font-size: 18px; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px; }
    .card h3 { margin: 0 0 12px; font-size: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
    th { background: #f5f5f5; font-weight: 600; }
    .total-row { background: #f0f0f0; font-weight: bold; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; }
    .stat-muted { color: #666; font-size: 13px; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 800; }
    .filter-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: end; }
    .filter-form label { font-size: 12px; color: #666; display: block; margin-bottom: 4px; }
    .filter-form input, .filter-form select { width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; }
    .filter-form button { padding: 6px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .export-btn { display: inline-block; margin: 10px 0; padding: 6px 12px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; }
    .report-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; }
    .report-tab { border: 1px solid #dbe1ea; background: #f8fafc; color: #334155; border-radius: 999px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .report-tab.active { background: #2563eb; border-color: #2563eb; color: #fff; }
    .report-tab-panel { display: none; }
    .report-tab-panel.active { display: block; }
    .report-chart-block { margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
    .report-table-block { margin-top: 8px; }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } .filter-form { grid-template-columns: 1fr; } }
</style>

<div class="card">
    <h2>Фильтры отчета</h2>
    <form method="get" class="filter-form">
        <div><label>Дата от</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
        <div><label>Дата до</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
        <div><label>Статус</label><select name="status"><option value="">Все</option><?php foreach (['new','paid','shipped','completed'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $statusLabels[$s] ?></option><?php endforeach; ?></select></div>
        <div><label>Категория</label><select name="category"><option value="">Все</option><?php foreach ($categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars(app_category_label($mysqli, $cat)) ?></option><?php endforeach; ?></select></div>
        <div style="grid-column:1/-1;"><button type="submit">Применить</button></div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-muted">Заказов</div><div class="stat-value"><?= $sumOrders ?></div></div>
    <div class="stat-card"><div class="stat-muted">Выручка</div><div class="stat-value"><?= number_format($sumRevenue, 2, '.', ' ') ?> BYN</div></div>
    <div class="stat-card"><div class="stat-muted">Повторные покупатели</div><div class="stat-value"><?= $repeatUsersCount ?></div></div>
    <div class="stat-card"><div class="stat-muted">Промокодов</div><div class="stat-value"><?= count($promoUsage) ?></div></div>
</div>

<?php
$catTotalOrders = 0;
$catTotalAmount = 0;
foreach ($byCategory as $r) {
    $catTotalOrders += (int)$r['orders_count'];
    $catTotalAmount += (float)$r['amount'];
}
$prodTotalQty = 0;
$prodTotalAmount = 0;
foreach ($topProducts as $r) {
    $prodTotalQty += (int)$r['total_qty'];
    $prodTotalAmount += (float)$r['total_amount'];
}
$regTotal = 0;
foreach ($registrations as $r) {
    $regTotal += (int)$r['registrations_count'];
}
$promoTotalOrders = 0;
$promoTotalDiscount = 0;
foreach ($promoUsage as $r) {
    $promoTotalOrders += (int)$r['orders_count'];
    $promoTotalDiscount += (float)$r['discount_sum'];
}
$repeatTotalSpent = 0;
foreach ($repeatUsersDetail as $r) {
    $repeatTotalSpent += (float)$r['total_spent'];
}
?>

<div class="card">
    <h2>Отчёты</h2>
    <div class="report-tabs" role="tablist">
        <button type="button" class="report-tab active" data-report-tab="tab-products" role="tab">Самые продаваемые товары</button>
        <button type="button" class="report-tab" data-report-tab="tab-registrations" role="tab">Регистрации по дням</button>
        <button type="button" class="report-tab" data-report-tab="tab-category" role="tab">Заказы по категориям</button>
        <button type="button" class="report-tab" data-report-tab="tab-promo" role="tab">Промокоды</button>
        <button type="button" class="report-tab" data-report-tab="tab-repeat" role="tab">Повторные покупатели</button>
    </div>

    <div id="tab-products" class="report-tab-panel active" role="tabpanel">
        <div class="report-chart-block">
            <h3>График топ-10 товаров по продажам</h3>
            <button type="button" id="downloadTopProductsChart" class="export-btn" style="background: #16a34a;">Скачать график PNG</button>
            <canvas id="topProductsChart" height="120"></canvas>
        </div>
        <div class="report-table-block">
            <h3>Самые продаваемые товары</h3>
            <a href="<?= admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'top_products']))) ?>" class="export-btn">Экспорт Excel</a>
            <table>
                <thead>
                    <tr><th>Товар</th><th>Продано, шт.</th><th>Сумма, BYN</th></tr>
                </thead>
                <tbody>
                <?php foreach ($topProducts as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['product_name']) ?></td>
                        <td><?= (int)$r['total_qty'] ?> шт.</td>
                        <td><?= htmlspecialchars(report_format_money((float)$r['total_amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td><strong>ИТОГО</strong></td>
                        <td><strong><?= $prodTotalQty ?> шт.</strong></td>
                        <td><strong><?= htmlspecialchars(report_format_money($prodTotalAmount)) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab-registrations" class="report-tab-panel" role="tabpanel">
        <div class="report-chart-block">
            <h3>График регистраций по дням</h3>
            <button type="button" id="downloadRegistrationsChart" class="export-btn" style="background: #16a34a;">Скачать график PNG</button>
            <canvas id="registrationsChart" height="120"></canvas>
        </div>
        <div class="report-table-block">
            <h3>Регистрации по дням</h3>
            <a href="<?= admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'registrations']))) ?>" class="export-btn">Экспорт Excel</a>
            <?php if (empty($registrations)): ?>
                <p class="muted">Нет данных за выбранный период.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Дата</th><th>Регистраций, чел.</th></tr>
                </thead>
                <tbody>
                <?php foreach ($registrations as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars(app_format_date((string)$r['period_day'])) ?></td>
                        <td><?= (int)$r['registrations_count'] ?> чел.</td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td><strong>ИТОГО</strong></td>
                        <td><strong><?= $regTotal ?> чел.</strong></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div id="tab-category" class="report-tab-panel" role="tabpanel">
        <h3>Заказы по категориям</h3>
        <a href="<?= admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'by_category']))) ?>" class="export-btn">Экспорт Excel</a>
        <table>
            <thead>
                <tr><th>Категория</th><th>Заказов, шт.</th><th>Сумма, BYN</th></tr>
            </thead>
            <tbody>
            <?php foreach ($byCategory as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(app_category_label($mysqli, (string)$r['category_slug'])) ?></td>
                    <td><?= (int)$r['orders_count'] ?> шт.</td>
                    <td><?= htmlspecialchars(report_format_money((float)$r['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="total-row">
                    <td><strong>ИТОГО</strong></td>
                    <td><strong><?= $catTotalOrders ?> шт.</strong></td>
                    <td><strong><?= htmlspecialchars(report_format_money($catTotalAmount)) ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="tab-promo" class="report-tab-panel" role="tabpanel">
        <h3>Использование промокодов</h3>
        <a href="<?= admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'promo_usage']))) ?>" class="export-btn">Экспорт Excel</a>
        <table>
            <thead>
                <tr><th>Промокод</th><th>Заказов, шт.</th><th>Скидка, BYN</th></tr>
            </thead>
            <tbody>
            <?php foreach ($promoUsage as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['promo_code']) ?></td>
                    <td><?= (int)$r['orders_count'] ?> шт.</td>
                    <td><?= htmlspecialchars(report_format_money((float)$r['discount_sum'])) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="total-row">
                    <td><strong>ИТОГО</strong></td>
                    <td><strong><?= $promoTotalOrders ?> шт.</strong></td>
                    <td><strong><?= htmlspecialchars(report_format_money($promoTotalDiscount)) ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="tab-repeat" class="report-tab-panel" role="tabpanel">
        <h3>Повторные покупатели (детальный отчёт)</h3>
        <a href="<?= admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'repeat_users']))) ?>" class="export-btn">Экспорт Excel</a>
        <p class="muted">Пользователей с более чем 1 заказом: <strong><?= $repeatUsersCount ?> чел.</strong></p>
        <?php if (empty($repeatUsersDetail)): ?>
            <p class="muted">Нет данных за выбранный период.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>Имя пользователя</th><th>Дата заказа</th><th>Товары</th><th>Всего заказов, шт.</th><th>Сумма, BYN</th></tr>
                </thead>
                <tbody>
                <?php foreach ($repeatUsersDetail as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['user_name']) ?></td>
                        <td><?= htmlspecialchars($r['order_date']) ?></td>
                        <td><?= htmlspecialchars(mb_substr($r['products'], 0, 80)) ?><?= mb_strlen($r['products']) > 80 ? '...' : '' ?></td>
                        <td><?= (int)$r['orders_count'] ?> шт.</td>
                        <td><?= htmlspecialchars(report_format_money((float)$r['total_spent'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4"><strong>ИТОГО сумма</strong></td>
                        <td><strong><?= htmlspecialchars(report_format_money($repeatTotalSpent)) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const regLabels = <?= json_encode(array_map(function ($r) {
        return app_format_date((string)($r['period_day'] ?? ''));
    }, $registrations), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    const regData = <?= json_encode(array_map('intval', array_column($registrations, 'registrations_count')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    const topItems = <?= json_encode(array_slice($topProducts, 0, 10), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    const topLabels = topItems.map(function(x) { return String(x.product_name || '').slice(0, 30); });
    const topData = topItems.map(function(x) { return parseInt(x.total_qty || 0, 10); });

    let regChart = null;
    let topChart = null;

    const regCanvas = document.getElementById('registrationsChart');
    if (regCanvas && regLabels.length > 0) {
        regChart = new Chart(regCanvas, {
            type: 'line',
            data: {
                labels: regLabels,
                datasets: [{
                    label: 'Регистрации, чел.',
                    data: regData,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.12)',
                    fill: true,
                    tension: 0.25
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
    }

    const topCanvas = document.getElementById('topProductsChart');
    if (topCanvas && topLabels.length > 0) {
        topChart = new Chart(topCanvas, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: 'Продано, шт.',
                    data: topData,
                    backgroundColor: '#f97316'
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
    }

    function downloadChart(chart, filename) {
        if (!chart) return;
        const link = document.createElement('a');
        link.href = chart.toBase64Image('image/png', 1);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    const downloadRegBtn = document.getElementById('downloadRegistrationsChart');
    const downloadTopBtn = document.getElementById('downloadTopProductsChart');
    if (downloadRegBtn) {
        downloadRegBtn.addEventListener('click', function() { downloadChart(regChart, 'registrations-chart.png'); });
    }
    if (downloadTopBtn) {
        downloadTopBtn.addEventListener('click', function() { downloadChart(topChart, 'top-products-chart.png'); });
    }

    const tabButtons = document.querySelectorAll('[data-report-tab]');
    const tabPanels = document.querySelectorAll('.report-tab-panel');
    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = btn.getAttribute('data-report-tab');
            tabButtons.forEach(function(b) { b.classList.remove('active'); });
            tabPanels.forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            const panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');
            if (targetId === 'tab-registrations' && regChart) regChart.resize();
            if (targetId === 'tab-products' && topChart) topChart.resize();
        });
    });
})();
</script>

<?php admin_page_end(); ?>