<?php

use webspell\LanguageService;
use webspell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('partners');

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('partners');

// Einfaches Routing: action aus GET/POST
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

// Pfad zu Logo-Uploads
$uploadDir = dirname(__DIR__) . '/images/';

// Helper Funktion: Datei-Upload verarbeiten
function handleLogoUpload($file, $oldFile = null) {
    global $uploadDir;

    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowed)) {
            return ['error' => 'Nur JPG, PNG, GIF erlaubt'];
        }

        $filename = uniqid('partners_') . '.' . $ext;
        $target = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Altes Logo löschen
            if ($oldFile && file_exists($uploadDir . $oldFile)) {
                unlink($uploadDir . $oldFile);
            }
            return ['filename' => $filename];
        } else {
            return ['error' => 'Fehler beim Hochladen'];
        }
    }
    return ['filename' => $oldFile]; // Kein Upload -> altes behalten
}

// POST: Löschen
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    // Logo-Datei holen
    $res = $_database->query("SELECT logo FROM plugins_partners WHERE id = $id");
    $row = $res->fetch_assoc();
    if ($row && $row['logo']) {
        @unlink($uploadDir . $row['logo']);
    }
    $_database->query("DELETE FROM plugins_partners WHERE id = $id");
    header("Location: admincenter.php?site=admin_partners");
    exit;
}

// POST: Add/Edit partner speichern
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_partner'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = $_database->real_escape_string(trim($_POST['name']));
    $description = $_database->real_escape_string($_POST['description'] ?? '');
    $slug = $_database->real_escape_string(trim($_POST['slug']));
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $userID = (int)$_SESSION['userID'];

    $oldLogo = '';
    if ($id > 0) {
        $res = safe_query("SELECT logo FROM plugins_partners WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $oldLogo = $row['logo'] ?? '';
    }

    $uploadResult = handleLogoUpload($_FILES['logo'] ?? null, $oldLogo);

    if (isset($uploadResult['error'])) {
        $error = $uploadResult['error'];
    } else {
        $logo = $_database->real_escape_string($uploadResult['filename']);

        if ($id > 0) {
            safe_query(
                "UPDATE plugins_partners 
                 SET name='$name', slug='$slug', description='$description', userID=$userID, logo='$logo', is_active=$is_active, sort_order=$sort_order 
                 WHERE id=$id"
            );
        } else {
            safe_query(
                "INSERT INTO plugins_partners (name, slug, description, userID, logo, is_active, sort_order) 
                 VALUES ('$name', '$slug', '$description', $userID, '$logo', $is_active, $sort_order)"
            );
        }

        header("Location: admincenter.php?site=admin_partners");
        exit;
    }
}

