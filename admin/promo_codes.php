<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';

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

$promoCodes = [];
if (db_table_exists($mysqli, 'promo_codes')) {
    $res = $mysqli->query("SELECT * FROM promo_codes ORDER BY id DESC LIMIT 500");
    while ($res && ($row = $res->fetch_assoc())) $promoCodes[] = $row;
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
    <?php if (!db_table_exists($mysqli, 'promo_codes')): ?>
        <p class="muted">Таблица promo_codes не найдена. Запусти миграции.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>CODE</th>
                <th>TYPE</th>
                <th>VALUE</th>
                <th>ACTIVE</th>
                <th>USES</th>
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
                                <option value="percent" <?= ((string)$p['type'] === 'percent') ? 'selected' : '' ?>>percent</option>
                                <option value="fixed" <?= ((string)$p['type'] === 'fixed') ? 'selected' : '' ?>>fixed</option>
                            </select>
                    </td>
                    <td>
                            <input name="value" type="number" step="0.01" value="<?= htmlspecialchars((string)$p['value']) ?>" style="max-width:120px;">
                    </td>
                    <td>
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="active" value="1" <?= !empty($p['active']) ? 'checked' : '' ?> style="width:auto;">
                                active
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

<?php admin_page_end(); ?>

