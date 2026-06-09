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
<style>
/* МОДАЛЬНОЕ ОКНО */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 550px;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
    animation: modalFadeIn 0.2s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    border-radius: 20px 20px 0 0;
}

.modal-header h3 {
    margin: 0;
    color: white;
    font-size: 18px;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
    box-shadow: none;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: none;
}

.modal-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #4b5563;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #f97316;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}

.modal-footer {
    padding: 16px 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-top: 1px solid #e5e7eb;
}

.modal-footer button {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #e5e7eb;
    box-shadow: none;
}

.btn-cancel:hover {
    background: #e5e7eb;
    transform: none;
}

.btn-save {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: white;
    border: none;
}

.btn-save:hover {
    transform: translateY(-1px);
}

/* КНОПКА РЕДАКТИРОВАТЬ В ТАБЛИЦЕ */
.edit-user-btn {
    background: #f97316;
    color: white;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.edit-user-btn:hover {
    background: #ea580c;
    transform: translateY(-1px);
}

/* ТАБЛИЦА */
.users-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.users-table th,
.users-table td {
    border: 1px solid #e5e7eb;
    padding: 10px 12px;
    text-align: left;
    vertical-align: middle;
}

.users-table th {
    background: #fff7ed;
    color: #9a3412;
    font-weight: 700;
    white-space: nowrap;
}

.users-table tr:nth-child(even) {
    background: #fafafa;
}

.card {
    background: white;
    border-radius: 18px;
    padding: 20px;
    margin: 16px 0;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    overflow-x: auto;
}

.msg-success {
    background: #d1fae5;
    color: #065f46;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.msg-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.muted {
    color: #6b7280;
    font-size: 13px;
}

.promo-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.promo-title {
    font-size: 13px;
    font-weight: 700;
    color: #4b5563;
    margin-bottom: 12px;
    text-transform: uppercase;
}

.promo-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    margin-bottom: 12px;
}

.promo-btn {
    background: #10b981;
    color: white;
    padding: 10px;
    border-radius: 10px;
    font-weight: 600;
    width: 100%;
}

.promo-btn:hover {
    background: #059669;
}
</style>

