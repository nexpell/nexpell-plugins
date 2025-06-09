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
global $_database,$languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('sponsors');

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('sponsors');

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

        $filename = uniqid('sponsor_') . '.' . $ext;
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
    $res = $_database->query("SELECT logo FROM plugins_sponsors WHERE id = $id");
    $row = $res->fetch_assoc();
    if ($row && $row['logo']) {
        @unlink($uploadDir . $row['logo']);
    }
    $_database->query("DELETE FROM plugins_sponsors WHERE id = $id");
    header("Location: admincenter.php?site=admin_sponsors");
    exit;
}

// POST: Add/Edit Sponsor speichern
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sponsor'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $_database->real_escape_string(trim($_POST['name']));
    $slug = $_database->real_escape_string(trim($_POST['slug']));
    $level = $_database->real_escape_string(trim($_POST['level']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $userID      = (int)$_SESSION['userID'];

    $oldLogo = '';
    if ($id > 0) {
        $res = $_database->query("SELECT logo FROM plugins_sponsors WHERE id = $id");
        $row = $res->fetch_assoc();
        $oldLogo = $row['logo'] ?? '';
    }

    $uploadResult = handleLogoUpload($_FILES['logo'] ?? null, $oldLogo);

    if (isset($uploadResult['error'])) {
        $error = $uploadResult['error'];
    } else {
        $logo = $uploadResult['filename'];

        if ($id > 0) {
            // Update
            $stmt = $_database->prepare("UPDATE plugins_sponsors SET name=?, slug=?, userID=?, level=?, logo=?, is_active=? WHERE id=?");
            $stmt->bind_param("sssssii", $name, $slug, $level, $userID, $logo, $is_active, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert
            $stmt = $_database->prepare("INSERT INTO plugins_sponsors (name, slug, level, userID, logo, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $name, $slug, $level, $userID, $logo, $is_active);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: admincenter.php?site=admin_sponsors");
        exit;
    }
}

// Sponsor-Level Auswahl (für Formular)
$levels = ['Platin Sponsor', 'Gold Sponsor', 'Silber Sponsor', 'Bronze Sponsor', 'Partner', 'Unterstützer'];

// === Anzeige abhängig von action ===
if ($action === 'add' || $action === 'edit') {

    $editSponsor = null;

    if ($action === 'edit' && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $res = $_database->query("SELECT * FROM plugins_sponsors WHERE id = $id");
        $editSponsor = $res->fetch_assoc();
    }

    ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> <?= $languageService->get('sponsors_manage') ?></div>
        <div>
            <a href="admincenter.php?site=admin_sponsors&action=add" class="btn btn-success btn-sm">
                <i class="bi bi-plus"></i> <?= $languageService->get('new') ?>
            </a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_sponsors"><?= $languageService->get('sponsors_manage') ?></a>
            </li>
            <li class="breadcrumb-item is_active" aria-current="page">
                <?= ($action === 'add' ? $languageService->get('sponsor_add') : $languageService->get('sponsor_edit')) ?>
            </li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">

           <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" action="admincenter.php?site=admin_sponsors&action=<?= $action ?><?= $editSponsor ? '&edit=' . (int)$editSponsor['id'] : '' ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($editSponsor['id'] ?? '') ?>">

                <div class="mb-3">
                    <label for="name" class="form-label"><?= $languageService->get('name') ?> *</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($editSponsor['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label"><?= $languageService->get('slug') ?></label>
                    <input type="slug" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($editSponsor['slug'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="level" class="form-label"><?= $languageService->get('sponsor_level') ?> *</label>
                    <select id="level" name="level" class="form-select" required>
                        <option value=""><?= $languageService->get('please_select') ?></option>
                        <?php foreach ($levels as $level): ?>
                            <option value="<?= htmlspecialchars($level) ?>" <?= (isset($editSponsor['level']) && $editSponsor['level'] === $level) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($level) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($editSponsor['logo'])): ?>
                    <div class="mt-2">
                        <img src="/includes/plugins/sponsors/images/<?= htmlspecialchars($editSponsor['logo']) ?>" alt="Logo" style="max-height:80px;">
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="logo" class="form-label"><?= $languageService->get('logo') ?></label>
                    <input type="file" class="form-control" id="logo" name="logo" <?= $editSponsor ? '' : 'required' ?>>                    
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= (!isset($editSponsor['is_active']) || $editSponsor['is_active'] == 1) ? 'checked' : '' ?>>
                    <label for="is_active" class="form-check-label"><?= $languageService->get('is_active') ?></label>
                </div>

                <button type="submit" name="save_sponsor" class="btn btn-primary">
                    <?= $editSponsor ? $languageService->get('save') : $languageService->get('add') ?>
                </button>
                <a href="admincenter.php?site=admin_sponsors" class="btn btn-secondary"><?= $languageService->get('back_to_list') ?></a>
            </form>


   

    <?php
} else {
    // Standard: Liste aller Sponsoren anzeigen
    $resSponsors = $_database->query("
    SELECT s.*, 
           COALESCE(k.click_count, 0) AS clicks
    FROM plugins_sponsors s
    LEFT JOIN (
        SELECT itemID, COUNT(*) AS click_count
        FROM link_clicks
        WHERE plugin = 'sponsors'
        GROUP BY itemID
    ) k ON s.id = k.itemID
    ORDER BY s.sort_order ASC
");
    ?>

    <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> <?= $languageService->get('sponsors_manage') ?></div>
        <div>
            <a href="admincenter.php?site=admin_sponsors&action=add" class="btn btn-success btn-sm">
                <i class="bi bi-plus"></i> <?= $languageService->get('new') ?>
            </a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_sponsors"><?= $languageService->get('sponsors_manage') ?></a>
            </li>
            <li class="breadcrumb-item is_active" aria-current="page">
                <?= $languageService->get('overview') ?>
            </li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">

        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= $languageService->get('logo') ?></th>
                    <th><?= $languageService->get('name') ?></th>
                    <th><?= $languageService->get('slug') ?></th>
                    <th><?= $languageService->get('sponsor_level') ?></th>
                    <th><?= $languageService->get('clicks_per_day') ?></th>
                    <th><?= $languageService->get('is_active') ?></th>
                    <th><?= $languageService->get('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($sponsor = $resSponsors->fetch_assoc()):
                    $createdTimestamp = isset($sponsor['created_at']) ? strtotime($sponsor['created_at']) : time();
                    $days = max(1, round((time() - $createdTimestamp) / (60 * 60 * 24))); 
                    $perday = round($sponsor['clicks'] / $days, 2);
                ?>
                <tr>
                    <td>
                        <?php if ($sponsor['logo'] && file_exists($uploadDir . $sponsor['logo'])): ?>
                            <img src="/includes/plugins/sponsors/images/<?= htmlspecialchars($sponsor['logo']) ?>" alt="<?= htmlspecialchars($sponsor['name']) ?> Logo" style="max-height:40px;">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($sponsor['name']) ?></td>
                    <td>
                        <?php if ($sponsor['slug']): ?>
                            <a href="<?= htmlspecialchars($sponsor['slug']) ?>" target="_blank" rel="nofollow"><?= htmlspecialchars($sponsor['slug']) ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($sponsor['level']) ?></td>
                    <td>
                        <?= (int)$sponsor['clicks'] ?> (Ø <?= $perday ?>/<?= $languageService->get('clicks_per_day') ?>)
                    </td>
                    <td><?= $sponsor['is_active'] ? $languageService->get('yes') : $languageService->get('no') ?></td>
                    <td>
                        <a href="admincenter.php?site=admin_sponsors&action=edit&edit=<?= $sponsor['id'] ?>" class="btn btn-sm btn-warning"><?= $languageService->get('edit') ?></a>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('<?= $languageService->get('confirm_delete') ?>');">
                            <input type="hidden" name="delete_id" value="<?= $sponsor['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= $languageService->get('delete') ?></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($resSponsors->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center"><?= $languageService->get('no_sponsors_found') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div></div></div>

    <?php
}
