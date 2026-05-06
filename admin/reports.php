<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';

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

function bind_dynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') return;
    $stmt->bind_param($types, ...$params);
}

function export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

// 1) Статистика по заказам в разрезе категорий и периодов.
$categoryWhere = $ordersWhere;
$categoryParams = $ordersParams;
$categoryTypes = $ordersTypes;
if ($category !== '') {
    $categoryWhere[] = 'm.filtr = ?';
    $categoryParams[] = $category;
    $categoryTypes .= 's';
}
$categoryWhereSql = $categoryWhere ? ('WHERE ' . implode(' AND ', $categoryWhere)) : '';
$sqlByCategory = "SELECT COALESCE(m.filtr, 'Без категории') AS category_slug, COUNT(DISTINCT o.id) AS orders_count, SUM(oi.line_total) AS amount
                  FROM orders o
                  LEFT JOIN order_items oi ON oi.order_id = o.id
                  LEFT JOIN medicator m ON m.id = oi.product_id
                  $categoryWhereSql
                  GROUP BY category_slug
                  ORDER BY amount DESC";
$stmt = $mysqli->prepare($sqlByCategory);
bind_dynamic($stmt, $categoryTypes, $categoryParams);
$stmt->execute();
$res = $stmt->get_result();
$byCategory = [];
while ($res && ($row = $res->fetch_assoc())) $byCategory[] = $row;
$stmt->close();

// 2) Топ продаваемых товаров.
$productWhere = $ordersWhere ? ('WHERE ' . implode(' AND ', $ordersWhere)) : '';
$sqlTopProducts = "SELECT oi.product_name_snapshot AS product_name, SUM(oi.qty) AS total_qty, SUM(oi.line_total) AS total_amount
                   FROM orders o
                   JOIN order_items oi ON oi.order_id = o.id
                   $productWhere
                   GROUP BY oi.product_name_snapshot
                   ORDER BY total_qty DESC, total_amount DESC
                   LIMIT 30";
$stmt = $mysqli->prepare($sqlTopProducts);
bind_dynamic($stmt, $ordersTypes, $ordersParams);
$stmt->execute();
$res = $stmt->get_result();
$topProducts = [];
while ($res && ($row = $res->fetch_assoc())) $topProducts[] = $row;
$stmt->close();

// 3) График регистраций пользователей (по месяцам).
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
$sqlRegistrations = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period_month, COUNT(*) AS registrations_count
                     FROM `user`
                     $userWhereSql
                     GROUP BY period_month
                     ORDER BY period_month ASC";
$stmt = $mysqli->prepare($sqlRegistrations);
bind_dynamic($stmt, $userTypes, $userParams);
$stmt->execute();
$res = $stmt->get_result();
$registrations = [];
while ($res && ($row = $res->fetch_assoc())) $registrations[] = $row;
$stmt->close();

// 4) Использованные акции/промокоды и скидки.
$sqlPromo = "SELECT COALESCE(pc.code, 'Без промокода') AS promo_code, COUNT(o.id) AS orders_count, SUM(o.discount_total) AS discount_sum
             FROM orders o
             LEFT JOIN promo_codes pc ON pc.id = o.promo_code_id
             $ordersWhereSql
             GROUP BY promo_code
             ORDER BY discount_sum DESC, orders_count DESC";
$stmt = $mysqli->prepare($sqlPromo);
bind_dynamic($stmt, $ordersTypes, $ordersParams);
$stmt->execute();
$res = $stmt->get_result();
$promoUsage = [];
while ($res && ($row = $res->fetch_assoc())) $promoUsage[] = $row;
$stmt->close();

// 5) Пользователи, вернувшиеся повторно.
$sqlRepeatUsers = "SELECT COUNT(*) AS repeat_users_count
                   FROM (
                     SELECT o.user_id
                     FROM orders o
                     $ordersWhereSql
                     AND o.user_id IS NOT NULL
                     GROUP BY o.user_id
                     HAVING COUNT(*) > 1
                   ) t";
