<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/migrations.php';
require_once __DIR__ . '/../includes/auth_lib.php';
require_once __DIR__ . '/../includes/mail_lib.php';

$msg = '';

function validate_promo_value(string $type, float $value): ?string
{
    if ($value < 0) {
        return 'Скидка не может быть меньше нуля';
    }
    if ($type === 'percent' && $value > 99) {
        return 'Процент скидки не может быть больше 99%';
    }
    return null;
}

if (isset($_POST['create'])) {
    $code = mb_strtoupper(trim((string)($_POST['code'] ?? '')), 'UTF-8');
    $type = trim((string)($_POST['type'] ?? 'percent'));
    $value = (float)($_POST['value'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    
    if ($code !== '' && in_array($type, ['percent', 'fixed'], true)) {
        $promoError = validate_promo_value($type, $value);
        if ($promoError !== null) {
            $msg = 'Ошибка: ' . $promoError;
        } else {
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
        $promoError = validate_promo_value($type, $value);
        if ($promoError !== null) {
            $msg = 'Ошибка: ' . $promoError;
        } else {
            $stmt = $mysqli->prepare("UPDATE promo_codes SET type = ?, value = ?, active = ? WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('sdii', $type, $value, $active, $id);
                $stmt->execute();
                $stmt->close();
                $msg = 'Обновлено';
            }
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
            <select name="type" id="promo_type_create">
                <option value="percent">Процент (макс. 99%)</option>
                <option value="fixed">Фиксированная сумма</option>
            </select>
        </div>
        <div>
            <label>Значение</label>
            <input name="value" type="number" step="0.01" min="0" value="10" id="promo_value_create" required>
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
        <div style="overflow-x: auto; overflow-y: clip;">
            <table style="min-width: 800px;">
                <thead>
                    <tr>
                        <th>Номер</th>
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
                                <select name="type" style="max-width:140px;" class="promo_type_update" data-id="<?= (int)$p['id'] ?>">
                                    <option value="percent" <?= ((string)$p['type'] === 'percent') ? 'selected' : '' ?>>Процент (макс. 99%)</option>
                                    <option value="fixed" <?= ((string)$p['type'] === 'fixed') ? 'selected' : '' ?>>Фиксированная сумма</option>
                                </select>
                        </td>
                        <td>
                                <input name="value" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)$p['value']) ?>" style="max-width:120px;" class="promo_value_update" data-id="<?= (int)$p['id'] ?>" required>
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
        </div>
    <?php endif; ?>
</div>

<script>
// Клиентская валидация на лету (чтобы дурак не ввёл 100%)
document.addEventListener('DOMContentLoaded', function() {
    // Для формы создания
    const createType = document.getElementById('promo_type_create');
    const createValue = document.getElementById('promo_value_create');
    
    function validatePromoValue(valueInput, typeSelect) {
        if (!valueInput) return true;
        const val = parseFloat(valueInput.value);
        if (isNaN(val) || val < 0) {
            valueInput.setCustomValidity('Скидка не может быть меньше нуля');
            valueInput.style.borderColor = '#c62828';
            return false;
        }
        if (typeSelect && typeSelect.value === 'percent' && val > 99) {
            valueInput.setCustomValidity('Процент скидки не может быть больше 99%');
            valueInput.style.borderColor = '#c62828';
            return false;
        }
        valueInput.setCustomValidity('');
        valueInput.style.borderColor = '';
        return true;
    }
    
    if (createType && createValue) {
        createType.addEventListener('change', function() {
            if (createType.value === 'percent') {
                createValue.max = 99;
                if (parseFloat(createValue.value) > 99) createValue.value = 99;
            } else {
                createValue.max = '';
                createValue.removeAttribute('max');
            }
            validatePromoValue(createValue, createType);
        });
        createValue.addEventListener('input', function() {
            validatePromoValue(createValue, createType);
        });
        if (createType.value === 'percent') {
            createValue.max = 99;
            createValue.min = 0;
        }
    }
    
    // Для каждой строки обновления
    document.querySelectorAll('.promo_type_update').forEach(function(select) {
        const id = select.getAttribute('data-id');
        const valueInput = document.querySelector('.promo_value_update[data-id="' + id + '"]');
        
        function validateRow() {
            validatePromoValue(valueInput, select);
        }
        
        if (select && valueInput) {
            select.addEventListener('change', function() {
                if (select.value === 'percent') {
                    valueInput.max = 99;
                    if (parseFloat(valueInput.value) > 99) valueInput.value = 99;
                } else {
                    valueInput.removeAttribute('max');
                }
                validateRow();
            });
            valueInput.addEventListener('input', validateRow);
            valueInput.min = 0;
            if (select.value === 'percent') valueInput.max = 99;
            validateRow();
        }
    });

    document.querySelectorAll('form').forEach(function(form) {
        var typeSelect = form.querySelector('#promo_type_create, .promo_type_update');
        var valueInput = form.querySelector('#promo_value_create, .promo_value_update');
        if (!typeSelect || !valueInput) return;
        form.addEventListener('submit', function(e) {
            if (!validatePromoValue(valueInput, typeSelect)) {
                e.preventDefault();
                valueInput.reportValidity();
            }
        });
    });
});
</script>

<div class="card">
    <h2>Рассылка промокодов</h2>
    <form method="post" class="grid">
        <div>
            <label>Промокод</label>
            <select name="promo_id" required>
                <option value="">Выбери промокод</option>
                <?php foreach ($promoCodes as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['code']) ?> (<?= htmlspecialchars(app_promo_type_label((string)$p['type'])) ?> <?= htmlspecialchars((string)$p['value']) ?>)</option>
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