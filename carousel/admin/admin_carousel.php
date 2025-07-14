<?php
use webspell\LanguageService;

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
$languageService->readPluginModule('carousel');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('carousel');


// Konfiguration
#$upload_dir = "../includes/plugins/carousel/images/";
#$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg'];
#$types = ['sticky', 'parallax', 'agency', 'carousel'];

// Aktionen
$action = $_GET['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;


$plugin_path = 'includes/plugins/carousel/images/';
@mkdir($plugin_path);

$types = ['sticky', 'parallax', 'agency', 'carousel'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'];

// === Speichern ===
// Save block
if (isset($_POST['save_block'])) {
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'];
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $description = $_POST['description'];
    $link = escape($_POST['link']);
    $visible = isset($_POST['visible']) ? 1 : 0;
    $isEdit = $id > 0;
    $filename = '';
    $media_type = '';

    if (isset($_FILES['media_file']) && $_FILES['media_file']['size'] > 0) {
        $mime = mime_content_type($_FILES['media_file']['tmp_name']);
        if (in_array($mime, $allowedMimeTypes)) {
            $ext = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('block_') . '.' . $ext;
            $media_type = str_starts_with($mime, 'video/') ? 'video' : 'image';

            if (move_uploaded_file($_FILES['media_file']['tmp_name'], $plugin_path . $filename)) {
                if ($isEdit) {
                    $result = safe_query("SELECT media_file FROM plugins_carousel WHERE id = " . (int)$id);
                    if (mysqli_num_rows($result)) {
                        $old = mysqli_fetch_assoc($result);
                        if ($old && $old['media_file'] !== $filename && file_exists($plugin_path . $old['media_file'])) {
                            @unlink($plugin_path . $old['media_file']);
                        }
                    }
                }
            } else {
                echo error("Datei-Upload fehlgeschlagen.");
            }
        } else {
            echo error("Ungültiger Medientyp.");
        }
    }

    if ($isEdit) {
        safe_query("UPDATE plugins_carousel SET 
            type = '$type', title = '$title', subtitle = '$subtitle', description = '$description', 
            link = '$link', visible = $visible
            " . ($filename ? ", media_file = '$filename', media_type = '$media_type'" : '') . "
            WHERE id = $id");
    } else {
        safe_query("INSERT INTO plugins_carousel 
            (type, title, subtitle, description, link, visible, media_type, media_file)
            VALUES ('$type', '$title', '$subtitle', '$description', '$link', $visible, '$media_type', '$filename')");
    }

    redirect('admincenter.php?site=admin_carousel', '', 0);
    exit;
}


// === Löschen ===
if (isset($_GET['delete'])) {
    $delID = (int)$_GET['delete'];
    $block = mysqli_fetch_assoc(safe_query("SELECT * FROM plugins_carousel WHERE id = $delID"));

    if ($block) {
        // Bilddatei löschen, wenn vorhanden
        $imageFilename = $block['media_file'] ?? '';
        if (!empty($imageFilename) && file_exists($plugin_path . $imageFilename)) {
            @unlink($plugin_path . $imageFilename);
        }

        // Datenbankeintrag löschen
        safe_query("DELETE FROM plugins_carousel WHERE id = $delID");
    }

    header("Location: admincenter.php?site=admin_carousel");
    exit;
}



// === Formular: add/edit ===
if ($action === 'add' || $action === 'edit') {
    $edit = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $edit = mysqli_fetch_assoc(safe_query("SELECT * FROM plugins_carousel WHERE id = " . (int)$_GET['id']));
    }

    ?>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-journal-text"></i> Bild / Video <?= $action ? "bearbeiten" : "hinzufügen" ?>
        </div>
        <nav class="breadcrumb bg-light p-2">
            <a class="breadcrumb-item" href="admincenter.php?site=admin_carousel">Bild / Video verwalten</a>
            <span class="breadcrumb-item active"><?= $action ? "Bearbeiten" : "Hinzufügen" ?></span>
        </nav>
        <div class="card-body">
            <div class="container py-5">

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

        <div class="mb-3">
            <label class="form-label">Typ</label>
            <select name="type" class="form-select" required>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= ($edit['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Titel</label>
            <input class="form-control" name="title" value="<?= htmlspecialchars($edit['title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Untertitel</label>
            <input class="form-control" name="subtitle" value="<?= htmlspecialchars($edit['subtitle'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea class="form-control" name="description"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Link</label>
            <input class="form-control" name="link" value="<?= htmlspecialchars($edit['link'] ?? '') ?>">
        </div>

        <div class="mb-3">
        <label class="form-label">Datei (Bild oder Video)</label>
        <input type="file" name="media_file" class="form-control">

        <?php
        if (!empty($edit['media_file'])) {
            $plugin_url = '../includes/plugins/carousel/images/'; // ggf. anpassen
            $mediaPath = $plugin_url . $edit['media_file'];
            $extension = strtolower(pathinfo($edit['media_file'], PATHINFO_EXTENSION));

            echo '<div class="mt-2">';
            echo '<label class="form-label">Aktuelle Datei-Vorschau:</label><br>';

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                echo '<img src="' . htmlspecialchars($mediaPath) . '" alt="Vorschau" class="img-thumbnail" style="max-width: 300px;">';
            } elseif (in_array($extension, ['mp4', 'webm', 'ogg'])) {
                echo '<video controls class="img-thumbnail" style="max-width: 300px;">';
                echo '<source src="' . htmlspecialchars($mediaPath) . '" type="video/' . $extension . '">';
                echo 'Video kann nicht geladen werden.';
                echo '</video>';
            } else {
                echo '<div class="alert alert-warning">Unbekannter Dateityp: ' . htmlspecialchars($edit['media_file']) . '</div>';
            }

            echo '</div>';
        }
        ?>
    </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="visible" value="1" <?= ($edit['visible'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label">Sichtbar</label>
        </div>

        <button class="btn btn-primary" name="save_block">Speichern</button>
        <a href="admincenter.php?site=admin_carousel" class="btn btn-secondary">Zurück</a>
    </form>
            </div>
        </div>
    </div>


    <?php

} elseif ($action == "settings") {

    // Speichern?
    if (isset($_POST['save_settings'])) {
        $carousel_height = escape($_POST['carousel_height']);
        $parallax_height = escape($_POST['parallax_height']);
        $sticky_height = escape($_POST['sticky_height']);
        $agency_height = escape($_POST['agency_height']);

        safe_query("UPDATE plugins_carousel_settings SET 
            carousel_height = '$carousel_height',
            parallax_height = '$parallax_height',
            sticky_height = '$sticky_height',
            agency_height = '$agency_height'
        WHERE carouselID = 1");

        echo '<div class="alert alert-success">Einstellungen gespeichert.</div>';
    }

    // Aktuelle Einstellungen laden
    $settings = mysqli_fetch_assoc(safe_query("SELECT * FROM plugins_carousel_settings WHERE carouselID = 1"));
    ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-sliders"></i> Carousel-Höhen Einstellungen</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Carousel-Höhe (z. B. 75vh oder 600px)</label>
                    <input class="form-control" name="carousel_height" value="<?= htmlspecialchars($settings['carousel_height']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Parallax-Höhe</label>
                    <input class="form-control" name="parallax_height" value="<?= htmlspecialchars($settings['parallax_height']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Sticky-Höhe</label>
                    <input class="form-control" name="sticky_height" value="<?= htmlspecialchars($settings['sticky_height']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Agency-Höhe</label>
                    <input class="form-control" name="agency_height" value="<?= htmlspecialchars($settings['agency_height']) ?>">
                </div>

                <button type="submit" name="save_settings" class="btn btn-primary">Speichern</button>
                <a href="admincenter.php?site=admin_carousel" class="btn btn-secondary">Zurück</a>
            </form>
        </div>
    </div>

    <?php
}

// === Listenansicht ===
else {
    $result = safe_query("SELECT * FROM plugins_carousel ORDER BY sort ASC, created_at DESC");
    ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> Bild / Video verwalten</div>
            <div>
                <a href="admincenter.php?site=admin_carousel&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neu</a>
                <a href="admincenter.php?site=admin_carousel&action=settings" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Setting</a>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=admin_carousel">Bild / Video verwalten</a></li>
                <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
            </ol>
        </nav> 
        <div class="card-body p-0">
            <div class="container py-5">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                <tr>
                    <th>Vorschau</th>
                    <th>Typ</th>
                    <th>Titel</th>                    
                    <th>Sichtbar</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($row = mysqli_fetch_assoc($result)) {
                    $mediaPath = $plugin_path . $row['media_file'];
                    $fileUrl = htmlspecialchars('../includes/plugins/carousel/images/' . $row['media_file']);
                    $extension = strtolower(pathinfo($row['media_file'], PATHINFO_EXTENSION));
                    ?>
                    <tr><td>
                            <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                <img src="<?= $fileUrl ?>" class="img-thumbnail" style="max-width: 100px;">
                            <?php elseif (in_array($extension, ['mp4', 'webm', 'ogg'])): ?>
                                <video style="max-width: 100px;" muted>
                                    <source src="<?= $fileUrl ?>" type="video/<?= $extension ?>">
                                    Video nicht verfügbar
                                </video>
                            <?php else: ?>
                                <span class="text-muted">Unbekannter Dateityp</span>
                            <?php endif; ?>
                        </td>
                        <td><?= ucfirst($row['type']) ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        
                        <td>
                            <?php if ($row['visible']): ?>
                                <span class="badge bg-success">Ja</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nein</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="admincenter.php?site=admin_carousel&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="admincenter.php?site=admin_carousel&delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php
                }
                if (mysqli_num_rows($result) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center">Keine Einträge vorhanden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php
}
