<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

$stmt = $mysqli->prepare("SELECT id, login, email, name, unp, role, created_at FROM `user` ORDER BY id DESC LIMIT 500");
$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($res && ($row = $res->fetch_assoc())) $users[] = $row;
$stmt->close();

admin_page_start('Пользователи');
?>
<div class="card">
    <h2>Зарегистрированные пользователи</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Login</th>
            <th>Email</th>
            <th>Имя</th>
            <th>УНП</th>
            <th>Роль</th>
            <th>Создан</th>
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
                <td><?= htmlspecialchars((string)($u['role'] ?? '')) ?></td>
                <td class="muted"><?= htmlspecialchars((string)($u['created_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php admin_page_end(); ?>