<?php if ($msg): ?>
    <div class="<?= strpos($msg, 'обновлен') !== false || strpos($msg, 'отправлен') !== false ? 'msg-success' : 'msg-error' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top: 0; color: #f97316;">Зарегистрированные пользователи</h2>
    <p class="muted" style="margin-bottom: 16px;">Всего: <?= (int)$totalRows ?> · Страница <?= (int)$page ?> из <?= (int)$totalPages ?></p>
    
    <table class="users-table">
        <thead>
            <tr>
                <th>Номер</th>
                <th>Логин</th>
                <th>E-mail</th>
                <th>Имя</th>
                <th>УНП</th>
                <th>Тип</th>
                <th>Компания</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Адрес</th>
                <th>Роль</th>
                <th>Создан</th>
                <th>Действия</th>
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
                <td><?= htmlspecialchars(app_account_type_label((string)($u['account_type'] ?? 'individual'))) ?></td>
                <td><?= htmlspecialchars((string)($u['company_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['representative_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['phone'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($u['address'] ?? '')) ?></td>
                <td><?= htmlspecialchars(app_user_role_label((string)($u['role'] ?? 'user'))) ?></td>
                <td style="font-size: 11px;"><?= htmlspecialchars(app_format_datetime((string)($u['created_at'] ?? ''))) ?></td>
                <td>
                    <button class="edit-user-btn" onclick="openEditModal(<?= (int)$u['id'] ?>)">Редактировать</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="card">
    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: center;">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(admin_url('users.php?page=' . ($page - 1)), ENT_QUOTES, 'UTF-8') ?>" style="background: #f97316; color: white; padding: 8px 16px; border-radius: 30px; text-decoration: none;">Назад</a>
        <?php endif; ?>
        <span style="color: #6b7280;">Страница <?= $page ?> из <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(admin_url('users.php?page=' . ($page + 1)), ENT_QUOTES, 'UTF-8') ?>" style="background: #f97316; color: white; padding: 8px 16px; border-radius: 30px; text-decoration: none;">Вперед</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- МОДАЛЬНОЕ ОКНО РЕДАКТИРОВАНИЯ -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Редактирование пользователя</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="post" id="editForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>ЛОГИН *</label>
                    <input type="text" name="login" id="edit_login" required>
                </div>
                
                <div class="form-group">
                    <label>E-MAIL *</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>ИМЯ</label>
                    <input type="text" name="name" id="edit_name" placeholder="Имя пользователя">
                </div>
                
                <div class="form-group">
                    <label>УНП</label>
                    <input type="text" name="unp" id="edit_unp" placeholder="УНП (для юрлиц)">
                </div>
                
                <div class="form-group">
                    <label>ТИП КЛИЕНТА</label>
                    <select name="account_type" id="edit_account_type">
                        <option value="individual">Физическое лицо</option>
                        <option value="legal">Юридическое лицо</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>КОМПАНИЯ</label>
                    <input type="text" name="company_name" id="edit_company_name" placeholder="Название компании">
                </div>
                
                <div class="form-group">
                    <label>ФИО</label>
                    <input type="text" name="representative_name" id="edit_representative_name" placeholder="ФИО представителя">
                </div>
                
                <div class="form-group">
                    <label>ТЕЛЕФОН</label>
                    <input type="tel" name="phone" id="edit_phone" placeholder="+375 (xx) xxx-xx-xx">
                </div>
                
                <div class="form-group">
                    <label>АДРЕС</label>
                    <input type="text" name="address" id="edit_address" placeholder="Адрес доставки">
                </div>
                
                <div class="form-group">
                    <label>РОЛЬ</label>
                    <select name="role" id="edit_role">
                        <option value="user">Пользователь</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>

                <?php if (!empty($promoCodes)): ?>
                <div class="promo-section">
                    <div class="promo-title">Отправить промокод</div>
                    <select id="promo_select_<?= time() ?>" class="promo-select">
                        <option value="">Выберите промокод</option>
                        <?php foreach ($promoCodes as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars((string)$p['code']) ?> (<?= htmlspecialchars(app_promo_type_label((string)$p['type'])) ?> - <?= htmlspecialchars((string)$p['value']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="promo-btn" onclick="sendPromoFromModal()">Отправить промокод на email</button>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Отмена</button>
                <button type="submit" name="update_user" value="1" class="btn-save">Сохранить изменения</button>
            </div>
        </form>
    </div>
</div>

<script>
// Данные пользователей из PHP в JS
const usersData = <?php 
    $data = [];
    foreach ($users as $u) {
        $data[$u['id']] = [
            'id' => $u['id'],
            'login' => $u['login'],
            'email' => $u['email'],
            'name' => $u['name'] ?? '',
            'unp' => $u['unp'] ?? '',
            'account_type' => $u['account_type'] ?? 'individual',
            'company_name' => $u['company_name'] ?? '',
            'representative_name' => $u['representative_name'] ?? '',
            'phone' => $u['phone'] ?? '',
            'address' => $u['address'] ?? '',
            'role' => $u['role'] ?? 'user'
        ];
    }
    echo json_encode($data);
?>;

let currentUserId = null;

function openEditModal(userId) {
    currentUserId = userId;
    const user = usersData[userId];
    
    if (user) {
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_login').value = user.login;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_unp').value = user.unp;
        document.getElementById('edit_account_type').value = user.account_type;
        document.getElementById('edit_company_name').value = user.company_name;
        document.getElementById('edit_representative_name').value = user.representative_name;
        var phoneEl = document.getElementById('edit_phone');
        if (phoneEl && window.AppPhoneMask && typeof window.AppPhoneMask.setValue === 'function') {
            window.AppPhoneMask.setValue(phoneEl, user.phone || '');
        } else if (phoneEl) {
            phoneEl.value = user.phone || '';
        }
        document.getElementById('edit_address').value = user.address;
        document.getElementById('edit_role').value = user.role;
        
        document.getElementById('editModal').classList.add('active');
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    currentUserId = null;
}

function sendPromoFromModal() {
    if (!currentUserId) return;
    
    const promoSelect = document.querySelector('#editModal .promo-select');
    const promoId = promoSelect ? promoSelect.value : null;
    
    if (!promoId) {
        alert('Выберите промокод');
        return;
    }
    
    // Создаем скрытую форму для отправки промокода
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.style.display = 'none';
    
    const userIdInput = document.createElement('input');
    userIdInput.name = 'user_id';
    userIdInput.value = currentUserId;
    form.appendChild(userIdInput);
    
    const promoIdInput = document.createElement('input');
    promoIdInput.name = 'promo_id';
    promoIdInput.value = promoId;
    form.appendChild(promoIdInput);
    
    const actionInput = document.createElement('input');
    actionInput.name = 'send_user_promo';
    actionInput.value = '1';
    form.appendChild(actionInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Закрытие по клику вне модального окна
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Закрытие по ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('editModal').classList.contains('active')) {
        closeEditModal();
    }
});
</script>

<?php admin_page_end(); ?>