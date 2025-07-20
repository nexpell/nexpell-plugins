<?php
// error_reporting aktivieren bei Bedarf
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\AccessControl;
use webspell\LanguageService;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('download');

// Style-Klasse laden
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('download'),
    'subtitle' => 'Download'
];
echo $tpl->loadTemplate("download", "head", $data_array, 'plugin');


// Wenn ?id= gesetzt, dann Download ausliefern
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $query = "SELECT * FROM plugins_downloads WHERE id = $id";
    $result = safe_query($query);
    $dl = $result ? $result->fetch_assoc() : null;

    if (!$dl) {
        die("Datei nicht gefunden");
    }

    // Prüfe Download-Verzeichnis
    $allowedDir = realpath(__DIR__ . "/files/");
    if (!$allowedDir || !is_dir($allowedDir)) {
        die("Download-Verzeichnis existiert nicht oder ist ungültig");
    }
    $allowedDir .= DIRECTORY_SEPARATOR;

    $basename = basename($dl['file']);
    $fullpath = realpath($allowedDir . $basename);

    if (!$fullpath || strpos($fullpath, $allowedDir) !== 0) {
        die("Ungültiger oder nicht erlaubter Pfad");
    }

    if (!file_exists($fullpath)) {
        die("Datei existiert nicht");
    }

    // Rollenzugriff prüfen
    $allowedRoles = [];
    if (!empty($dl['access_roles'])) {
        $allowedRoles = json_decode($dl['access_roles'], true);
        if (!is_array($allowedRoles)) {
            $allowedRoles = [];
        }
    }

    if (!empty($allowedRoles) && !AccessControl::hasAnyRole($allowedRoles)) {
        die("Zugriff verweigert");
    }

    // Download-Zähler hochzählen
    safe_query("UPDATE plugins_downloads SET downloads = downloads + 1 WHERE id = $id");

    // Download-Log schreiben
    $userID = $_SESSION['userID'] ?? 0;
    if ($userID > 0) {
        $stmt = $_database->prepare("
            INSERT INTO plugins_downloads_logs (userID, fileID)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $userID, $id);
        $stmt->execute();
        $stmt->close();
    }

    // Datei ausliefern
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fullpath) . '"');
    header('Content-Length: ' . filesize($fullpath));
    readfile($fullpath);
    exit;
}

// Download-Liste anzeigen
echo '<div class="container py-4">';

// Kategorien und Dateien laden
$sql = "
SELECT
    c.categoryID,
    c.title AS cat_title,
    c.description AS cat_desc,
    d.id AS downloadID,
    d.title AS dl_title,
    d.description AS dl_desc,
    d.downloads,
    d.access_roles,
    d.uploaded_at
FROM plugins_downloads_categories c
LEFT JOIN plugins_downloads d ON d.categoryID = c.categoryID
ORDER BY c.categoryID, d.uploaded_at DESC
";

$result = safe_query($sql);
if (!$result) {
    echo '<div class="alert alert-danger">Fehler beim Laden der Downloads: ' . htmlspecialchars($_database->error) . '</div>';
    exit;
}

$currentCatID = null;
$hasVisibleInCategory = false;

while ($row = $result->fetch_assoc()) {
    if ($currentCatID !== $row['categoryID']) {
        if ($currentCatID !== null) {
            if (!$hasVisibleInCategory) {
                echo '<div class="alert alert-info"><p class="fst-italic text-muted"><i class="bi bi-info-circle"></i> In dieser Kategorie sind keine für Sie sichtbaren Downloads vorhanden.</p></div>';
            }
            echo '</div>'; // row schließen
            echo '</section>';
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

    if (empty($row['downloadID'])) {
        echo '<div class="alert alert-info"><p class="fst-italic text-muted"><i class="bi bi-info-circle"></i> Keine Downloads in dieser Kategorie.</p></div>';
        continue;
    }

    // Rollenzugriff prüfen
    $allowedRoles = [];
    if (!empty($row['access_roles'])) {
        $allowedRoles = json_decode($row['access_roles'], true);
        if (!is_array($allowedRoles)) {
            $allowedRoles = [];
        }
    }

    if (empty($allowedRoles) || AccessControl::hasAnyRole($allowedRoles)) {
        $hasVisibleInCategory = true;

        echo '<div class="col-md-12">';
        echo '<div class="card h-100 shadow-sm">';
        echo '  <div class="card-body d-flex flex-column">';
        echo '    <h5 class="card-title">' . htmlspecialchars($row['dl_title']) . '</h5>';
        if (!empty($row['dl_desc'])) {
            //echo '<p class="card-text text-truncate" title="' . htmlspecialchars($row['dl_desc']) . '">' . htmlspecialchars($row['dl_desc']) . '</p>';
            echo '<p class="card-text" title="' . htmlspecialchars($row['dl_desc']) . '">' . $row['dl_desc'] . '</p>';
        }
        echo '    <div class="mt-auto">';
        if (!empty($row['uploaded_at']) && strtotime($row['uploaded_at']) !== false) {
            $uploadedDate = date("d.m.Y", strtotime($row['uploaded_at']));
            echo '<p class="card-subtitle mb-2 text-muted"><small>Hochgeladen am: ' . $uploadedDate . '</small></p>';
        }
        echo '      <small class="text-muted">Downloads: ' . intval($row['downloads']) . '</small><br>';
        echo '      <a href="index.php?site=downloads&id=' . intval($row['downloadID']) . '" class="btn btn-primary btn-sm mt-2">Download</a>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        echo '</div>';
    }
}

// Letzte Kategorie schließen
if ($currentCatID !== null) {
    if (!$hasVisibleInCategory) {
        echo '<div class="alert alert-info"><p class="fst-italic text-muted"><i class="bi bi-info-circle"></i> In dieser Kategorie sind keine für Sie sichtbaren Downloads vorhanden.</p></div>';
    }
    echo '</div>'; // row schließen
    echo '</section>';
}

echo '</div>'; // container schließen
