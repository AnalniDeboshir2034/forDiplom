<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

require_once __DIR__ . '/../includes/migrations.php';

$results = [];
$run = isset($_POST['run_migrations']);
if ($run) {
    $results = app_apply_migrations($mysqli);
}

admin_page_start('Миграции БД');
?>

<div class="card">
    <h2>Миграции</h2>
    <p class="muted">
        Эта страница применяет изменения схемы БД, нужные для регистрации, заказов, промокодов, отзывов и логов e-mail.
    </p>
    <form method="post" onsubmit="return confirm('Применить миграции БД?');">
        <button type="submit" name="run_migrations" value="1">Запустить миграции</button>
    </form>
</div>

<div class="card">
    <h2>Список шагов</h2>
    <table>
        <thead>
        <tr>
            <th>Шаг</th>
            <th>Статус</th>
            <th>Время</th>
            <th>Ошибка</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (app_get_migrations() as $mig): ?>
            <tr>
                <td><?= htmlspecialchars((string)$mig['name']) ?></td>
                <td class="muted">ожидает</td>
                <td class="muted">—</td>
                <td class="muted">—</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($run): ?>
<div class="card">
    <h2>Результат запуска</h2>
    <table>
        <thead>
        <tr>
            <th>Шаг</th>
            <th>OK</th>
            <th>Время</th>
            <th>Ошибка</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string)($r['name'] ?? '')) ?></td>
                <td><?= !empty($r['ok']) ? '✅' : '❌' ?></td>
                <td class="muted"><?= (int)($r['ms'] ?? 0) ?> ms</td>
                <td style="color:#b00020;"><?= htmlspecialchars((string)($r['error'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php admin_page_end(); ?>

