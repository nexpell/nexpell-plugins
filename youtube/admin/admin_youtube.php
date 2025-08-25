<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\NavigationUpdater;// SEO Anpassung

$_SESSION['language'] = $_SESSION['language'] ?? 'de';

global $languageService,$_database;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('youtube');

AccessControl::checkAdminAccess('youtube');

$message   = '';
$action    = $_GET['action'] ?? '';
$edit_key  = $_GET['key'] ?? '';

// --- Hilfsfunktionen ---
function getSetting($key, $default = null) {
    global $_database;
    $key_safe = $_database->real_escape_string($key);
    $result = $_database->query("
        SELECT setting_value 
        FROM plugins_youtube_settings 
        WHERE plugin_name='youtube' AND setting_key='$key_safe' 
        LIMIT 1
    ");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

function setSetting($key, $value) {
    global $_database;
    $key_safe   = $_database->real_escape_string($key);
    $value_safe = $_database->real_escape_string($value);

    $result = $_database->query("
        SELECT id 
        FROM plugins_youtube_settings 
        WHERE plugin_name='youtube' AND setting_key='$key_safe' 
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $_database->query("
            UPDATE plugins_youtube_settings 
            SET setting_value='$value_safe', updated_at=NOW() 
            WHERE plugin_name='youtube' AND setting_key='$key_safe'
        ");
    } else {
        $_database->query("
            INSERT INTO plugins_youtube_settings 
            (plugin_name, setting_key, setting_value, updated_at) 
            VALUES ('youtube', '$key_safe', '$value_safe', NOW())
        ");
    }
}

// --- Aktionen verarbeiten ---

// Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    setSetting('default_video_id', trim($_POST['default_video_id']));
    setSetting('videos_per_page', intval($_POST['videos_per_page']));
    setSetting('videos_per_page_other', intval($_POST['videos_per_page_other']));
    setSetting('display_mode', ($_POST['display_mode'] === 'list') ? 'list' : 'grid');
    setSetting('first_full_width', isset($_POST['first_full_width']) ? 1 : 0);

    header('Location: admincenter.php?site=admin_youtube');
    exit;
}

// Videos löschen
if (isset($_GET['delete'])) {
    $videoId_safe = $_database->real_escape_string($_GET['delete']);
    $stmt = $_database->prepare("
        SELECT setting_key 
        FROM plugins_youtube 
        WHERE plugin_name='youtube' AND setting_value=? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $videoId_safe);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $stmtDel = $_database->prepare("
            DELETE FROM plugins_youtube 
            WHERE plugin_name='youtube' AND setting_key=?
        ");
        $stmtDel->bind_param("s", $row['setting_key']);
        $stmtDel->execute();
        $message = "Video erfolgreich gelöscht!";
    }
}

// Add/Edit Videos
// --- Add/Edit Videos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $video_id = trim($_POST['video_id'] ?? '');
    $set_as_first = isset($_POST['set_as_first']) ? 1 : 0;

    if (!empty($video_id)) {
        if ($action === 'add') {
            $count = $_database->query("
                SELECT COUNT(*) 
                FROM plugins_youtube 
                WHERE plugin_name='youtube'
            ")->fetch_row()[0];
            $newKey = "video_" . ($count + 1);

            // --- Wenn dieses Video als erstes markiert wird, alle anderen zurücksetzen ---
            if ($set_as_first) {
                $_database->query("UPDATE plugins_youtube SET is_first=0 WHERE plugin_name='youtube'");
            }

            $stmt = $_database->prepare("
                INSERT INTO plugins_youtube 
                (plugin_name, setting_key, setting_value, is_first, updated_at) 
                VALUES ('youtube', ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssi", $newKey, $video_id, $set_as_first);
            $stmt->execute();

            $edit_key = $newKey; // Key für Markierung
        }

        if ($action === 'edit' && !empty($_POST['edit_video_key'])) {
            $edit_key = $_POST['edit_video_key'];

            // --- Wenn als erstes markiert, alle anderen zurücksetzen ---
            if ($set_as_first) {
                $_database->query("UPDATE plugins_youtube SET is_first=0 WHERE plugin_name='youtube'");
            }

            $stmt = $_database->prepare("
                UPDATE plugins_youtube 
                SET setting_value=?, is_first=?, updated_at=NOW() 
                WHERE plugin_name='youtube' AND setting_key=?
            ");
            $stmt->bind_param("sis", $video_id, $set_as_first, $edit_key);
            $stmt->execute();
        }

            /////////////////////////////////////////////////////////////////////////////
            // Datei-Name des aktuellen Admin-Moduls ermitteln
            $admin_file = basename(__FILE__, '.php');

            // Aktualisiert das Änderungsdatum in der Navigation für dieses Modul
            // Warum das wichtig ist:
            // ✅ Google liest das Änderungsdatum über die sitemap.xml (Tag <lastmod>)
            // ✅ Wenn sich Inhalte ändern, soll Google das bemerken
            // ✅ Dadurch werden Seiten öfter und gezielter gecrawlt (besseres SEO)
            // ✅ Das Datum bleibt so immer aktuell – automatisch und ohne Pflegeaufwand
            $admin_file = basename(__FILE__, '.php');
            echo NavigationUpdater::updateFromAdminFile($admin_file);
            /////////////////////////////////////////////////////////////////////////////
    }

    // Zurück zur Übersicht
    header('Location: admincenter.php?site=admin_youtube');
    exit;
}


// --- Einstellungen laden ---
$defaultVideoId    = getSetting('default_video_id', 'D_x8ms9nGQw');
$videosPerPage     = getSetting('videos_per_page', 4);
$videosPerPageOther= getSetting('videos_per_page_other', 6);
$displayMode       = getSetting('display_mode', 'grid');
$firstFullWidth    = getSetting('first_full_width', 0);




// --- Add/Edit Formular ---
if (in_array($action, ['add','edit'])) {
    $isEdit = ($action === 'edit');

    $currentVideoId = '';
    $isFirst = 0;

    if ($isEdit && $edit_key) {
        $stmt = $_database->prepare("
            SELECT setting_value, is_first 
            FROM plugins_youtube 
            WHERE plugin_name='youtube' AND setting_key=?
        ");
        $stmt->bind_param("s", $edit_key);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $currentVideoId = $row['setting_value'];
            $isFirst = (int)$row['is_first'];
        }
    }

    $defaultVideoId = getSetting('default_video_id', '');
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> YouTube Videos verwalten</div>
        <div>
            <a href="admincenter.php?site=admin_youtube&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neu</a>
            <a href="admincenter.php?site=admin_youtube&action=settings" class="btn btn-primary btn-sm"><i class="bi bi-tags"></i> Settings</a>
        </div>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_youtube">Youtube verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Video bearbeiten' : 'Neues Video hinzufügen' ?></li>
        </ol>
    </nav>
    <div class="card-body p-0">
        <div class="container py-5">

            <!-- Add/Edit Formular -->
            <form method="POST" class="row g-2 align-items-center">
                <?php if($isEdit): ?>
                    <input type="hidden" name="edit_video_key" value="<?= htmlspecialchars($edit_key); ?>">
                <?php endif; ?>

                <div class="col-md-9">
                    <input type="text" class="form-control" name="video_id" placeholder="Video-ID eingeben"
                           value="<?= htmlspecialchars($currentVideoId); ?>" required>
                </div>

                <div class="col-md-3 d-grid">
                    <button class="btn btn-<?= $isEdit ? 'primary' : 'success'; ?>" type="submit">
                        <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg'; ?>"></i> <?= $isEdit ? 'Speichern' : 'Hinzufügen'; ?>
                    </button>
                </div>

                <!-- Markierung als erstes Video -->
                <div class="col-12 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="set_as_first" id="set_as_first"
                               value="1" <?= ($isFirst) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="set_as_first">
                            Als erstes Video markieren
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
    return; // Stoppt Hauptseite
}




// --- Settings Formular ---
if ($action === 'settings') {
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> YouTube Plugin Einstellungen</div>
        <div>
            <a href="admincenter.php?site=admin_youtube&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neu</a>
            <a href="admincenter.php?site=admin_youtube&action=settings" class="btn btn-primary btn-sm"><i class="bi bi-tags"></i> Settings</a>
        </div>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_youtube">Youtube verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">YouTube Plugin Einstellungen</li>
        </ol>
    </nav>
    <div class="card-body p-0">
        <div class="container py-5">
    <h2>YouTube Plugin Einstellungen</h2>
    <form method="POST">
        <input type="hidden" name="save_settings" value="1">
        <div class="mb-3">
            <label class="form-label">Default Video ID</label>
            <input type="text" class="form-control" name="default_video_id" value="<?php echo htmlspecialchars($defaultVideoId); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Videos pro Seite (erste Seite)</label>
            <input type="number" class="form-control" name="videos_per_page" value="<?php echo htmlspecialchars($videosPerPage); ?>" min="1" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Videos pro Seite (Folge-Seiten)</label>
            <input type="number" class="form-control" name="videos_per_page_other" value="<?php echo htmlspecialchars($videosPerPageOther); ?>" min="1" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Anzeigeart</label>
            <select class="form-select" name="display_mode">
                <option value="grid" <?php echo ($displayMode === 'grid') ? 'selected' : ''; ?>>Nebeneinander (Grid)</option>
                <option value="list" <?php echo ($displayMode === 'list') ? 'selected' : ''; ?>>Untereinander (Liste)</option>
            </select>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="first_full_width" id="first_full_width" <?php echo ($firstFullWidth) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="first_full_width">Erstes Video volle Breite bei Grid</label>
        </div>
        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
    </form>
</div>
<?php
    return; // Stoppt Hauptseite
}

// --- Übersicht (Listing) ---
$videos = [];
$result = $_database->query("
    SELECT setting_key, setting_value, is_first 
    FROM plugins_youtube 
    WHERE plugin_name='youtube' AND setting_key LIKE 'video_%' 
    ORDER BY id DESC
");
if ($result) while ($row = $result->fetch_assoc()) $videos[] = $row;
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> YouTube Videos verwalten</div>
        <div>
            <a href="admincenter.php?site=admin_youtube&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neu</a>
            <a href="admincenter.php?site=admin_youtube&action=settings" class="btn btn-primary btn-sm"><i class="bi bi-tags"></i> Settings</a>
        </div>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_youtube">Youtube verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">ADD / EDIT</li>
        </ol>
    </nav>
    <div class="card-body p-0">
        <div class="container py-5">
            <?php if(!empty($message)): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

<table class="table table-striped">
<thead>
    <tr>
        <th>Vorschau</th>
        <th>Video ID</th>
        <th>Aktionen</th>
    </tr>
</thead>
<tbody>
<?php foreach($videos as $v): ?>
    <tr>
        <td>
            <?php
                $videoId = htmlspecialchars($v['setting_value']);
                $thumbnailUrl = "https://img.youtube.com/vi/$videoId/hqdefault.jpg";
            ?>
            <img src="<?php echo $thumbnailUrl; ?>" width="180" height="78" alt="YouTube Video Thumbnail">
            <?php if($v['is_first']): ?>
                <i class="bi bi-star-fill text-warning ms-2" title="Erstes Video"></i>
            <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($v['setting_value']); ?></td>
        <td>
            <a href="admincenter.php?site=admin_youtube&action=edit&key=<?php echo urlencode($v['setting_key']); ?>" class="btn btn-sm btn-primary">Bearbeiten</a>
            <a href="admincenter.php?site=admin_youtube&delete=<?php echo urlencode($v['setting_value']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Video wirklich löschen?');">Löschen</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>

        </div>
    </div>
</div>
