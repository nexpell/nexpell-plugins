<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
use nexpell\LanguageService;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('downloads');

// Style-Klasse laden
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Downloads'
];
echo $tpl->loadTemplate("downloads", "head", $data_array, 'plugin');


$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Funktion: Zugriff prüfen
function hasAccess($accessRolesJson): bool {
    if (empty($accessRolesJson)) return true;
    $roles = json_decode($accessRolesJson, true);
    if (!is_array($roles)) return true;
    return AccessControl::hasAnyRole($roles);
}

// Aktion: Datei herunterladen
if ($action === 'download' && $id > 0) {
    $result = safe_query("SELECT * FROM plugins_downloads WHERE id = $id");
    $dl = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;

    if (!$dl || !hasAccess($dl['access_roles'])) {
        die("Download nicht erlaubt oder nicht gefunden.");
    }

    $file = basename($dl['file']);
    $fullpath = realpath(__DIR__ . "/files/$file");
    $allowedDir = realpath(__DIR__ . "/files/") . DIRECTORY_SEPARATOR;

    if (!$fullpath || !file_exists($fullpath) || strpos($fullpath, $allowedDir) !== 0) {
        die("Datei ungültig oder fehlt.");
    }

    // Download-Zähler & Log
    if (!empty($_SESSION['userID'])) {
        $userID = (int)$_SESSION['userID'];
        $stmt = $_database->prepare("INSERT INTO plugins_downloads_logs (userID, fileID) VALUES (?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ii", $userID, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("DB-Fehler: " . $_database->error);
        }
    }

    // Datei ausliefern
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($fullpath));
    readfile($fullpath);
    exit;
}

