<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/auth_lib.php';
require_once __DIR__ . '/../includes/mail_lib.php';

$msg = '';

if (isset($_POST['create'])) {
    $code = mb_strtoupper(trim((string)($_POST['code'] ?? '')), 'UTF-8');
    $type = trim((string)($_POST['type'] ?? 'percent'));
    $value = (float)($_POST['value'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    if ($code !== '' && in_array($type, ['percent', 'fixed'], true)) {
        $stmt = $mysqli->prepare("INSERT INTO promo_codes (code, type, value, active) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssdi', $code, $type, $value, $active);
            $stmt->execute();
            $stmt->close();
            $msg = 'Промокод создан';
        } else {
            $msg = 'Нужны миграции promo_codes';
        }
    }
}

if (isset($_POST['delete'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM promo_codes WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Удалено';
        }
    }
}

if (isset($_POST['update'])) {
    $id = (int)($_POST['id'] ?? 0);
    $type = trim((string)($_POST['type'] ?? 'percent'));
    $value = (float)($_POST['value'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    if ($id > 0 && in_array($type, ['percent', 'fixed'], true)) {
        $stmt = $mysqli->prepare("UPDATE promo_codes SET type = ?, value = ?, active = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('sdii', $type, $value, $active, $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Обновлено';
        }
    }
}

if (isset($_POST['send_promo'])) {
    $promoId = (int)($_POST['promo_id'] ?? 0);
    $customEmail = app_normalize_email((string)($_POST['to_email'] ?? ''));
    $sendAll = isset($_POST['send_all']) ? 1 : 0;
    $emails = [];

    if ($sendAll) {
        $resUsers = $mysqli->query("SELECT email FROM `user` WHERE email IS NOT NULL AND email <> '' LIMIT 2000");
        while ($resUsers && ($row = $resUsers->fetch_assoc())) {
            $mail = app_normalize_email((string)($row['email'] ?? ''));
            if (app_is_email($mail)) $emails[$mail] = true;
        }
    } elseif ($customEmail !== '' && app_is_email($customEmail)) {
        $emails[$customEmail] = true;
    }

    $stmtPromo = $mysqli->prepare("SELECT code, type, value FROM promo_codes WHERE id = ? LIMIT 1");
    $promo = null;
    if ($stmtPromo) {
        $stmtPromo->bind_param('i', $promoId);
        $stmtPromo->execute();
        $resPromo = $stmtPromo->get_result();
        $promo = $resPromo ? $resPromo->fetch_assoc() : null;
        $stmtPromo->close();
    }

    if ($promo && count($emails) > 0) {
        $sentCount = 0;
        $failCount = 0;
        $kind = ((string)$promo['type'] === 'fixed') ? ('-' . (float)$promo['value']) : ((float)$promo['value'] . '%');
        $subject = 'Промокод для вас на ' . app_site_host();
        foreach (array_keys($emails) as $email) {
            $body = "Здравствуйте!\n\nДарим вам промокод: {$promo['code']}\nТип скидки: {$kind}\n\nПриятных покупок!\n";
            $mailError = null;
            $sent = app_send_mail($email, $subject, $body, $mailError);
            app_log_email_attempt($mysqli, $email, $subject, $body, $sent, $mailError);
            if ($sent) $sentCount++; else $failCount++;
        }
        $msg = "Рассылка завершена. Отправлено: {$sentCount}, ошибок: {$failCount}";
    } else {
        $msg = 'Выбери промокод и получателя(ей)';
    }
}

$promoCodes = [];
if (db_table_exists($mysqli, 'promo_codes')) {
    $perPage = 20;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    $countRes = $mysqli->query("SELECT COUNT(*) AS cnt FROM promo_codes");
    $totalRows = (int)(($countRes ? $countRes->fetch_assoc()['cnt'] : 0) ?? 0);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    $stmtPage = $mysqli->prepare("SELECT * FROM promo_codes ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmtPage->bind_param('ii', $perPage, $offset);
    $stmtPage->execute();
    $res = $stmtPage->get_result();
    while ($res && ($row = $res->fetch_assoc())) $promoCodes[] = $row;
    $stmtPage->close();
}

admin_page_start('Промокоды');
?>

<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
    <h2>Создать промокод</h2>
    <form method="post" class="grid">
        <div>
            <label>Код</label>
            <input name="code" required placeholder="SALE10">
        </div>
        <div>
            <label>Тип</label>
            <select name="type">
                <option value="percent">percent</option>
                <option value="fixed">fixed</option>
            </select>
        </div>
        <div>
            <label>Значение</label>
            <input name="value" type="number" step="0.01" value="10">
        </div>
        <div>
            <label>&nbsp;</label>
            <label style="display:flex;gap:8px;align-items:center;">
                <input type="checkbox" name="active" value="1" checked style="width:auto;"> Активен
            </label>
        </div>
        <div style="grid-column:1/-1;">
            <button type="submit" name="create" value="1">Создать</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Список промокодов</h2>
    <?php if (isset($totalRows)): ?>
        <p class="muted">Всего: <?= (int)$totalRows ?> · Страница <?= (int)$page ?> из <?= (int)$totalPages ?></p>
    <?php endif; ?>
    <?php if (!db_table_exists($mysqli, 'promo_codes')): ?>
        <p class="muted">Таблица promo_codes не найдена. Запусти миграции.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Код</th>
                <th>Тип</th>
                <th>Значение</th>
                <th>Активность</th>
                <th>Использования</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($promoCodes as $p): ?>
                <tr>
                    <td>#<?= (int)$p['id'] ?></td>
                    <td><strong><?= htmlspecialchars((string)$p['code']) ?></strong></td>
                    <td>
                        <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <select name="type" style="max-width:140px;">
                                <option value="percent" <?= ((string)$p['type'] === 'percent') ? 'selected' : '' ?>>процент</option>
                                <option value="fixed" <?= ((string)$p['type'] === 'fixed') ? 'selected' : '' ?>>фикс</option>
                            </select>
                    </td>
                    <td>
                            <input name="value" type="number" step="0.01" value="<?= htmlspecialchars((string)$p['value']) ?>" style="max-width:120px;">
                    </td>
                    <td>
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="active" value="1" <?= !empty($p['active']) ? 'checked' : '' ?> style="width:auto;">
                                активен
                            </label>
                    </td>
                    <td class="muted"><?= (int)($p['uses_count'] ?? 0) ?> / <?= htmlspecialchars((string)($p['max_uses'] ?? '∞')) ?></td>
                    <td>
                            <button type="submit" name="update" value="1">Сохранить</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Удалить промокод?');" style="margin-top:6px;">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" name="delete" value="1" class="danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php if (isset($totalPages) && $totalPages > 1): ?>
<div class="card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(admin_url('promo_codes.php?page=' . ($page - 1)), ENT_QUOTES, 'UTF-8') ?>">← Назад</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(admin_url('promo_codes.php?page=' . ($page + 1)), ENT_QUOTES, 'UTF-8') ?>">Вперед →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>Рассылка промокодов</h2>
    <form method="post" class="grid">
        <div>
            <label>Промокод</label>
            <select name="promo_id" required>
                <option value="">Выбери промокод</option>
                <?php foreach ($promoCodes as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['code']) ?> (<?= htmlspecialchars((string)$p['type']) ?> <?= htmlspecialchars((string)$p['value']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Email (если не всем)</label>
            <input type="email" name="to_email" placeholder="example@gmail.com">
        </div>
        <div style="grid-column:1/-1;">
            <label style="display:flex;gap:8px;align-items:center;">
                <input type="checkbox" name="send_all" value="1" style="width:auto;">
                Отправить всем пользователям с email
            </label>
        </div>
        <div style="grid-column:1/-1;">
            <button type="submit" name="send_promo" value="1">Запустить рассылку</button>
        </div>
    </form>
</div>

<?php admin_page_end(); ?>

