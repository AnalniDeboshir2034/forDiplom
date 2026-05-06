<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';

$msg = '';

if (isset($_POST['moderate'])) {
    $id = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    if ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $adminId = 0;
        $stmt = $mysqli->prepare("UPDATE reviews SET status = ?, moderated_at = NOW(), moderated_by = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('sii', $status, $adminId, $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Обновлено';
        }
    }
}

$statusFilter = trim((string)($_GET['status'] ?? 'pending'));
$allowed = ['pending', 'approved', 'rejected', ''];
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'pending';

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalRows = 0;
$totalPages = 1;

$reviews = [];
function admin_review_status_label(string $status): string
{
    $map = ['pending' => 'На модерации', 'approved' => 'Одобрен', 'rejected' => 'Отклонен'];
    return $map[$status] ?? $status;
}
if (db_table_exists($mysqli, 'reviews')) {
    if ($statusFilter === '') {
        $countRes = $mysqli->query("SELECT COUNT(*) AS cnt FROM reviews");
        $totalRows = (int)(($countRes ? $countRes->fetch_assoc()['cnt'] : 0) ?? 0);
    } else {
        $countStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM reviews WHERE status = ?");
        $countStmt->bind_param('s', $statusFilter);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalRows = (int)(($countRes ? $countRes->fetch_assoc()['cnt'] : 0) ?? 0);
        $countStmt->close();
    }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    if ($statusFilter === '') {
        $stmt = $mysqli->prepare("SELECT r.*, u.login AS user_login FROM reviews r LEFT JOIN user u ON u.id = r.user_id ORDER BY r.id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $stmt = $mysqli->prepare("SELECT r.*, u.login AS user_login FROM reviews r LEFT JOIN user u ON u.id = r.user_id WHERE r.status = ? ORDER BY r.id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('sii', $statusFilter, $perPage, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    while ($res && ($row = $res->fetch_assoc())) $reviews[] = $row;
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
}

admin_page_start('Отзывы');
?>

<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
    <h2>Модерация отзывов</h2>
    <p class="muted">Фильтр:
        <a href="<?= htmlspecialchars(admin_url('reviews.php?status=pending'), ENT_QUOTES, 'UTF-8') ?>">на модерации</a> ·
        <a href="<?= htmlspecialchars(admin_url('reviews.php?status=approved'), ENT_QUOTES, 'UTF-8') ?>">одобренные</a> ·
        <a href="<?= htmlspecialchars(admin_url('reviews.php?status=rejected'), ENT_QUOTES, 'UTF-8') ?>">отклоненные</a> ·
        <a href="<?= htmlspecialchars(admin_url('reviews.php?status='), ENT_QUOTES, 'UTF-8') ?>">все</a>
    </p>
    <p class="muted">Всего: <?= (int)$totalRows ?> · Страница <?= (int)$page ?> из <?= (int)$totalPages ?></p>

    <?php if (!db_table_exists($mysqli, 'reviews')): ?>
        <p class="muted">Таблица reviews не найдена. Запусти миграции.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Заказ</th>
                <th>Пользователь</th>
                <th>Оценка</th>
                <th>Текст</th>
                <th>Статус</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td>#<?= (int)$r['order_id'] ?></td>
                    <td><?= htmlspecialchars((string)($r['user_login'] ?? '')) ?></td>
                    <td><?= (int)$r['rating'] ?></td>
                    <td><?= nl2br(htmlspecialchars((string)$r['text'])) ?></td>
                    <td><?= htmlspecialchars(admin_review_status_label((string)$r['status'])) ?></td>
                    <td>
                        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" name="moderate" value="1" class="btn" style="background:#16a34a;">Одобрить</button>
                        </form>
                        <form method="post" style="margin-top:6px;">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" name="moderate" value="1" class="danger">Отклонить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <?php
        $buildReviewPageUrl = function (int $targetPage) use ($statusFilter): string {
            $qs = ['page' => $targetPage, 'status' => $statusFilter];
            return admin_url('reviews.php?' . http_build_query($qs));
        };
        ?>
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($buildReviewPageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">← Назад</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildReviewPageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Вперед →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php admin_page_end(); ?>

