<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

$success = '';
$error = '';

$deletedFlag = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
if ($deletedFlag === 1) {
    $success = 'Медикатор удалён';
}

$uploadDirWeb = 'products/admin_uploads';
$uploadDirAbs = __DIR__ . '/../' . $uploadDirWeb;
$uploadDocsWeb = 'products/admin_docs';
$uploadDocsAbs = __DIR__ . '/../' . $uploadDocsWeb;
if (!is_dir($uploadDirAbs)) {
    mkdir($uploadDirAbs, 0755, true);
}
if (!is_dir($uploadDocsAbs)) {
    mkdir($uploadDocsAbs, 0755, true);
}

function admin_img_src($pathImg)
{
    if ($pathImg === '') {
        return '';
    }
    $startsWithSlash = substr((string)$pathImg, 0, 1) === '/';
    return ($startsWithSlash ? '' : '/') . $pathImg;
}

function admin_get_uploaded_files_array($filesField)
{
    $result = [];
    if (!isset($filesField['name']) || !is_array($filesField['name'])) {
        return $result;
    }
    $count = count($filesField['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $result[] = [
                'name' => $filesField['name'][$i],
                'tmp_name' => $filesField['tmp_name'][$i],
                'type' => $filesField['type'][$i] ?? '',
                'error' => $filesField['error'][$i],
            ];
        }
    }
    return $result;
}

function admin_is_image_file($mime)
{
    return strpos((string)$mime, 'image/') === 0;
}

function admin_is_pdf_file($mime, $name)
{
    $mime = (string)$mime;
    $name = (string)$name;
    if ($mime === 'application/pdf') {
        return true;
    }
    return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
}

function admin_save_uploaded_pdf($fileField, $uploadDocsAbs, $uploadDocsWeb)
{
    if (!isset($fileField['error']) || $fileField['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $tmpName = $fileField['tmp_name'];
    $origName = basename($fileField['name']);
    $mime = isset($fileField['type']) ? $fileField['type'] : '';
    if (!admin_is_pdf_file($mime, $origName)) {
        return '';
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9\._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
    $safeName = uniqid('doc_', true) . '_' . $safeBase . '.pdf';
    $destAbs = $uploadDocsAbs . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmpName, $destAbs)) {
        return '';
    }

    return $uploadDocsWeb . '/' . $safeName;
}

function admin_prepare_or_throw($mysqli, $sql)
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Ошибка подготовки SQL: ' . $mysqli->error);
    }
    return $stmt;
}

