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
        <style>
            body { font-family: Arial, sans-serif; background: #f6f7fb; }
            .box { max-width: 380px; margin: 80px auto; background: #fff; padding: 24px; border-radius: 12px; }
            input { width: 100%; padding: 10px; margin: 8px 0; }
            button { width: 100%; padding: 10px; background: #1f5cff; color: #fff; border: 0; border-radius: 8px; }
            .err { color: #b00020; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>Вход в админ-панель</h2>
            <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
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
        <li><a href="/admin/medicators">Медикаторы</a></li>
        <li><a href="/admin/gallery">Галерея медикаторов</a></li>
        <li><a href="/admin/filters">Фильтры и субфильтры</a></li>
        <li><a href="/admin/settings">JSON настройки сайта</a></li>
        <li><a href="/admin/orders">Заказы</a></li>
        <li><a href="/admin/users">Пользователи</a></li>
        <li><a href="/admin/promo_codes">Промокоды</a></li>
        <li><a href="/admin/reviews">Отзывы</a></li>
        <li><a href="/admin/migrate">Миграции БД</a></li>
    </ul>
</div>
<?php admin_page_end(); ?>

