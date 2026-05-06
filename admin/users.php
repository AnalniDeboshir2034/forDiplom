<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();
require_once __DIR__ . '/../includes/auth_lib.php';
require_once __DIR__ . '/../includes/mail_lib.php';

$msg = '';

if (isset($_POST['update_user'])) {
    $id = (int)($_POST['id'] ?? 0);
    $login = trim((string)($_POST['login'] ?? ''));
    $email = app_normalize_email((string)($_POST['email'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $unp = trim((string)($_POST['unp'] ?? ''));
    $accountType = trim((string)($_POST['account_type'] ?? 'individual'));
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $representativeName = trim((string)($_POST['representative_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $role = trim((string)($_POST['role'] ?? 'user'));
    $allowedRoles = ['user', 'admin'];
    $allowedAccountTypes = ['individual', 'legal'];

    if ($id > 0 && $login !== '' && app_is_email($email) && in_array($role, $allowedRoles, true) && in_array($accountType, $allowedAccountTypes, true)) {
        $stmt = $mysqli->prepare("UPDATE `user` SET `login` = ?, `email` = ?, `name` = ?, `unp` = ?, `role` = ?, `account_type` = ?, `company_name` = ?, `representative_name` = ?, `phone` = ?, `address` = ? WHERE `id` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ssssssssssi', $login, $email, $name, $unp, $role, $accountType, $companyName, $representativeName, $phone, $address, $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Пользователь обновлен';
        } else {
            $stmtLegacy = $mysqli->prepare("UPDATE `user` SET `login` = ?, `email` = ?, `name` = ?, `unp` = ?, `role` = ? WHERE `id` = ? LIMIT 1");
            if ($stmtLegacy) {
                $stmtLegacy->bind_param('sssssi', $login, $email, $name, $unp, $role, $id);
                $stmtLegacy->execute();
                $stmtLegacy->close();
                $msg = 'Пользователь обновлен';
            } else {
                $msg = 'Ошибка БД при обновлении пользователя';
            }
        }
    } else {
        $msg = 'Проверь данные пользователя (логин/email/роль)';
    }
}

if (isset($_POST['send_user_promo'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $promoId = (int)($_POST['promo_id'] ?? 0);
    if ($userId > 0 && $promoId > 0) {
        $stmtU = $mysqli->prepare("SELECT email FROM `user` WHERE id = ? LIMIT 1");
        $stmtU->bind_param('i', $userId);
        $stmtU->execute();
        $resU = $stmtU->get_result();
        $u = $resU ? $resU->fetch_assoc() : null;
        $stmtU->close();

        $stmtP = $mysqli->prepare("SELECT code, type, value FROM promo_codes WHERE id = ? LIMIT 1");
        $stmtP->bind_param('i', $promoId);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        $p = $resP ? $resP->fetch_assoc() : null;
        $stmtP->close();

        $to = app_normalize_email((string)($u['email'] ?? ''));
        if ($p && app_is_email($to)) {
            $kind = ((string)$p['type'] === 'fixed') ? ('-' . (float)$p['value']) : ((float)$p['value'] . '%');
            $subject = 'Промокод для вас на ' . app_site_host();
            $body = "Здравствуйте!\n\nВаш персональный промокод: {$p['code']}\nСкидка: {$kind}\n\nПриятных покупок!";
            $mailError = null;
            $sent = app_send_mail($to, $subject, $body, $mailError);
            app_log_email_attempt($mysqli, $to, $subject, $body, $sent, $mailError);
            $msg = $sent ? 'Промокод отправлен пользователю' : ('Ошибка отправки: ' . ($mailError ?: 'mail() returned false'));
        } else {
            $msg = 'Не найден email пользователя или промокод';
        }
    }
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countRes = $mysqli->query("SELECT COUNT(*) AS cnt FROM `user`");
$totalRows = (int)(($countRes ? $countRes->fetch_assoc()['cnt'] : 0) ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $mysqli->prepare("SELECT id, login, email, name, unp, role, account_type, company_name, representative_name, phone, address, created_at FROM `user` ORDER BY id DESC LIMIT ? OFFSET ?");
if (!$stmt) {
    $stmt = $mysqli->prepare("SELECT id, login, email, name, unp, role, created_at FROM `user` ORDER BY id DESC LIMIT ? OFFSET ?");
}
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($res && ($row = $res->fetch_assoc())) $users[] = $row;
$stmt->close();
$promoCodes = [];
$checkTable = $mysqli->query("SHOW TABLES LIKE 'promo_codes'");
if ($checkTable && $checkTable->num_rows > 0) {
    $resP = $mysqli->query("SELECT id, code, type, value FROM promo_codes WHERE active = 1 ORDER BY id DESC LIMIT 200");
    while ($resP && ($row = $resP->fetch_assoc())) $promoCodes[] = $row;
}

admin_page_start('Пользователи');
?>
<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card">
    <h2>Зарегистрированные пользователи</h2>
    <p class="muted">Всего: <?= (int)$totalRows ?> · Страница <?= (int)$page ?> из <?= (int)$totalPages ?></p>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Логин</th>
            <th>E-mail</th>
            <th>Имя</th>
            <th>УНП</th>
            <th>Тип</th>
            <th>Компания</th>
            <th>Представитель</th>
            <th>Телефон</th>
            <th>Адрес</th>
            <th>Роль</th>
            <th>Создан</th>
            <th>Изменить</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td>#<?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars((string)$u['login']) ?></td>
                <td><?= htmlspecialchars((string)$u['email']) ?></td>
                <td><?= htmlspecialchars((string)($u['name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['unp'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['account_type'] ?? 'individual')) ?></td>
                <td><?= htmlspecialchars((string)($u['company_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['representative_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['phone'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['address'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['role'] ?? '')) ?></td>
                <td class="muted"><?= htmlspecialchars((string)($u['created_at'] ?? '')) ?></td>
                <td>
                    <form method="post" style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input name="login" value="<?= htmlspecialchars((string)$u['login']) ?>" required>
                        <input name="email" type="email" value="<?= htmlspecialchars((string)$u['email']) ?>" required>
                        <input name="name" value="<?= htmlspecialchars((string)($u['name'] ?? '')) ?>" placeholder="Имя">
                        <input name="unp" value="<?= htmlspecialchars((string)($u['unp'] ?? '')) ?>" placeholder="УНП">
                        <select name="account_type">
                            <option value="individual" <?= (($u['account_type'] ?? 'individual') === 'individual') ? 'selected' : '' ?>>физлицо</option>
                            <option value="legal" <?= (($u['account_type'] ?? '') === 'legal') ? 'selected' : '' ?>>юрлицо</option>
                        </select>
                        <input name="company_name" value="<?= htmlspecialchars((string)($u['company_name'] ?? '')) ?>" placeholder="Компания">
                        <input name="representative_name" value="<?= htmlspecialchars((string)($u['representative_name'] ?? '')) ?>" placeholder="Представитель">
                        <input name="phone" value="<?= htmlspecialchars((string)($u['phone'] ?? '')) ?>" placeholder="Телефон">
                        <input name="address" value="<?= htmlspecialchars((string)($u['address'] ?? '')) ?>" placeholder="Адрес">
                        <select name="role">
                            <option value="user" <?= (($u['role'] ?? '') === 'user') ? 'selected' : '' ?>>пользователь</option>
                            <option value="admin" <?= (($u['role'] ?? '') === 'admin') ? 'selected' : '' ?>>администратор</option>
                        </select>
                        <button type="submit" name="update_user" value="1">Сохранить</button>
                    </form>
                    <?php if (!empty($promoCodes)): ?>
                    <form method="post" style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <select name="promo_id" required style="max-width:280px;">
                            <option value="">Выбери промокод</option>
                            <?php foreach ($promoCodes as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['code']) ?> (<?= htmlspecialchars((string)$p['type']) ?> <?= htmlspecialchars((string)$p['value']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="send_user_promo" value="1">Отправить промокод</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1): ?>
<div class="card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(admin_url('users.php?page=' . ($page - 1)), ENT_QUOTES, 'UTF-8') ?>">← Назад</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(admin_url('users.php?page=' . ($page + 1)), ENT_QUOTES, 'UTF-8') ?>">Вперед →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php admin_page_end(); ?>