if ($action === 'cat_list' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $categoryID = (int)$_GET['id'];

    // Kategorie-Titel holen
    $catResult = safe_query("SELECT title FROM plugins_downloads_categories WHERE categoryID = $categoryID");
    $catRow = mysqli_fetch_assoc($catResult);
    $catTitle = $catRow['title'] ?? 'Kategorie';

    echo '<nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?site=downloads">Downloads</a></li>
            <li class="breadcrumb-item">' . htmlspecialchars($catTitle) . '</li>
        </ol>
    </nav>';

    $result = safe_query("SELECT * FROM plugins_downloads WHERE categoryID = $categoryID ORDER BY updated_at DESC");

    if (mysqli_num_rows($result)) {
        echo '<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">';

        while ($row = mysqli_fetch_assoc($result)) {
            $title      = htmlspecialchars($row['title']);
            $descText   = strip_tags($row['description']);
            $descShort  = (mb_strlen($descText) > 150) ? mb_substr($descText, 0, 150) . '…' : $descText;
            $uploaded = !empty($row['uploaded_at']) ? date("d.m.Y", strtotime($row['uploaded_at'])) : null;
        $updated = !empty($row['updated_at']) ? date("d.m.Y", strtotime($row['updated_at'])) : null;
            $downloads  = (int)$row['downloads'];
            $detailLink = 'index.php?site=downloads&action=watch&id=' . (int)$row['id'];

           ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <span class="text-primary"><?= $title ?></span>
                        </h5>

                        <?php if (!empty($descText)): ?>
                            <p class="card-text text-truncate" title="<?= htmlspecialchars($descText) ?>"><?= $descShort ?></p>
                        <?php endif; ?>

                        <div class="mt-auto">
                            <ul class="list-unstyled mb-2 small text-muted">
                                <?php if ($uploaded): ?>
                                    <li><i class="bi bi-upload me-2"></i><strong>Hochgeladen am:</strong> <?= $uploaded ?></li>
                                <?php endif; ?>
                                <?php if ($updated && $updated !== $uploaded): ?>
                                    <li><i class="bi bi-pencil-square me-2"></i><strong>Zuletzt aktualisiert:</strong> <span class="text-warning fw-bold"><?= $updated ?></span></li>
                                <?php endif; ?>
                                <li><i class="bi bi-download me-2"></i><strong>Downloads:</strong> <?= $downloads ?></li>
                            </ul>

                            <a href="<?= $detailLink ?>" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-info-circle"></i> Details & Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        echo '</div>';
    } else {
        echo '<div class="alert alert-info">' . $languageService->get('no_downloads_in_this_category') . '</div>';
    }
}

 

// Aktion: Detailansicht

// Detailansicht
elseif ($action === 'detail' && $id > 0) {
    // JOIN, damit auch Kategorie geladen wird
    $sql = "
        SELECT d.*, c.title AS category_title, c.categoryID,
               (SELECT COUNT(*) FROM plugins_downloads_logs l WHERE l.fileID = d.id) AS download_count
        FROM plugins_downloads d
        LEFT JOIN plugins_downloads_categories c ON d.categoryID = c.categoryID
        WHERE d.id = $id
    ";
    $result = safe_query($sql);
    if (!$result || $result->num_rows === 0) {
        die('Download nicht gefunden');
    }

    $dl = $result->fetch_assoc();
    if (!hasAccess($dl['access_roles'])) {
        die("<div class=\"alert alert-danger\">Kein Zugriff</div>");
    }

    $title = htmlspecialchars($dl['title']);
    $desc = $dl['description'];
    $uploaded = !empty($dl['uploaded_at']) ? date("d.m.Y", strtotime($dl['uploaded_at'])) : '–';
    $updated = !empty($dl['updated_at']) ? date("d.m.Y", strtotime($dl['updated_at'])) : '–';
    $downloads = intval($dl['download_count']);
    $downloadLink = "index.php?site=downloads&action=download&id=$id";

    // Kategorie
    $catTitle = htmlspecialchars($dl['category_title']);
    $catID = intval($dl['categoryID']);
    $catLink = "index.php?site=downloads&action=cat_list&id=$catID";

    ?>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php?site=downloads">Downloads</a></li>
        <li class="breadcrumb-item"><a href="<?= $catLink ?>"><?= $catTitle ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $title ?></li>
      </ol>
    </nav>

    <div class="card mb-4">
        <div class="card-header">
            <h4><?= $title ?></h4>
        </div>
        <div class="card-body">
            <p><strong>Beschreibung:</strong><br><?= $desc ?></p>
            <div class="bg-light border rounded p-2 mb-2">
                <p class="mb-1 mt-3 fw-semibold text-muted">Datei-Info</p>
                <ul class="list-unstyled small text-muted">
                    <li><i class="bi bi-upload me-2"></i><strong>Hochgeladen am:</strong> <?= $uploaded ?></li>
                    <li><i class="bi bi-pencil-square me-2"></i><strong>Zuletzt aktualisiert:</strong> <?= $updated ?></li>
                    <li><i class="bi bi-download me-2"></i><strong>Downloads:</strong> <?= $downloads ?></li>
                </ul>
            </div>
            <a href="<?= $downloadLink ?>" class="btn btn-primary">
                <i class="bi bi-download"></i> Jetzt herunterladen
            </a>
        </div>
    </div>

    <?php
    exit;
} else {



    // Aktion: Übersicht (Standard oder ?action=list)
    $sql = "
    SELECT
        c.categoryID,
        c.title AS cat_title,
        c.description AS cat_desc,
        d.id AS downloadID,
        d.title AS dl_title,
        d.description AS dl_desc,
        d.access_roles,
        d.uploaded_at,
        d.updated_at,
        (SELECT COUNT(*) FROM plugins_downloads_logs l WHERE l.fileID = d.id) AS download_count
    FROM plugins_downloads_categories c
    LEFT JOIN plugins_downloads d ON d.categoryID = c.categoryID
    ORDER BY c.categoryID, d.uploaded_at DESC
    ";

    $result = safe_query($sql);
    if (!$result) {
        echo '<div class="alert alert-danger">Fehler beim Laden der Downloads.</div>';
        exit;
    }

    $catID = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $catTitle = '';
    $catLink = '';

    // Falls Kategorie gesetzt, aus DB Titel holen
    if ($catID) {
        $catSql = "SELECT title FROM plugins_downloads_categories WHERE categoryID = $catID LIMIT 1";
        $catResult = safe_query($catSql);
        if ($catResult && $catRow = $catResult->fetch_assoc()) {
            $catTitle = htmlspecialchars($catRow['title']);
            $catLink = "index.php?site=downloads&action=cat_list&id=$catID";
        }
    }

    // Beispiel: Falls Detailseite, Titel des Downloads setzen (optional)
    $title = '';
    if (isset($_GET['action']) && $_GET['action'] === 'detail' && isset($_GET['id'])) {
        $dlID = (int)$_GET['id'];
        $dlSql = "SELECT title FROM plugins_downloads WHERE id = $dlID LIMIT 1";
        $dlResult = safe_query($dlSql);
        if ($dlResult && $dlRow = $dlResult->fetch_assoc()) {
            $title = htmlspecialchars($dlRow['title']);
        }
    }


    ?>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php?site=downloads">Downloads</a></li>

        <?php if ($catID && $catTitle): ?>
            <li class="breadcrumb-item"><a href="<?= $catLink ?>"><?= $catTitle ?></a></li>
        <?php endif; ?>

        <?php if (!empty($title)): ?>
            <li class="breadcrumb-item active" aria-current="page"><?= $title ?></li>
        <?php endif; ?>
      </ol>
    </nav>
    <?php

    $currentCatID = null;
    $hasVisibleInCategory = false;

    while ($row = $result->fetch_assoc()) {
        if ($currentCatID !== $row['categoryID']) {
            if ($currentCatID !== null) {
                if (!$hasVisibleInCategory) {
                    echo '<div class="alert alert-info">Keine sichtbaren Downloads.</div>';
                }
                echo '</div></section>';
            }

            echo '<section class="mb-5">';
            echo '<h4>' . htmlspecialchars($row['cat_title']) . '</h4>';
            if (!empty($row['cat_desc'])) {
                echo '<p class="text-secondary">' . htmlspecialchars($row['cat_desc']) . '</p>';
            }
            echo '<div class="row row-cols-1 row-cols-md-3 g-4">';
            $currentCatID = $row['categoryID'];
            $hasVisibleInCategory = false;
        }

        if (empty($row['downloadID']) || !hasAccess($row['access_roles'])) {
            continue;
        }

        $hasVisibleInCategory = true;
        $title = htmlspecialchars($row['dl_title']);
        #$desc = $row['dl_desc'];
        $uploaded = !empty($row['uploaded_at']) ? date("d.m.Y", strtotime($row['uploaded_at'])) : null;
        $updated = !empty($row['updated_at']) ? date("d.m.Y", strtotime($row['updated_at'])) : null;
        $downloads = intval($row['download_count']);
        $detailLink = 'index.php?site=downloads&action=detail&id=' . intval($row['downloadID']);
        $downloadLink = 'index.php?site=downloads&action=download&id=' . intval($row['downloadID']);

        $descText = strip_tags($row['dl_desc']); // HTML entfernen
        $maxLength = 100; // max Zeichen
        if (mb_strlen($descText) > $maxLength) {
            $descShort = mb_substr($descText, 0, $maxLength) . '...';
        } else {
            $descShort = $descText;
        }
        ?>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <span class="text-primary"><?= $title ?></span>
                    </h5>

                    <?php if (!empty($descText)): ?>
                        <p class="card-text text-truncate" title="<?= htmlspecialchars($descText) ?>"><?= $descShort ?></p>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <ul class="list-unstyled mb-2 small text-muted">
                            <?php if ($uploaded): ?>
                                <li><i class="bi bi-upload me-2"></i><strong>Hochgeladen am:</strong> <?= $uploaded ?></li>
                            <?php endif; ?>
                            <?php if ($updated && $updated !== $uploaded): ?>
                                <li><i class="bi bi-pencil-square me-2"></i><strong>Zuletzt aktualisiert:</strong> <span class="text-warning fw-bold"><?= $updated ?></span></li>
                            <?php endif; ?>
                            <li><i class="bi bi-download me-2"></i><strong>Downloads:</strong> <?= $downloads ?></li>
                        </ul>

                        <a href="<?= $detailLink ?>" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-info-circle"></i> Details & Download
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }

    if ($currentCatID !== null) {
        if (!$hasVisibleInCategory) {
            echo '<div class="alert alert-info">Keine sichtbaren Downloads in dieser Kategorie.</div>';
        }
        echo '</div></section>';
    } else {
        echo '<div class="alert alert-warning">Es sind derzeit keine Downloads verfügbar.</div>';
    }
}
?>
