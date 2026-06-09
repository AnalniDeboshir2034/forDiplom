<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'filter_create') {
        $stmt = $mysqli->prepare("INSERT INTO filter (name, opis, slug) VALUES (?,?,?)");
        $name = admin_req('name');
        $opis = admin_req('opis');
        $slugInput = admin_req('slug');
        $slug = $slugInput !== '' ? admin_sanitize_slug($slugInput, 'filter') : slugify_ru_to_en($name, 'filter');
        $slug = admin_ensure_unique_slug($mysqli, 'filter', $slug, null);
        $stmt->bind_param('sss', $name, $opis, $slug);
        $stmt->execute();
        $success = 'Фильтр создан';
    }
    if ($action === 'filter_update') {
        $stmt = $mysqli->prepare("UPDATE filter SET name=?, slug=?, opis=? WHERE id=?");
        $id = (int)($_POST['id'] ?? 0);
        $name = admin_req('name');
        $slugInput = admin_req('slug');
        $slug = $slugInput !== '' ? admin_sanitize_slug($slugInput, 'filter') : slugify_ru_to_en($name, 'filter');
        $slug = admin_ensure_unique_slug($mysqli, 'filter', $slug, $id);
        $opis = admin_req('opis');
        $stmt->bind_param('sssi', $name, $slug, $opis, $id);
        $stmt->execute();
        $success = 'Фильтр обновлён';
    }
    if ($action === 'filter_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM filter_Relationships WHERE filter_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt = $mysqli->prepare("DELETE FROM filter WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $success = 'Фильтр удалён';
    }

    if ($action === 'subfilter_create') {
        $stmt = $mysqli->prepare("INSERT INTO subfilter (name, opis, slug) VALUES (?,?,?)");
        $name = admin_req('name');
        $opis = admin_req('opis');
        $slugInput = admin_req('slug');
        $slug = $slugInput !== '' ? admin_sanitize_slug($slugInput, 'subfilter') : slugify_ru_to_en($name, 'subfilter');
        $slug = admin_ensure_unique_slug($mysqli, 'subfilter', $slug, null);
        $stmt->bind_param('sss', $name, $opis, $slug);
        $stmt->execute();
        $success = 'Субфильтр создан';
    }
    if ($action === 'subfilter_update') {
        $stmt = $mysqli->prepare("UPDATE subfilter SET name=?, slug=?, opis=? WHERE id=?");
        $id = (int)($_POST['id'] ?? 0);
        $name = admin_req('name');
        $slugInput = admin_req('slug');
        $slug = $slugInput !== '' ? admin_sanitize_slug($slugInput, 'subfilter') : slugify_ru_to_en($name, 'subfilter');
        $slug = admin_ensure_unique_slug($mysqli, 'subfilter', $slug, $id);
        $opis = admin_req('opis');
        $stmt->bind_param('sssi', $name, $slug, $opis, $id);
        $stmt->execute();
        $success = 'Субфильтр обновлён';
    }
    if ($action === 'subfilter_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM filter_Relationships WHERE subfilter_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt = $mysqli->prepare("DELETE FROM subfilter WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $success = 'Субфильтр удалён';
    }

    if ($action === 'relation_create') {
        $stmt = $mysqli->prepare("INSERT INTO filter_Relationships (filter_id, subfilter_id) VALUES (?,?)");
        $filterId = (int)($_POST['filter_id'] ?? 0);
        $subfilterId = (int)($_POST['subfilter_id'] ?? 0);
        $stmt->bind_param('ii', $filterId, $subfilterId);
        $stmt->execute();
        $success = 'Связь добавлена';
    }
    if ($action === 'relation_delete') {
        $stmt = $mysqli->prepare("DELETE FROM filter_Relationships WHERE id=?");
        $id = (int)($_POST['id'] ?? 0);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $success = 'Связь удалена';
    }
}

$perPage = 20;
$fPage = max(1, (int)($_GET['fpage'] ?? 1));
$sfPage = max(1, (int)($_GET['sfpage'] ?? 1));
$rPage = max(1, (int)($_GET['rpage'] ?? 1));

$filtersTotalRes = $mysqli->query("SELECT COUNT(*) AS cnt FROM filter");
$filtersTotal = (int)(($filtersTotalRes ? $filtersTotalRes->fetch_assoc()['cnt'] : 0) ?? 0);
$filtersPages = max(1, (int)ceil($filtersTotal / $perPage));
if ($fPage > $filtersPages) $fPage = $filtersPages;
$filtersOffset = ($fPage - 1) * $perPage;
$stmtFilters = $mysqli->prepare("SELECT * FROM filter ORDER BY id DESC LIMIT ? OFFSET ?");
$stmtFilters->bind_param('ii', $perPage, $filtersOffset);
$stmtFilters->execute();
$filters = $stmtFilters->get_result();