if ($ordersWhereSql === '') {
    $sqlRepeatUsers = "SELECT COUNT(*) AS repeat_users_count
                       FROM (
                         SELECT o.user_id
                         FROM orders o
                         WHERE o.user_id IS NOT NULL
                         GROUP BY o.user_id
                         HAVING COUNT(*) > 1
                       ) t";
}
$stmt = $mysqli->prepare($sqlRepeatUsers);
bind_dynamic($stmt, $ordersTypes, $ordersParams);
$stmt->execute();
$res = $stmt->get_result();
$repeatUsers = (int)(($res ? $res->fetch_assoc()['repeat_users_count'] : 0) ?? 0);
$stmt->close();

if ($export && $report !== '') {
    if ($report === 'by_category') {
        $rows = [];
        foreach ($byCategory as $r) {
            $rows[] = [$r['category_slug'], (int)$r['orders_count'], (float)$r['amount']];
        }
        export_csv('orders_by_category.csv', ['Категория', 'Кол-во заказов', 'Сумма'], $rows);
    }
    if ($report === 'top_products') {
        $rows = [];
        foreach ($topProducts as $r) {
            $rows[] = [$r['product_name'], (int)$r['total_qty'], (float)$r['total_amount']];
        }
        export_csv('top_products.csv', ['Товар', 'Продано шт.', 'Сумма'], $rows);
    }
    if ($report === 'registrations') {
        $rows = [];
        foreach ($registrations as $r) {
            $rows[] = [$r['period_month'], (int)$r['registrations_count']];
        }
        export_csv('registrations.csv', ['Период', 'Регистраций'], $rows);
    }
    if ($report === 'promo_usage') {
        $rows = [];
        foreach ($promoUsage as $r) {
            $rows[] = [$r['promo_code'], (int)$r['orders_count'], (float)$r['discount_sum']];
        }
        export_csv('promo_usage.csv', ['Промокод', 'Заказов', 'Сумма скидки'], $rows);
    }
}