if ($action === 'add' || $action === 'edit') {

    $editpartner = null;

    if ($action === 'edit' && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $res = $_database->query("SELECT * FROM plugins_partners WHERE id = $id");
        $editpartner = $res->fetch_assoc();
    }
    ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> <?= $languageService->get('partners_manage') ?></div>
        <div>
            <a href="admincenter.php?site=admin_partners&action=add" class="btn btn-success btn-sm">
                <i class="bi bi-plus"></i> <?= $languageService->get('partners_new') ?>
            </a>
            <a href="admincenter.php?site=admin_partners_settings" class="btn btn-primary btn-sm">
                <i class="bi bi-tags"></i> <?= $languageService->get('partners_settings') ?>
            </a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_partners"><?= $languageService->get('partners_manage') ?></a>
            </li>
            <li class="breadcrumb-item is_active" aria-current="page">
                <?= ($action === 'add' ? $languageService->get('partners_add') : $languageService->get('partners_edit')) ?>
            </li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="admincenter.php?site=admin_partners&action=<?= $action ?><?= $editpartner ? '&edit=' . (int)$editpartner['id'] : '' ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editpartner['id'] ?? '') ?>">

            <div class="mb-3">
                <label for="name" class="form-label"><?= $languageService->get('partners_name') ?> *</label>
                <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($editpartner['name'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label"><?= $languageService->get('partners_description') ?></label>
                <textarea class="ckeditor" name="description" rows="10"><?= htmlspecialchars($editpartner['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label for="slug" class="form-label"><?= $languageService->get('partners_slug') ?></label>
                <input type="slug" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($editpartner['slug'] ?? '') ?>">
            </div>

            <?php if (!empty($editpartner['logo'])): ?>
                <div class="mt-2">
                    <img src="/includes/plugins/partners/images/<?= htmlspecialchars($editpartner['logo']) ?>" class="img-thumbnail" width="200" alt="Banner">
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="logo" class="form-label"><?= $languageService->get('partners_logo') ?> (JPG, PNG, GIF)</label>
                <input type="file" class="form-control" id="logo" name="logo" <?= $editpartner ? '' : 'required' ?>>
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label"><?= $languageService->get('partners_sort_order') ?></label>
                <input class="form-control" type="number" name="sort_order" id="sort_order" value="<?= htmlspecialchars($editpartner['sort_order'] ?? 0) ?>">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= (!isset($editpartner['is_active']) || $editpartner['is_active'] == 1) ? 'checked' : '' ?>>
                <label for="is_active" class="form-check-label"><?= $languageService->get('active') ?></label>
            </div>

            <button type="submit" name="save_partner" class="btn btn-primary"><?= $editpartner ? $languageService->get('save') : $languageService->get('add') ?></button>
            <a href="admincenter.php?site=admin_partners" class="btn btn-secondary"><?= $languageService->get('back_to_list') ?></a>
        </form>

        </div>
    </div>
</div>

    <?php
} else {
    // Standard: Liste aller partneren anzeigen
    $respartners = $_database->query("
    SELECT s.*, 
           COALESCE(k.click_count, 0) AS clicks
    FROM plugins_partners s
    LEFT JOIN (
        SELECT itemID, COUNT(*) AS click_count
        FROM link_clicks
        WHERE plugin = 'partners'
        GROUP BY itemID
    ) k ON s.id = k.itemID
    ORDER BY s.sort_order ASC
");

?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> <?= $languageService->get('partners_manage') ?></div>
        <div>
            <a href="admincenter.php?site=admin_partners&action=add" class="btn btn-success btn-sm">
                <i class="bi bi-plus"></i> <?= $languageService->get('partners_new') ?>
            </a>
            <a href="admincenter.php?site=admin_partners_settings" class="btn btn-primary btn-sm">
                <i class="bi bi-tags"></i> <?= $languageService->get('partners_settings') ?>
            </a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_partners"><?= $languageService->get('partners_manage') ?></a>
            </li>
            <li class="breadcrumb-item is_active" aria-current="page"><?= $languageService->get('partners_breadcrumb') ?></li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= $languageService->get('partners_logo') ?></th>
                        <th><?= $languageService->get('partners_name') ?></th>
                        <th><?= $languageService->get('partners_slug') ?></th>
                        <th><?= $languageService->get('partners_clicks') ?></th>
                        <th><?= $languageService->get('partners_active') ?></th>
                        <th><?= $languageService->get('partners_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($partner = $respartners->fetch_assoc()):
                        $createdTimestamp = isset($partner['created_at']) ? strtotime($partner['created_at']) : time();
                        $days = max(1, round((time() - $createdTimestamp) / (60 * 60 * 24))); 
                        $perday = round($partner['clicks'] / $days, 2);
                    ?>
                    <tr>
                        <td>
                            <?php if ($partner['logo'] && file_exists($uploadDir . $partner['logo'])): ?>
                                <img src="/includes/plugins/partners/images/<?= htmlspecialchars($partner['logo']) ?>" alt="Logo" style="max-height:40px;">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($partner['name']) ?></td>
                        <td>
                            <?php if ($partner['slug']): ?>
                                <a href="<?= htmlspecialchars($partner['slug']) ?>" target="_blank" rel="nofollow"><?= htmlspecialchars($partner['slug']) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= (int)$partner['clicks'] ?> (Ø <?= $perday ?>/Tag)
                        </td>
                        <td><?= $partner['is_active'] ? $languageService->get('yes') : $languageService->get('no') ?></td>
                        <td>
                            <a href="admincenter.php?site=admin_partners&action=edit&edit=<?= $partner['id'] ?>" class="btn btn-sm btn-warning">
                                <?= $languageService->get('partners_edit') ?>
                            </a>
                            <form method="post" style="display:inline-block" onsubmit="return confirm('<?= $languageService->get('partners_delete_confirm') ?>');">
                                <input type="hidden" name="delete_id" value="<?= $partner['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <?= $languageService->get('partners_delete') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($respartners->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <?= $languageService->get('partners_none_found') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <?php
}