$subfiltersTotalRes = $mysqli->query("SELECT COUNT(*) AS cnt FROM subfilter");
$subfiltersTotal = (int)(($subfiltersTotalRes ? $subfiltersTotalRes->fetch_assoc()['cnt'] : 0) ?? 0);
$subfiltersPages = max(1, (int)ceil($subfiltersTotal / $perPage));
if ($sfPage > $subfiltersPages) $sfPage = $subfiltersPages;
$subfiltersOffset = ($sfPage - 1) * $perPage;
$stmtSubfilters = $mysqli->prepare("SELECT * FROM subfilter ORDER BY id DESC LIMIT ? OFFSET ?");
$stmtSubfilters->bind_param('ii', $perPage, $subfiltersOffset);
$stmtSubfilters->execute();
$subfilters = $stmtSubfilters->get_result();

$relationsTotalRes = $mysqli->query("SELECT COUNT(*) AS cnt FROM filter_Relationships");
$relationsTotal = (int)(($relationsTotalRes ? $relationsTotalRes->fetch_assoc()['cnt'] : 0) ?? 0);
$relationsPages = max(1, (int)ceil($relationsTotal / $perPage));
if ($rPage > $relationsPages) $rPage = $relationsPages;
$relationsOffset = ($rPage - 1) * $perPage;
$stmtRelations = $mysqli->prepare("SELECT fr.id, f.name AS filter_name, sf.name AS subfilter_name FROM filter_Relationships fr JOIN filter f ON f.id=fr.filter_id JOIN subfilter sf ON sf.id=fr.subfilter_id ORDER BY fr.id DESC LIMIT ? OFFSET ?");
$stmtRelations->bind_param('ii', $perPage, $relationsOffset);
$stmtRelations->execute();
$relations = $stmtRelations->get_result();
$fList = $mysqli->query("SELECT id,name FROM filter ORDER BY id DESC");
$sfList = $mysqli->query("SELECT id,name FROM subfilter ORDER BY id DESC");