admin_page_start('Отчеты');
$baseQs = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'status' => $status,
    'category' => $category,
];
$statusLabels = ['new' => 'Новый', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'completed' => 'Завершен'];
$sumOrders = 0;
$sumRevenue = 0.0;
foreach ($byCategory as $r) {
    $sumOrders += (int)$r['orders_count'];
    $sumRevenue += (float)$r['amount'];
}
?>
<div class="card">
    <h2>Фильтры отчета</h2>
    <form method="get" class="grid">
        <div>
            <label>Дата от</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div>
            <label>Дата до</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div>
            <label>Статус заказа</label>
            <select name="status">
                <option value="">Все</option>
                <?php foreach (['new','paid','shipped','completed'] as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= htmlspecialchars($statusLabels[$s]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Категория</label>
            <select name="category">
                <option value="">Все</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="grid-column:1/-1;">
            <button type="submit">Применить</button>
        </div>
    </form>
</div>

<div class="card" style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:12px;">
    <div><div class="muted">Заказов (по категориям)</div><div style="font-size:28px;font-weight:800;"><?= (int)$sumOrders ?></div></div>
    <div><div class="muted">Выручка</div><div style="font-size:28px;font-weight:800;"><?= htmlspecialchars(number_format($sumRevenue, 2, '.', ' ')) ?> BYN</div></div>
    <div><div class="muted">Повторные покупатели</div><div style="font-size:28px;font-weight:800;"><?= (int)$repeatUsers ?></div></div>
    <div><div class="muted">Использовано промокодов</div><div style="font-size:28px;font-weight:800;"><?= count($promoUsage) ?></div></div>
</div>

<div class="card">
    <h2>График регистраций</h2>
    <p><button type="button" id="downloadRegistrationsChart">Скачать график PNG</button></p>
    <canvas id="registrationsChart" height="120"></canvas>
</div>

<div class="card">
    <h2>График топ-10 товаров по продажам</h2>
    <p><button type="button" id="downloadTopProductsChart">Скачать график PNG</button></p>
    <canvas id="topProductsChart" height="120"></canvas>
</div>

<div class="card">
    <h2>Статистика заказов по категориям</h2>
    <p><a href="<?= htmlspecialchars(admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'by_category']))), ENT_QUOTES, 'UTF-8') ?>">Экспорт CSV (MS Office / Excel)</a></p>
    <table>
        <tr><th>Категория</th><th>Кол-во заказов</th><th>Сумма</th></tr>
        <?php foreach ($byCategory as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string)$r['category_slug']) ?></td>
                <td><?= (int)$r['orders_count'] ?></td>
                <td><?= htmlspecialchars((string)$r['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>Самые продаваемые товары</h2>
    <p><a href="<?= htmlspecialchars(admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'top_products']))), ENT_QUOTES, 'UTF-8') ?>">Экспорт CSV (MS Office / Excel)</a></p>
    <table>
        <tr><th>Товар</th><th>Продано шт.</th><th>Сумма</th></tr>
        <?php foreach ($topProducts as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string)$r['product_name']) ?></td>
                <td><?= (int)$r['total_qty'] ?></td>
                <td><?= htmlspecialchars((string)$r['total_amount']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>Регистрации пользователей по месяцам</h2>
    <p><a href="<?= htmlspecialchars(admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'registrations']))), ENT_QUOTES, 'UTF-8') ?>">Экспорт CSV (MS Office / Excel)</a></p>
    <table>
        <tr><th>Период</th><th>Регистраций</th></tr>
        <?php foreach ($registrations as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string)$r['period_month']) ?></td>
                <td><?= (int)$r['registrations_count'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>Использование промокодов и скидок</h2>
    <p><a href="<?= htmlspecialchars(admin_url('reports.php?' . http_build_query(array_merge($baseQs, ['export' => 1, 'report' => 'promo_usage']))), ENT_QUOTES, 'UTF-8') ?>">Экспорт CSV (MS Office / Excel)</a></p>
    <table>
        <tr><th>Промокод</th><th>Заказов</th><th>Сумма скидки</th></tr>
        <?php foreach ($promoUsage as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string)$r['promo_code']) ?></td>
                <td><?= (int)$r['orders_count'] ?></td>
                <td><?= htmlspecialchars((string)$r['discount_sum']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>Повторные покупатели</h2>
    <p>Пользователей с более чем 1 заказом за выбранный период: <strong><?= (int)$repeatUsers ?></strong></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function(){
    var regLabels = <?= json_encode(array_column($registrations, 'period_month'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var regData = <?= json_encode(array_map('intval', array_column($registrations, 'registrations_count')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var topItems = <?= json_encode(array_slice($topProducts, 0, 10), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var topLabels = topItems.map(function(x){ return String(x.product_name || '').slice(0, 24); });
    var topData = topItems.map(function(x){ return parseInt(x.total_qty || 0, 10); });

    var regCanvas = document.getElementById('registrationsChart');
    var regChart = null;
    if (regCanvas) {
      regChart = new Chart(regCanvas, {
        type: 'line',
        data: { labels: regLabels, datasets: [{ label: 'Регистрации', data: regData, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .25 }] },
        options: { responsive: true, plugins: { legend: { display: false } } }
      });
    }

    var topCanvas = document.getElementById('topProductsChart');
    var topChart = null;
    if (topCanvas) {
      topChart = new Chart(topCanvas, {
        type: 'bar',
        data: { labels: topLabels, datasets: [{ label: 'Продано шт.', data: topData, backgroundColor: '#f97316' }] },
        options: { responsive: true, plugins: { legend: { display: false } } }
      });
    }
    function downloadChart(chart, filename) {
      if (!chart) return;
      var a = document.createElement('a');
      a.href = chart.toBase64Image('image/png', 1);
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }
    var dlReg = document.getElementById('downloadRegistrationsChart');
    if (dlReg) dlReg.addEventListener('click', function(){ downloadChart(regChart, 'registrations-chart.png'); });
    var dlTop = document.getElementById('downloadTopProductsChart');
    if (dlTop) dlTop.addEventListener('click', function(){ downloadChart(topChart, 'top-products-chart.png'); });
  })();
</script>
<?php admin_page_end(); ?>