function admin_table_exists($mysqli, $tableName)
{
    $sql = "SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1";
    $stmt = admin_prepare_or_throw($mysqli, $sql);
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function admin_column_exists($mysqli, $tableName, $columnName)
{
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = admin_prepare_or_throw($mysqli, $sql);
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

$medicatorHasSlug = false;
try {
    $medicatorHasSlug = admin_column_exists($mysqli, 'medicator', 'slug');
} catch (Throwable $e) {
    $medicatorHasSlug = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'medicator_create') {
        try {
            $name = admin_req('name');
            $filtr = admin_req('filtr');
            $slug = '';
            if ($medicatorHasSlug) {
                $slugInput = admin_req('slug');
                $slug = $slugInput !== '' ? admin_sanitize_slug($slugInput) : admin_sanitize_slug(slugify_ru_to_en($name, 'product'));
                $slug = admin_ensure_unique_slug($mysqli, 'medicator', $slug, null);
            }

            $passportLink = admin_req('passport');
            $userPassLink = admin_req('user_pass');
            $passportUploaded = admin_save_uploaded_pdf(isset($_FILES['passport_file']) ? $_FILES['passport_file'] : [], $uploadDocsAbs, $uploadDocsWeb);
            $userPassUploaded = admin_save_uploaded_pdf(isset($_FILES['user_pass_file']) ? $_FILES['user_pass_file'] : [], $uploadDocsAbs, $uploadDocsWeb);
            if ($passportUploaded !== '') {
                $passportLink = $passportUploaded;
            }
            if ($userPassUploaded !== '') {
                $userPassLink = $userPassUploaded;
            }

            if ($medicatorHasSlug) {
                $sql = "INSERT INTO medicator (name,d_dosing,performance,pressure,temperature,connections,m_seal,m_case,dop,passport,user_pass,opis,filtr,slug)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmt = admin_prepare_or_throw($mysqli, $sql);
                $stmt->bind_param(
                    'ssssssssssssss',
                    $name,
                    admin_req('d_dosing'),
                    admin_req('performance'),
                    admin_req('pressure'),
                    admin_req('temperature'),
                    admin_req('connections'),
                    admin_req('m_seal'),
                    admin_req('m_case'),
                    admin_req('dop'),
                    $passportLink,
                    $userPassLink,
                    admin_req('opis'),
                    $filtr,
                    $slug
                );
            } else {
                $sql = "INSERT INTO medicator (name,d_dosing,performance,pressure,temperature,connections,m_seal,m_case,dop,passport,user_pass,opis,filtr)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmt = admin_prepare_or_throw($mysqli, $sql);
                $stmt->bind_param(
                    'sssssssssssss',
                    $name,
                    admin_req('d_dosing'),
                    admin_req('performance'),
                    admin_req('pressure'),
                    admin_req('temperature'),
                    admin_req('connections'),
                    admin_req('m_seal'),
                    admin_req('m_case'),
                    admin_req('dop'),
                    $passportLink,
                    $userPassLink,
                    admin_req('opis'),
                    $filtr
                );
            }

            $stmt->execute();
            $stmt->close();
            $success = 'Медикатор создан';
        } catch (Throwable $e) {
            $error = 'Ошибка создания: ' . $e->getMessage();
        }
    }

    if ($action === 'medicator_update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $name = admin_req('name');
                $filtr = admin_req('filtr');
                $slug = '';
                if ($medicatorHasSlug) {
                    $slugInput = admin_req('slug');
                    $slug = $slugInput !== '' ? admin_sanitize_slug($slugInput) : admin_sanitize_slug(slugify_ru_to_en($name, 'product'));
                    $slug = admin_ensure_unique_slug($mysqli, 'medicator', $slug, $id);
                }

                $opis = admin_req('opis');
                $passportLink = admin_req('passport');
                $userPassLink = admin_req('user_pass');
                $passportUploaded = admin_save_uploaded_pdf(isset($_FILES['passport_file']) ? $_FILES['passport_file'] : [], $uploadDocsAbs, $uploadDocsWeb);
                $userPassUploaded = admin_save_uploaded_pdf(isset($_FILES['user_pass_file']) ? $_FILES['user_pass_file'] : [], $uploadDocsAbs, $uploadDocsWeb);
                if ($passportUploaded !== '') {
                    $passportLink = $passportUploaded;
                }
                if ($userPassUploaded !== '') {
                    $userPassLink = $userPassUploaded;
                }

                if ($medicatorHasSlug) {
                    $sql = "UPDATE medicator SET 
                            name=?, slug=?, filtr=?, 
                            d_dosing=?, performance=?, pressure=?, temperature=?,
                            connections=?, m_seal=?, m_case=?, dop=?,
                            passport=?, user_pass=?, opis=?
                            WHERE id=?";
                    $stmt = admin_prepare_or_throw($mysqli, $sql);
                    $stmt->bind_param(
                        str_repeat('s', 14) . 'i',
                        $name,
                        $slug,
                        $filtr,
                        admin_req('d_dosing'),
                        admin_req('performance'),
                        admin_req('pressure'),
                        admin_req('temperature'),
                        admin_req('connections'),
                        admin_req('m_seal'),
                        admin_req('m_case'),
                        admin_req('dop'),
                        $passportLink,
                        $userPassLink,
                        $opis,
                        $id
                    );
                } else {
                    $sql = "UPDATE medicator SET 
                            name=?, filtr=?, 
                            d_dosing=?, performance=?, pressure=?, temperature=?,
                            connections=?, m_seal=?, m_case=?, dop=?,
                            passport=?, user_pass=?, opis=?
                            WHERE id=?";
                    $stmt = admin_prepare_or_throw($mysqli, $sql);
                    $stmt->bind_param(
                        str_repeat('s', 13) . 'i',
                        $name,
                        $filtr,
                        admin_req('d_dosing'),
                        admin_req('performance'),
                        admin_req('pressure'),
                        admin_req('temperature'),
                        admin_req('connections'),
                        admin_req('m_seal'),
                        admin_req('m_case'),
                        admin_req('dop'),
                        $passportLink,
                        $userPassLink,
                        $opis,
                        $id
                    );
                }

                $stmt->execute();
                $stmt->close();
                $success = 'Медикатор обновлён';
            } catch (Throwable $e) {
                $error = 'Ошибка обновления: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'medicator_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Collect related file paths to delete after DB commit.
            $pathsToDelete = [];
            try {
                $stmt = $mysqli->prepare("SELECT passport, user_pass FROM medicator WHERE id=? LIMIT 1");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($row) {
                    if (!empty($row['passport'])) $pathsToDelete[] = (string)$row['passport'];
                    if (!empty($row['user_pass'])) $pathsToDelete[] = (string)$row['user_pass'];
                }

                $stmt = $mysqli->prepare("SELECT path_img FROM medicator_img WHERE medicator_id=?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        if (!empty($r['path_img'])) $pathsToDelete[] = (string)$r['path_img'];
                    }
                }
                $stmt->close();
            } catch (Throwable $e) {
                // ignore file cleanup prefetch errors
            }

            $mysqli->begin_transaction();
            try {
                if (admin_table_exists($mysqli, 'medicator_view')) {
                    $stmt = admin_prepare_or_throw($mysqli, "DELETE FROM medicator_view WHERE medicator_id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                }

                if (admin_table_exists($mysqli, 'medicator_img')) {
                    $stmt = admin_prepare_or_throw($mysqli, "DELETE FROM medicator_img WHERE medicator_id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                }

                $stmt = $mysqli->prepare("DELETE FROM medicator WHERE id=?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();

                $mysqli->commit();

                // Remove related files (images/docs) from disk after successful DB commit.
                $projectRoot = realpath(__DIR__ . '/../');
                foreach ($pathsToDelete as $relPath) {
                    $relPath = ltrim((string)$relPath, "/\\");
                    if ($relPath === '' || strpos($relPath, '..') !== false) continue;

                    // Only delete files that belong to our upload folders.
                    $isUpload = (strpos($relPath, 'products/admin_uploads/') === 0) || (strpos($relPath, 'products/admin_docs/') === 0);
                    if (!$isUpload) continue;

                    $abs = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relPath);
                    if (is_file($abs)) {
                        @unlink($abs);
                    }
                }

                header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1');
                exit;
            } catch (Throwable $e) {
                $mysqli->rollback();
                $error = 'Ошибка удаления: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'medicator_img_add') {
        $medicatorId = (int)($_POST['medicator_id'] ?? 0);
        $makeMainFirst = isset($_POST['make_main_first']) ? 1 : 0;
        $files = admin_get_uploaded_files_array($_FILES['img_files'] ?? []);

        if ($medicatorId > 0 && count($files) > 0) {
            $maxSort = 0;
            $stmt = $mysqli->prepare("SELECT COALESCE(MAX(sort),0) AS max_sort FROM medicator_img WHERE medicator_id=?");
            $stmt->bind_param('i', $medicatorId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $maxSort = (int)($row['max_sort'] ?? 0);
            $stmt->close();

            if ($makeMainFirst === 1) {
                $stmt = $mysqli->prepare("UPDATE medicator_img SET is_Main=0 WHERE medicator_id=?");
                $stmt->bind_param('i', $medicatorId);
                $stmt->execute();
                $stmt->close();
            }

            $insert = $mysqli->prepare("INSERT INTO medicator_img (medicator_id, is_Main, path_img, sort) VALUES (?,?,?,?)");

            $idx = 0;
            foreach ($files as $file) {
                $mime = $file['type'] ?? '';
                if (!admin_is_image_file($mime)) {
                    continue;
                }

                $originalName = basename($file['name']);
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if ($ext === '') {
                    $ext = 'jpg';
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9\._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $safeName = uniqid('img_', true) . '_' . $safeBase . '.' . $ext;

                $destAbs = $uploadDirAbs . DIRECTORY_SEPARATOR . $safeName;
                if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
                    continue;
                }

                $pathImg = $uploadDirWeb . '/' . $safeName; // relative path for DB
                $isMain = ($makeMainFirst === 1 && $idx === 0) ? 1 : 0;
                $sort = $maxSort + $idx + 1;

                $insert->bind_param('iisi', $medicatorId, $isMain, $pathImg, $sort);
                $insert->execute();

                $idx++;
            }
            $success = 'Картинки добавлены';
        }
    }

    if ($action === 'medicator_img_update') {
        $id = (int)($_POST['img_id'] ?? 0);
        $medicatorId = (int)($_POST['medicator_id'] ?? 0);
        $isMain = (int)($_POST['is_main'] ?? 0);
        $sort = (int)($_POST['sort'] ?? 0);
        $pathImg = admin_req('path_img');

        if ($id > 0) {
            if ($isMain === 1) {
                $stmt = $mysqli->prepare("UPDATE medicator_img SET is_Main=0 WHERE medicator_id=? AND id!=?");
                $stmt->bind_param('ii', $medicatorId, $id);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $mysqli->prepare("UPDATE medicator_img SET path_img=?, is_Main=?, sort=? WHERE id=? AND medicator_id=?");
            $stmt->bind_param('siiii', $pathImg, $isMain, $sort, $id, $medicatorId);
            $stmt->execute();
            $success = 'Картинка обновлена';
        }
    }

    if ($action === 'medicator_img_delete') {
        $id = (int)($_POST['img_id'] ?? 0);
        $medicatorId = (int)($_POST['medicator_id'] ?? 0);
        if ($id > 0 && $medicatorId > 0) {
            $stmt = $mysqli->prepare("SELECT is_Main FROM medicator_img WHERE id=? AND medicator_id=? LIMIT 1");
            $stmt->bind_param('ii', $id, $medicatorId);
            $stmt->execute();
            $resRow = $stmt->get_result();
            $row = $resRow ? $resRow->fetch_assoc() : null;
            $wasMain = (int)($row['is_Main'] ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare("DELETE FROM medicator_img WHERE id=? AND medicator_id=?");
            $stmt->bind_param('ii', $id, $medicatorId);
            $stmt->execute();

            if ($wasMain === 1) {
                $stmt = $mysqli->prepare("SELECT id FROM medicator_img WHERE medicator_id=? ORDER BY sort ASC, id ASC LIMIT 1");
                $stmt->bind_param('i', $medicatorId);
                $stmt->execute();
                $resMain = $stmt->get_result();
                $rowMain = $resMain ? $resMain->fetch_assoc() : null;
                $newMainId = isset($rowMain['id']) ? $rowMain['id'] : null;
                $stmt->close();

                if ($newMainId) {
                    $stmt = $mysqli->prepare("UPDATE medicator_img SET is_Main=1 WHERE id=? AND medicator_id=?");
                    $stmt->bind_param('ii', $newMainId, $medicatorId);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $success = 'Картинка удалена';
        }
    }
}

// For slug auto we need subfilters list (nice datalist).
$subfilters = $mysqli->query("SELECT id, slug, name FROM subfilter ORDER BY name ASC");
$subfilterOptions = [];
if ($subfilters) {
    while ($sf = $subfilters->fetch_assoc()) {
        $subfilterOptions[] = $sf;
    }
    $subfilters->free();
}

$medicators = $mysqli->query("SELECT * FROM medicator ORDER BY id DESC");
admin_page_start('Админка: Медикаторы + Галерея');
?>

<div class="card">
    <h2>Поиск по медикаторам</h2>
    <p class="muted">Введи ID или часть названия, чтобы быстро найти нужную карточку.</p>
    <div class="grid">
        <div>
            <label>Поиск</label>
            <input id="medicatorSearch" placeholder="Например: 25 или master pro">
        </div>
        <div>
            <label>Быстрый переход по ID</label>
            <div style="display:flex;gap:8px;">
                <input id="medicatorJumpId" placeholder="ID карточки">
                <button type="button" id="medicatorJumpBtn">Перейти</button>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
        <button type="button" id="expandAllMedicators">Развернуть все</button>
        <button type="button" id="collapseAllMedicators">Свернуть все</button>
        <button type="button" id="toggleSimpleMode">Простой режим: выкл</button>
    </div>
</div>

<div class="card">
    <h2>Создать медикатор</h2>
    <?php if ($success): ?>
        <div class="msg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg" style="border-color:#f5c6cb;color:#721c24;background:#f8d7da;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="grid" enctype="multipart/form-data">
        <input type="hidden" name="action" value="medicator_create">

        <div>
            <label>Название медикатора</label>
            <input name="name" class="slug-source" placeholder="Например: Master Pro 2000" required data-slug-target="slug_create">
        </div>

        <div>
            <label>Slug (можно менять вручную)</label>
            <input id="slug_create" name="slug" class="slug-target" placeholder="латиница, дефисы">
        </div>
        <div>
            <label>Категория (slug subfilter)</label>
            <input name="filtr" list="subfilter_list" placeholder="Например: dosatron-d25" required>
        </div>
        <datalist id="subfilter_list">
            <?php foreach ($subfilterOptions as $sf): ?>
                <option value="<?= htmlspecialchars($sf['slug']) ?>">
                    <?= htmlspecialchars($sf['name']) ?>
                </option>
            <?php endforeach; ?>
        </datalist>

        <div class="advanced-field"><label>Диапазон дозирования</label><input name="d_dosing" placeholder="d_dosing"></div>
        <div class="advanced-field"><label>Производительность</label><input name="performance" placeholder="performance"></div>
        <div class="advanced-field"><label>Давление</label><input name="pressure" placeholder="pressure"></div>
        <div class="advanced-field"><label>Температура</label><input name="temperature" placeholder="temperature"></div>
        <div class="advanced-field"><label>Подключения</label><input name="connections" placeholder="connections"></div>
        <div class="advanced-field"><label>Материал уплотнений</label><input name="m_seal" placeholder="m_seal"></div>
        <div class="advanced-field"><label>Материал корпуса</label><input name="m_case" placeholder="m_case"></div>
        <div class="advanced-field"><label>Дополнительно</label><input name="dop" placeholder="dop"></div>
        <div class="advanced-field"><label>Passport URL</label><input name="passport" placeholder="ссылка на PDF"></div>
        <div class="advanced-field"><label>User Pass URL</label><input name="user_pass" placeholder="ссылка на PDF"></div>
        <div class="advanced-field"><label>Загрузить Passport PDF</label><input type="file" name="passport_file" accept=".pdf,application/pdf"></div>
        <div class="advanced-field"><label>Загрузить User Pass PDF</label><input type="file" name="user_pass_file" accept=".pdf,application/pdf"></div>

        <div style="grid-column:1/-1;">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0;">
                <button type="button" class="bb-btn" data-open="[b]" data-close="[/b]">Ж</button>
                <button type="button" class="bb-btn" data-open="[i]" data-close="[/i]">К</button>
                <button type="button" class="bb-btn" data-open="[u]" data-close="[/u]">Подч</button>
                <button type="button" class="bb-btn" data-open="[h2]" data-close="[/h2]">H2</button>
                <button type="button" class="bb-btn" data-open="[h3]" data-close="[/h3]">H3</button>
                <button type="button" class="bb-btn" data-open="[ul]" data-close="[/ul]">UL</button>
                    <button type="button" class="bb-btn" data-open="[li]" data-close="[/li]">LI</button>
                    <button type="button" class="bb-btn" data-open="[p]" data-close="[/p]">P</button>
            </div>
            <textarea name="opis" class="opis-textarea" rows="6" placeholder="opis (поддерживает BBCode: [b],[i],[u],[h2],[h3],[ul],[li])"></textarea>
        </div>

        <div style="grid-column:1/-1;">
            <button type="submit">Создать</button>
        </div>
    </form>
</div>

<?php if ($medicators): ?>
    <?php while ($m = $medicators->fetch_assoc()): ?>
        <?php $images = $mysqli->query("SELECT * FROM medicator_img WHERE medicator_id=" . (int)$m['id'] . " ORDER BY sort ASC, id ASC"); ?>

        <div class="card medicator-item" data-mid="<?= (int)$m['id'] ?>" data-mname="<?= htmlspecialchars(strtolower($m['name'] ?? '')) ?>">
            <details>
                <summary style="cursor:pointer;font-weight:700;">
                    #<?= (int)$m['id'] ?> — <?= htmlspecialchars($m['name']) ?>
                    <span style="margin-left:10px;font-size:12px;color:#475569;">
                        <?php
                        $imgCount = $images ? (int)$images->num_rows : 0;
                        $hasPassport = !empty($m['passport']);
                        $hasUserPass = !empty($m['user_pass']);
                        ?>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;">slug: <?= htmlspecialchars($m['slug'] ?? '-') ?></span>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#ecfeff;color:#155e75;">img: <?= $imgCount ?></span>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:<?= $hasPassport ? '#ecfdf3' : '#f1f5f9' ?>;color:<?= $hasPassport ? '#166534' : '#475569' ?>;">passport: <?= $hasPassport ? 'yes' : 'no' ?></span>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;background:<?= $hasUserPass ? '#ecfdf3' : '#f1f5f9' ?>;color:<?= $hasUserPass ? '#166534' : '#475569' ?>;">userpass: <?= $hasUserPass ? 'yes' : 'no' ?></span>
                    </span>
                </summary>

                <form method="post" class="grid" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="medicator_update">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">

                    <div>
                        <label>Название</label>
                        <input name="name" value="<?= htmlspecialchars($m['name']) ?>" class="slug-source" data-slug-target="slug_m<?= (int)$m['id'] ?>">
                    </div>
                    <div>
                        <label>Slug</label>
                        <input id="slug_m<?= (int)$m['id'] ?>" name="slug" class="slug-target" value="<?= htmlspecialchars($m['slug'] ?? '') ?>" placeholder="slug">
                    </div>
                    <div>
                        <label>Категория (subfilter slug)</label>
                        <input name="filtr" list="subfilter_list" value="<?= htmlspecialchars($m['filtr'] ?? '') ?>" placeholder="filtr (subfilter slug)" required>
                    </div>

                    <div class="advanced-field"><label>Диапазон дозирования</label><input name="d_dosing" value="<?= htmlspecialchars($m['d_dosing'] ?? '') ?>" placeholder="d_dosing"></div>
                    <div class="advanced-field"><label>Производительность</label><input name="performance" value="<?= htmlspecialchars($m['performance'] ?? '') ?>" placeholder="performance"></div>
                    <div class="advanced-field"><label>Давление</label><input name="pressure" value="<?= htmlspecialchars($m['pressure'] ?? '') ?>" placeholder="pressure"></div>
                    <div class="advanced-field"><label>Температура</label><input name="temperature" value="<?= htmlspecialchars($m['temperature'] ?? '') ?>" placeholder="temperature"></div>
                    <div class="advanced-field"><label>Подключения</label><input name="connections" value="<?= htmlspecialchars($m['connections'] ?? '') ?>" placeholder="connections"></div>
                    <div class="advanced-field"><label>Материал уплотнений</label><input name="m_seal" value="<?= htmlspecialchars($m['m_seal'] ?? '') ?>" placeholder="m_seal"></div>
                    <div class="advanced-field"><label>Материал корпуса</label><input name="m_case" value="<?= htmlspecialchars($m['m_case'] ?? '') ?>" placeholder="m_case"></div>
                    <div class="advanced-field"><label>Дополнительно</label><input name="dop" value="<?= htmlspecialchars($m['dop'] ?? '') ?>" placeholder="dop"></div>
                    <div class="advanced-field">
                        <label>Passport URL</label>
                        <input name="passport" value="<?= htmlspecialchars($m['passport'] ?? '') ?>" placeholder="passport">
                        <?php if (!empty($m['passport'])): ?>
                            <div><a href="<?= htmlspecialchars((substr($m['passport'],0,1)==='/'?$m['passport']:'/'.$m['passport'])) ?>" target="_blank">Открыть текущий Passport</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="advanced-field">
                        <label>User Pass URL</label>
                        <input name="user_pass" value="<?= htmlspecialchars($m['user_pass'] ?? '') ?>" placeholder="user_pass">
                        <?php if (!empty($m['user_pass'])): ?>
                            <div><a href="<?= htmlspecialchars((substr($m['user_pass'],0,1)==='/'?$m['user_pass']:'/'.$m['user_pass'])) ?>" target="_blank">Открыть текущий User Pass</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="advanced-field"><label>Загрузить Passport PDF</label><input type="file" name="passport_file" accept=".pdf,application/pdf"></div>
                    <div class="advanced-field"><label>Загрузить User Pass PDF</label><input type="file" name="user_pass_file" accept=".pdf,application/pdf"></div>

                    <div style="grid-column:1/-1;">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0;">
                            <button type="button" class="bb-btn" data-open="[b]" data-close="[/b]">Ж</button>
                            <button type="button" class="bb-btn" data-open="[i]" data-close="[/i]">К</button>
                            <button type="button" class="bb-btn" data-open="[u]" data-close="[/u]">Подч</button>
                            <button type="button" class="bb-btn" data-open="[h2]" data-close="[/h2]">H2</button>
                            <button type="button" class="bb-btn" data-open="[h3]" data-close="[/h3]">H3</button>
                            <button type="button" class="bb-btn" data-open="[ul]" data-close="[/ul]">UL</button>
                            <button type="button" class="bb-btn" data-open="[li]" data-close="[/li]">LI</button>
                            <button type="button" class="bb-btn" data-open="[p]" data-close="[/p]">P</button>
                        </div>
                        <textarea
                            name="opis"
                            class="opis-textarea"
                            rows="6"
                            placeholder="opis"
                        ><?= htmlspecialchars($m['opis'] ?? '') ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <button type="submit">Сохранить</button>
                    </div>
                </form>

                <form method="post" onsubmit="return confirm('Удалить медикатор #<?= (int)$m['id'] ?>?')" style="margin:8px 0 0 0;">
                    <input type="hidden" name="action" value="medicator_delete">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="danger">Удалить медикатор</button>
                </form>

                <hr>

                <h3 style="margin-top:10px;">Галерея: загрузка новых картинок</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="medicator_img_add">
                    <input type="hidden" name="medicator_id" value="<?= (int)$m['id'] ?>">
                    <div class="grid" style="align-items:end;">
                        <input type="file" name="img_files[]" accept="image/*" multiple required>
                        <label style="display:flex;gap:8px;align-items:center;margin:0;">
                            <input type="checkbox" name="make_main_first" value="1">
                            Сделать главным первой загруженной
                        </label>
                        <button type="submit">Добавить</button>
                    </div>
                </form>

                <h3>Существующие картинки</h3>
                <table>
                    <tr><th>ID</th><th>Картинка</th><th>Правка</th><th>Удалить</th></tr>
                    <?php if ($images && $images->num_rows > 0): ?>
                        <?php while ($img = $images->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$img['id'] ?></td>
                                <td>
                                    <?php $src = admin_img_src($img['path_img'] ?? ''); ?>
                                    <?php if ($src): ?>
                                        <img src="<?= htmlspecialchars($src) ?>" style="max-width:90px;max-height:60px;object-fit:cover;" alt="">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display:flex;flex-direction:column;gap:6px;">
                                        <input type="hidden" name="action" value="medicator_img_update">
                                        <input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>">
                                        <input type="hidden" name="medicator_id" value="<?= (int)$m['id'] ?>">
                                        <input type="hidden" name="is_main" value="0">
                                        <input type="checkbox" name="is_main" value="1" <?= (int)$img['is_Main'] === 1 ? 'checked' : '' ?>>
                                        <input name="sort" type="number" value="<?= (int)$img['sort'] ?>" style="width:120px;">
                                        <input name="path_img" value="<?= htmlspecialchars($img['path_img'] ?? '') ?>">
                                        <button type="submit">OK</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Удалить картинку #<?= (int)$img['id'] ?>?')" style="margin:0;">
                                        <input type="hidden" name="action" value="medicator_img_delete">
                                        <input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>">
                                        <input type="hidden" name="medicator_id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="danger">Del</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="muted">Картинок пока нет</td></tr>
                    <?php endif; ?>
                </table>
            </details>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

<script>
// BBCode toolbar: wrap selection in nearest textarea.
function bbWrapSelection(textarea, openTag, closeTag) {
    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || 0;
    const selected = textarea.value.substring(start, end);
    const before = textarea.value.substring(0, start);
    const after = textarea.value.substring(end);
    textarea.value = before + openTag + selected + closeTag + after;
    textarea.focus();
    const newPos = start + openTag.length;
    textarea.setSelectionRange(newPos, newPos + selected.length);
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.bb-btn');
    if (!btn) return;
    const form = btn.closest('form') || btn.closest('details');
    if (!form) return;
    const textarea = form.querySelector('textarea.opis-textarea');
    if (!textarea) return;
    const openTag = btn.getAttribute('data-open') || '';
    const closeTag = btn.getAttribute('data-close') || '';
    bbWrapSelection(textarea, openTag, closeTag);
});

function slugifyRuToEn(text) {
    if (!text) return '';
    text = String(text).toLowerCase().trim();
    const map = {
        'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t',
        'у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
    };
    let out = '';
    for (const ch of text) {
        out += (map.hasOwnProperty(ch) ? map[ch] : ch);
    }
    out = out.replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    return out;
}

// Auto slug per form: if user didn't manually change slug - update it from name.
document.querySelectorAll('form').forEach(function (form) {
    const nameInputs = form.querySelectorAll('.slug-source');
    const slugInputs = form.querySelectorAll('.slug-target');
    if (!slugInputs || slugInputs.length === 0) return;

    slugInputs.forEach(function(sl) { sl.dataset.manual = '0'; sl.addEventListener('input', function(){ sl.dataset.manual = '1'; }); });

    nameInputs.forEach(function (nameInput) {
        nameInput.addEventListener('input', function () {
            const targetId = nameInput.getAttribute('data-slug-target');
            if (!targetId) return;
            const sl = form.querySelector('#' + targetId) || document.getElementById(targetId);
            if (!sl) return;
            if (sl.dataset.manual === '0') {
                const gen = slugifyRuToEn(nameInput.value);
                if (gen) sl.value = gen;
            }
        });
    });
});

// Simple search by id or name.
var searchInput = document.getElementById('medicatorSearch');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        var q = String(searchInput.value || '').toLowerCase().trim();
        document.querySelectorAll('.medicator-item').forEach(function (item) {
            var id = String(item.getAttribute('data-mid') || '').toLowerCase();
            var name = String(item.getAttribute('data-mname') || '').toLowerCase();
            var ok = !q || id.indexOf(q) !== -1 || name.indexOf(q) !== -1;
            item.style.display = ok ? '' : 'none';
        });
    });
}

var expandBtn = document.getElementById('expandAllMedicators');
var collapseBtn = document.getElementById('collapseAllMedicators');
if (expandBtn) {
    expandBtn.addEventListener('click', function () {
        document.querySelectorAll('.medicator-item details').forEach(function (d) { d.open = true; });
    });
}
if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
        document.querySelectorAll('.medicator-item details').forEach(function (d) { d.open = false; });
    });
}

var jumpInput = document.getElementById('medicatorJumpId');
var jumpBtn = document.getElementById('medicatorJumpBtn');
if (jumpInput && jumpBtn) {
    jumpBtn.addEventListener('click', function () {
        var id = String((jumpInput.value || '')).trim();
        if (!id) return;
        var card = document.querySelector('.medicator-item[data-mid="' + id + '"]');
        if (!card) return;
        var details = card.querySelector('details');
        if (details) details.open = true;
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        card.style.outline = '2px solid #f97316';
        setTimeout(function(){ card.style.outline = ''; }, 1500);
    });
}

var simpleModeBtn = document.getElementById('toggleSimpleMode');
var simpleModeEnabled = false;
if (simpleModeBtn) {
    simpleModeBtn.addEventListener('click', function () {
        simpleModeEnabled = !simpleModeEnabled;
        document.querySelectorAll('.advanced-field').forEach(function (el) {
            el.style.display = simpleModeEnabled ? 'none' : '';
        });
        simpleModeBtn.textContent = simpleModeEnabled ? 'Простой режим: вкл' : 'Простой режим: выкл';
    });
}
</script>

<?php admin_page_end(); ?>

