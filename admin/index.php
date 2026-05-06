<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['is_admin'])) {
    $error = $_SESSION['admin_login_error'] ?? '';
    unset($_SESSION['admin_login_error']);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
        <link rel="stylesheet" href="<?= htmlspecialchars(app_url('admin/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
    </head>
    <body class="admin-page">
        <div class="card admin-auth">
            <h2>Вход в админ-панель</h2>
            <?php if ($error): ?><p style="color:#b00020;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="admin_login" value="1">
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

admin_page_start('Админ-панель');
?>
<div class="card">
    <h2>Разделы</h2>
    <p class="muted">Админка разделена на отдельные страницы.</p>
    <ul>
        <li><a href="<?= htmlspecialchars(admin_url('medicators.php'), ENT_QUOTES, 'UTF-8') ?>">Медикаторы</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('gallery.php'), ENT_QUOTES, 'UTF-8') ?>">Галерея медикаторов</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('filters.php'), ENT_QUOTES, 'UTF-8') ?>">Фильтры и субфильтры</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('settings.php'), ENT_QUOTES, 'UTF-8') ?>">JSON настройки сайта</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('orders.php'), ENT_QUOTES, 'UTF-8') ?>">Заказы</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('users.php'), ENT_QUOTES, 'UTF-8') ?>">Пользователи</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('promo_codes.php'), ENT_QUOTES, 'UTF-8') ?>">Промокоды</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('reviews.php'), ENT_QUOTES, 'UTF-8') ?>">Отзывы</a></li>
        <li><a href="<?= htmlspecialchars(admin_url('reports.php'), ENT_QUOTES, 'UTF-8') ?>">Отчеты</a></li>
    </ul>
</div>
<?php admin_page_end(); ?>