admin_page_start('Админка: Фильтры и субфильтры');
if ($success) {
    echo '<div class="msg">' . htmlspecialchars($success) . '</div>';
}
?>
<div class="card">
    <h2>Создание</h2>
    <div class="grid">
        <form method="post">
            <h3>Новый filter</h3>
            <input type="hidden" name="action" value="filter_create">
            <input name="name" placeholder="name" required class="slug-source" data-slug-target="slug_filter_create">
            <input name="slug" id="slug_filter_create" placeholder="slug" required class="slug-target" data-manual="0">
            <input name="opis" placeholder="opis">
            <button>Создать filter</button>
        </form>
        <form method="post">
            <h3>Новый subfilter</h3>
            <input type="hidden" name="action" value="subfilter_create">
            <input name="name" placeholder="name" required class="slug-source" data-slug-target="slug_subfilter_create">
            <input name="slug" id="slug_subfilter_create" placeholder="slug" required class="slug-target" data-manual="0">
            <input name="opis" placeholder="opis">
            <button>Создать subfilter</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Связать filter/subfilter</h2>
    <form method="post" class="grid">
        <input type="hidden" name="action" value="relation_create">
        <select name="filter_id" required>
            <option value="">filter_id</option>
            <?php while ($f = $fList->fetch_assoc()): ?>
                <option value="<?= (int)$f['id'] ?>"><?= (int)$f['id'] ?> - <?= htmlspecialchars($f['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <select name="subfilter_id" required>
            <option value="">subfilter_id</option>
            <?php while ($sf = $sfList->fetch_assoc()): ?>
                <option value="<?= (int)$sf['id'] ?>"><?= (int)$sf['id'] ?> - <?= htmlspecialchars($sf['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button>Связать</button>
    </form>
</div>

<div class="card">
    <h2>Filter</h2>
    <p class="muted">Всего: <?= (int)$filtersTotal ?> · Страница <?= (int)$fPage ?> из <?= (int)$filtersPages ?></p>
    <table>
        <tr><th>Номер</th><th>Название</th><th>Слаг</th><th>Описание</th><th>Обновить</th><th>Удалить</th></tr>
        <?php while ($f = $filters->fetch_assoc()): ?>
            <tr>
                <td><?= (int)$f['id'] ?></td>
                <td><input form="f<?= (int)$f['id'] ?>" name="name" value="<?= htmlspecialchars($f['name']) ?>" class="slug-source" data-slug-target="slug_f<?= (int)$f['id'] ?>"></td>
                <td><input form="f<?= (int)$f['id'] ?>" name="slug" id="slug_f<?= (int)$f['id'] ?>" value="<?= htmlspecialchars($f['slug']) ?>" class="slug-target" data-manual="0"></td>
                <td><input form="f<?= (int)$f['id'] ?>" name="opis" value="<?= htmlspecialchars($f['opis']) ?>"></td>
                <td>
                    <form id="f<?= (int)$f['id'] ?>" method="post">
                        <input type="hidden" name="action" value="filter_update">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <button>Сохранить</button>
                    </form>
                </td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="filter_delete">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <button class="danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php if ($filtersPages > 1): ?>
<div class="card">
    <?php if ($fPage > 1): ?><a href="<?= htmlspecialchars(admin_url('filters.php?fpage=' . ($fPage - 1) . '&sfpage=' . $sfPage . '&rpage=' . $rPage), ENT_QUOTES, 'UTF-8') ?>">← Filter назад</a><?php endif; ?>
    <?php if ($fPage < $filtersPages): ?><a style="margin-left:10px;" href="<?= htmlspecialchars(admin_url('filters.php?fpage=' . ($fPage + 1) . '&sfpage=' . $sfPage . '&rpage=' . $rPage), ENT_QUOTES, 'UTF-8') ?>">Filter вперед →</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Subfilter</h2>
    <p class="muted">Всего: <?= (int)$subfiltersTotal ?> · Страница <?= (int)$sfPage ?> из <?= (int)$subfiltersPages ?></p>
    <table>
        <tr><th>Номер</th><th>Название</th><th>Слаг</th><th>Описание</th><th>Обновить</th><th>Удалить</th></tr>
        <?php while ($sf = $subfilters->fetch_assoc()): ?>
            <tr>
                <td><?= (int)$sf['id'] ?></td>
                <td><input form="sf<?= (int)$sf['id'] ?>" name="name" value="<?= htmlspecialchars($sf['name']) ?>" class="slug-source" data-slug-target="slug_sf<?= (int)$sf['id'] ?>"></td>
                <td><input form="sf<?= (int)$sf['id'] ?>" name="slug" id="slug_sf<?= (int)$sf['id'] ?>" value="<?= htmlspecialchars($sf['slug']) ?>" class="slug-target" data-manual="0"></td>
                <td><input form="sf<?= (int)$sf['id'] ?>" name="opis" value="<?= htmlspecialchars($sf['opis']) ?>"></td>
                <td>
                    <form id="sf<?= (int)$sf['id'] ?>" method="post">
                        <input type="hidden" name="action" value="subfilter_update">
                        <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
                        <button>Сохранить</button>
                    </form>
                </td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="subfilter_delete">
                        <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
                        <button class="danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php if ($subfiltersPages > 1): ?>
<div class="card">
    <?php if ($sfPage > 1): ?><a href="<?= htmlspecialchars(admin_url('filters.php?fpage=' . $fPage . '&sfpage=' . ($sfPage - 1) . '&rpage=' . $rPage), ENT_QUOTES, 'UTF-8') ?>">← Subfilter назад</a><?php endif; ?>
    <?php if ($sfPage < $subfiltersPages): ?><a style="margin-left:10px;" href="<?= htmlspecialchars(admin_url('filters.php?fpage=' . $fPage . '&sfpage=' . ($sfPage + 1) . '&rpage=' . $rPage), ENT_QUOTES, 'UTF-8') ?>">Subfilter вперед →</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Связи</h2>
    <p class="muted">Всего: <?= (int)$relationsTotal ?> · Страница <?= (int)$rPage ?> из <?= (int)$relationsPages ?></p>
    <table>
        <tr><th>Номер</th><th>Фильтр</th><th>Субфильтр</th><th>Удалить</th></tr>
        <?php while ($r = $relations->fetch_assoc()): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['filter_name']) ?></td>
                <td><?= htmlspecialchars($r['subfilter_name']) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="relation_delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php if ($relationsPages > 1): ?>
<div class="card">
    <?php if ($rPage > 1): ?><a href="<?= htmlspecialchars(admin_url('filters.php?fpage=' . $fPage . '&sfpage=' . $sfPage . '&rpage=' . ($rPage - 1)), ENT_QUOTES, 'UTF-8') ?>">← Связи назад</a><?php endif; ?>
    <?php if ($rPage < $relationsPages): ?><a style="margin-left:10px;" href="<?= htmlspecialchars(admin_url('filters.php?fpage=' . $fPage . '&sfpage=' . $sfPage . '&rpage=' . ($rPage + 1)), ENT_QUOTES, 'UTF-8') ?>">Связи вперед →</a><?php endif; ?>
</div>
<?php endif; ?>
<script>
function slugifyRuToEn(text) {
    if (!text) return '';
    text = String(text).toLowerCase().trim();
    const map = {
        'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t',
        'у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
    };
    let out = '';
    for (const ch of text) out += (map.hasOwnProperty(ch) ? map[ch] : ch);
    out = out.replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    return out;
}

document.addEventListener('input', function (e) {
    const nameInput = e.target.closest('.slug-source');
    if (!nameInput) return;
    const targetId = nameInput.getAttribute('data-slug-target');
    if (!targetId) return;
    const slugInput = document.getElementById(targetId);
    if (!slugInput) return;
    if (slugInput.dataset.manual === '0') {
        slugInput.value = slugifyRuToEn(nameInput.value);
    }
});

document.querySelectorAll('.slug-target').forEach(function (sl) {
    sl.addEventListener('input', function () {
        sl.dataset.manual = '1';
    });
});
</script>

<?php admin_page_end(); ?>

