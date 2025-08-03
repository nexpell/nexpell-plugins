<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('gallery');

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('gallery');

// Parameter aus URL lesen
$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$filterClass = $_GET['filter_class'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'upload_date';
$sortDir = ($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Max Bilder pro Seite
$perPage = 10;

// Mögliche Sortierfelder (Whitelist)
$allowedSorts = ['filename', 'upload_date'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'upload_date';
}

// Upload Verzeichnis
#$uploadDir = __DIR__ . '/../images/';



// Rechtecheck hier (z.B. isAdmin())

// --- AJAX Löschung ---
/*if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Bildname ermitteln
    $stmt = $_database->prepare("SELECT filename FROM plugins_gallery WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($filename);
    if ($stmt->fetch()) {
        $stmt->close();
        // Bild aus DB löschen
        $stmtDel = $_database->prepare("DELETE FROM plugins_gallery WHERE id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();
        $stmtDel->close();
        // Datei löschen
        @unlink($uploadDir . $filename);
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Bild nicht gefunden']);
    }
    exit;
}*/

if (isset($_GET['delete'], $_GET['file'])) {
    $id = intval($_GET['delete']);
    $filename = basename($_GET['file']); // gegen Pfadangriffe absichern

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/gallery/images/upload/';
    $thumbDir = $uploadDir . 'thumbs/';
    $origDir = $uploadDir . 'originals/';

    // Dateien löschen
    @unlink($uploadDir . $filename);
    @unlink($thumbDir . $filename);

    // Dateiendung & Name extrahieren
    $fileBase = pathinfo($filename, PATHINFO_FILENAME); // ohne .jpg
    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);

    $originalName = $fileBase . '_orig.' . $fileExt;
    @unlink($origDir . $originalName);

    // DB-Eintrag löschen – korrekt vorbereitet
    $stmt = $_database->prepare("DELETE FROM plugins_gallery WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Weiterleitung
    header("Location: admincenter.php?site=admin_gallery");
    exit;
}



if ($action === "add_cat" || $action === "edit_cat") {
    $isEdit_cat = ($action === "edit_cat");
    $category = ['id' => 0, 'name' => ''];

    if ($isEdit_cat && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $catID = (int)$_GET['id'];
        $result = $_database->prepare("SELECT * FROM plugins_gallery_categories WHERE id = ?");
        $result->execute([$catID]);
        $category = $result->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            echo "<div class='alert alert-danger'>Kategorie nicht gefunden.</div>";
            exit;
        }
    }

    // Formularverarbeitung
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            echo "<div class='alert alert-warning'>Bitte gib einen Kategorienamen ein.</div>";
        } else {
            if ($isEdit_cat) {
                $stmt = $_database->prepare("UPDATE plugins_gallery_categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $category['id']]);
                echo "<div class='alert alert-success'>Kategorie wurde aktualisiert.</div>";
            } else {
                $stmt = $_database->prepare("INSERT INTO plugins_gallery_categories (name) VALUES (?)");
                $stmt->execute([$name]);
                echo "<div class='alert alert-success'>Kategorie wurde hinzugefügt.</div>";
            }

            echo '<a href="admincenter.php?site=admin_gallery" class="btn btn-primary mt-3">Zurück zur Übersicht</a>';
            exit;
        }
    }
?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-folder-plus"></i> Galerie Kategorie <?= $isEdit_cat ? 'bearbeiten' : 'hinzufügen' ?>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_gallery">Galerie verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit_cat ? 'Kategorie bearbeiten' : 'Kategorie hinzufügen' ?></li>
        </ol>
    </nav>

    <div class="card-body">
        <form method="post" action="">
            <div class="mb-3">
                <label for="name" class="form-label">Kategoriename</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($category['name']) ?>" required>
            </div>

            <button type="submit" class="btn btn-success">
                <?= $isEdit_cat ? 'Speichern' : 'Kategorie hinzufügen' ?>
            </button>
            <a href="admincenter.php?site=admin_gallery" class="btn btn-secondary">Abbrechen</a>
        </form>
    </div>
</div>
<?php
    exit;
}



$settings_result = safe_query("SELECT * FROM `settings`");
$settings = mysqli_fetch_assoc($settings_result);
$hp_title = $settings['hptitle'] ?? 'nexpell';

function createWatermarkOnImage($src, $dest, $watermarkText = '© DeinName') {
    $info = getimagesize($src);
    if (!$info) return false;

    list($width, $height) = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($src); break;
        case 'image/png':  $image = imagecreatefrompng($src); break;
        case 'image/webp': $image = imagecreatefromwebp($src); break;
        default: return false;
    }

    // Wasserzeichen hinzufügen
    $fontPath = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/gallery/images/fonts/OpenSans-Regular.ttf';
    if (file_exists($fontPath) && function_exists('imagettftext')) {
        $fontSize = 12;
        $padding = 10;
        $textColor = imagecolorallocatealpha($image, 255, 255, 255, 40); // Weiß, halbtransparent
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $watermarkText);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];

        $x = $width - $textWidth - $padding;
        $y = $height - $padding - (-$bbox[7]); // Korrigiert Position Y

        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $watermarkText);
    }

    // Bild speichern
    switch ($mime) {
        case 'image/jpeg': imagejpeg($image, $dest, 85); break;
        case 'image/png':  imagepng($image, $dest); break;
        case 'image/webp': imagewebp($image, $dest); break;
    }

    imagedestroy($image);
    return true;
}

function createThumbnail($src, $dest, $maxWidth, $maxHeight) {
    $info = getimagesize($src);
    if (!$info) return false;

    list($width, $height) = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($src); break;
        case 'image/png':  $image = imagecreatefrompng($src); break;
        case 'image/webp': $image = imagecreatefromwebp($src); break;
        default: return false;
    }

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    // Für PNG/WebP Transparenz erhalten
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    switch ($mime) {
        case 'image/jpeg': imagejpeg($thumb, $dest, 85); break;
        case 'image/png':  imagepng($thumb, $dest); break;
        case 'image/webp': imagewebp($thumb, $dest); break;
    }

    imagedestroy($image);
    imagedestroy($thumb);
    return true;
}






if ($action === "add" || $action === "edit") {

$id = intval($_GET['id'] ?? 0);
$isEdit = $id > 0;
$data = ['filename' => '', 'class' => '', 'category_id' => 0];

// Kategorien laden
$categories = [];
$res = $_database->query("SELECT id, name FROM plugins_gallery_categories ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// Daten bei Bearbeitung laden
if ($isEdit) {
    $stmt = $_database->prepare("SELECT filename, class, category_id FROM plugins_gallery WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($filenameDB, $classDB, $catIDDB);
    if ($stmt->fetch()) {
        $data = ['filename' => $filenameDB, 'class' => $classDB, 'category_id' => $catIDDB];
    } else {
        echo "<div class='alert alert-danger'>Bild nicht gefunden.</div>";
        exit;
    }
    $stmt->close();
}

// Fehler- und Uploadstatus
$error = '';
$fileUploaded = false;
$filename = '';



// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = $_POST['class'] ?? '';
    $category_id = intval($_POST['category_id'] ?? 0);

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/gallery/images/upload/';

// Initial Werte
$fileUploaded = false;
$filename = '';
$originalPath = '';
$thumbDir = $uploadDir . 'thumbs/';
$thumbPath = ''; 
$originalsDir = $uploadDir . 'originals/';
$originalPath = $uploadDir . $filename; // Das Ziel für move_uploaded_file()   

    // Upload-Verarbeitung
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $originalName = basename($_FILES['image']['name']);
    $fileExt = pathinfo($originalName, PATHINFO_EXTENSION);
    $fileBase = pathinfo($originalName, PATHINFO_FILENAME);
    #$fileBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileBase); // Sonderzeichen entfernen

    $uniqueId = uniqid('img_'); // EINMALIG erzeugen
$fileExt = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);

// Dateinamen auf Basis der EINEN ID
$filenameWatermarked = $uniqueId . '.' . $fileExt;       // z. B. img_abcd1234.jpg
$filenameOrig = $uniqueId . '_orig.' . $fileExt;         // z. B. img_abcd1234_orig.jpg
$thumbFilename = $filenameWatermarked;                   // gleich wie das Wasserzeichen

// Pfade
$originalPath = $uploadDir . 'originals/' . $filenameOrig;
$watermarkedPath = $uploadDir . $filenameWatermarked;
$thumbPath = $thumbDir . $thumbFilename;

    echo "Original-Pfad: $originalPath<br>";
    echo "Tmp-Datei: " . $_FILES['image']['tmp_name'] . "<br>";

    if (!is_dir($originalsDir)) {
        mkdir($originalsDir, 0775, true);
    }

    // Vorab prüfen, ob Ordner existiert
    if (!is_dir($uploadDir)) {
        echo "Fehler: Upload-Ordner existiert nicht: $uploadDir<br>";
    } elseif (!is_writable($uploadDir)) {
        echo "Fehler: Upload-Ordner ist nicht beschreibbar: $uploadDir<br>";
    }

    if (move_uploaded_file($_FILES['image']['tmp_name'], $originalPath)) {
        echo "Originalbild gespeichert: $filenameOrig<br>";
        $fileUploaded = true;

        if (function_exists('createWatermarkOnImage')) {
            createWatermarkOnImage($originalPath, $watermarkedPath, '© ' . $hp_title);
            echo "Wasserzeichen erstellt: $filenameWatermarked<br>";
        } else {
            copy($originalPath, $watermarkedPath);
            echo "Original kopiert (kein Wasserzeichen): $filenameWatermarked<br>";
        }

        if (function_exists('createThumbnail')) {
            createThumbnail($watermarkedPath, $thumbPath, 300, 300);
            echo "Thumbnail erstellt: $thumbFilename<br>";
        }

        $filename = $filenameWatermarked;
    } else {
        echo "❌ Fehler beim Speichern von: $originalPath<br>";
        echo "Upload-Fehlercode: " . $_FILES['image']['error'] . "<br>";
    }
} else {
    echo '❌ Keine Datei hochgeladen oder Fehler beim Upload.<br>';
    echo 'Upload-Fehlercode: ' . ($_FILES['image']['error'] ?? 'nicht gesetzt') . '<br>';
}




if (empty($error)) {
    if ($isEdit) {
        if ($fileUploaded) {
            // Altes Bild + Thumbnail + Original löschen
            if (!empty($data['filename'])) {
                @unlink($uploadDir . $data['filename']);
                @unlink($uploadDir . 'thumbs/' . $data['filename']);
                $oldOriginal = $uploadDir . pathinfo($data['filename'], PATHINFO_FILENAME) . '_orig.' . pathinfo($data['filename'], PATHINFO_EXTENSION);
                @unlink($oldOriginal);
            }

            $stmt = $_database->prepare("UPDATE plugins_gallery SET filename = ?, class = ?, category_id = ? WHERE id = ?");
            $stmt->bind_param("ssii", $filename, $class, $category_id, $id);
        } else {
            $stmt = $_database->prepare("UPDATE plugins_gallery SET class = ?, category_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $class, $category_id, $id);
        }
    } else {
        if ($fileUploaded) {
            $stmt = $_database->prepare("INSERT INTO plugins_gallery (filename, class, category_id, upload_date) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $filename, $class, $category_id);
        } else {
            $error = 'Bitte wählen Sie eine Bilddatei aus.';
        }
    }

    if (empty($error) && isset($stmt)) {
        $stmt->execute();
        $stmt->close();
        header("Location: admincenter.php?site=admin_gallery");
        exit;
    }
}


}






?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-image"></i> Galerie verwalten
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_gallery">Galerie verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Bild bearbeiten' : 'Bild hinzufügen' ?></li>
        </ol>
    </nav>

    <div class="card-body">
        <div class="container py-5">
            <h4><?= $isEdit ? 'Bild bearbeiten' : 'Bild hinzufügen' ?></h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="category_id" class="form-label">Kategorie:</label>
                    <select class="form-select" name="category_id" id="category_id" required>
                        <option value="">Bitte wählen</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $data['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isEdit): ?>
                    <p><strong>Aktuelles Bild:</strong><br>
                        <img src="/includes/plugins/gallery/images/upload/<?= htmlspecialchars($data['filename']) ?>" class="img-thumbnail" width="200" loading="lazy" alt="aktuelles Bild">
                    </p>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="image" class="form-label">Bild-Datei (JPG, PNG, WebP):</label>
                    <input class="form-control" type="file" name="image" id="image" <?= $isEdit ? '' : 'required' ?>>
                </div>

                    <?php
                        $filename = htmlspecialchars($data['filename']);
                        $fileBase = pathinfo($filename, PATHINFO_FILENAME);         // img_abc123
                        $fileExt  = pathinfo($filename, PATHINFO_EXTENSION);        // jpg
                        $filenameOrig = $fileBase . '_orig.' . $fileExt;            // img_abc123_orig.jpg

                        $urlBase = '/includes/plugins/gallery/images/upload/';
                    ?>

                    <ul class="list-unstyled small">
                        <li>🖼️ Wasserzeichen: <a href="<?= $urlBase . $filename ?>" target="_blank"><?= $urlBase . $filename ?></a></li>
                        <li>📷 Originalbild: <a href="<?= $urlBase . 'originals/' . $filenameOrig ?>" target="_blank"><?= $urlBase . 'originals/' . $filenameOrig ?></a></li>
                        <li>🔎 Thumbnail: <a href="<?= $urlBase . 'thumbs/' . $filename ?>" target="_blank"><?= $urlBase . 'thumbs/' . $filename ?></a></li>
                    </ul>


                <div class="mb-3">
                    <label for="class" class="form-label">Layout-Klasse:</label>
                    <select class="form-select" name="class" id="class">
                        <option value="" <?= $data['class'] === '' ? 'selected' : '' ?>>Standard</option>
                        <option value="wide" <?= $data['class'] === 'wide' ? 'selected' : '' ?>>Wide</option>
                        <option value="tall" <?= $data['class'] === 'tall' ? 'selected' : '' ?>>Tall</option>
                        <option value="big" <?= $data['class'] === 'big' ? 'selected' : '' ?>>Big</option>
                    </select>
                </div>

                
                <button type="submit" class="btn btn-success"><?= $isEdit ? 'Speichern' : 'Hinzufügen' ?></button>
                <a href="admincenter.php?site=admin_gallery" class="btn btn-secondary">Abbrechen</a>
            </form>
        </div>
    </div>
</div>

<?php
    exit;
}



if ($action === "sort") {

########################
$columns = 4;
$rowsPerPage = 3;
$imagesPerPage = $columns * $rowsPerPage;

$isPartial = isset($_GET['partial']) && $_GET['partial'] == '1';

// Bilder holen
$result = $_database->query("SELECT id, filename, class FROM plugins_gallery ORDER BY position ASC, id ASC");
$allImages = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $allImages[] = $row;
    }
}
$pages = array_chunk($allImages, $imagesPerPage);
?>

<?php if (!$isPartial): ?>
<link rel="stylesheet" href="/includes/plugins/gallery/admin/css/admin_gallery.css" />
<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> Galerie verwalten
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_gallery">Galerie verwalten</a></li>
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_gallery&action=sort">Galerie: Bilder sortieren</a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>

    <div class="card-body">
<?php endif; ?>

        <div class="container-fluid py-5" id="sortable-gallery">
            <?php foreach ($pages as $index => $pageImages): ?>
                <div class="card-site">
                    <h2>Seite <?= $index + 1 ?></h2>
                    <div class="grid-wrapper">
                        <?php foreach ($pageImages as $image): ?>
                            <div class="sortable-item <?= htmlspecialchars($image['class']) ?>" data-id="<?= (int)$image['id'] ?>">
                                <img src="/includes/plugins/gallery/images/upload/<?= htmlspecialchars($image['filename']) ?>" alt="">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

<?php if (!$isPartial): ?>
        <button id="save-order" disabled class="btn btn-primary mt-3">Reihenfolge speichern</button>
    </div> 
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="/includes/plugins/gallery/admin/js/gallery.js"></script>
<?php endif; ?>


<?php } else {


$filterClass = $_GET['filter_class'] ?? '';
$filterCat = $_GET['filter_cat'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'upload_date';
$sortDir = $_GET['sort_dir'] ?? 'desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$where = '';
$params = [];
$types = '';

if ($filterClass && in_array($filterClass, ['', 'wide', 'tall', 'big']) === false) {
    $filterClass = '';
}
if ($filterClass) {
    $where = " WHERE class = ?";
    $params[] = $filterClass;
    $types .= 's';
}

if (is_numeric($filterCat)) {
    $where .= ($where ? ' AND' : ' WHERE') . " category_id = ?";
    $params[] = (int)$filterCat;
    $types .= 'i';
}

// Sortierung absichern
$allowedSortBy = ['upload_date', 'filename', 'category_id'];
if (!in_array($sortBy, $allowedSortBy)) {
    $sortBy = 'upload_date';
}
$sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

// Kategorien abrufen
$categories = [];
$catStmt = $_database->query("SELECT id, name FROM plugins_gallery_categories ORDER BY name ASC");
while ($row = $catStmt->fetch_assoc()) {
    $categories[$row['id']] = $row['name'];
}

// Zähle Gesamtanzahl
$countSql = "SELECT COUNT(*) FROM plugins_gallery" . $where;
$stmtCount = $_database->prepare($countSql);
if ($params) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$stmtCount->bind_result($totalItems);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = ceil($totalItems / $perPage);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// Daten abrufen
$dataSql = "SELECT id, filename, class, upload_date, category_id FROM plugins_gallery" . $where . " ORDER BY $sortBy $sortDir LIMIT ? OFFSET ?";
$stmtData = $_database->prepare($dataSql);

if ($params) {
    $types2 = $types . 'ii';
    $params2 = array_merge($params, [$perPage, $offset]);
    $stmtData->bind_param($types2, ...$params2);
} else {
    $stmtData->bind_param("ii", $perPage, $offset);
}

$stmtData->execute();
$result = $stmtData->get_result();
$images = $result->fetch_all(MYSQLI_ASSOC);
$stmtData->close();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-paragraph"></i> Galerie verwalten</div>
        <div>
            <a href="admincenter.php?site=admin_gallery&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neues Bild</a>
            <a href="admincenter.php?site=admin_gallery&action=add_cat" class="btn btn-success btn-sm"><i class="bi bi-plus-circle"></i> Neue Kategorie</a>
            <a href="admincenter.php?site=admin_gallery&action=sort" class="btn btn-primary btn-sm"><i class="bi bi-tags"></i> Seite sortieren</a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_gallery">Galerie verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">Übersicht</li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">

            <form method="get" action="admincenter.php" class="d-flex align-items-center mb-3" style="gap:10px;">
                <input type="hidden" name="site" value="admin_gallery" />
                <label for="filter_class" class="form-label mb-0">Klasse:</label>
                <select name="filter_class" id="filter_class" class="form-select form-select-sm">
                    <option value="" <?= $filterClass === '' ? 'selected' : '' ?>>Alle</option>
                    <option value="wide" <?= $filterClass === 'wide' ? 'selected' : '' ?>>Wide</option>
                    <option value="tall" <?= $filterClass === 'tall' ? 'selected' : '' ?>>Tall</option>
                    <option value="big" <?= $filterClass === 'big' ? 'selected' : '' ?>>Big</option>
                </select>

                <label for="filter_cat" class="form-label mb-0 ms-3">Kategorie:</label>
                <select name="filter_cat" id="filter_cat" class="form-select form-select-sm">
                    <option value="" <?= $filterCat === '' ? 'selected' : '' ?>>Alle</option>
                    <?php foreach ($categories as $catID => $title): ?>
                        <option value="<?= $catID ?>" <?= $filterCat == $catID ? 'selected' : '' ?>>
                            <?= htmlspecialchars($title) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="sort_by" class="form-label mb-0 ms-3">Sortieren nach:</label>
                <select name="sort_by" id="sort_by" class="form-select form-select-sm">
                    <option value="upload_date" <?= $sortBy === 'upload_date' ? 'selected' : '' ?>>Upload Datum</option>
                    <option value="filename" <?= $sortBy === 'filename' ? 'selected' : '' ?>>Dateiname</option>
                    <option value="category_id" <?= $sortBy === 'category_id' ? 'selected' : '' ?>>Kategorie</option>
                </select>

                <select name="sort_dir" id="sort_dir" class="form-select form-select-sm ms-1">
                    <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Aufsteigend</option>
                    <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Absteigend</option>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm ms-3">Anwenden</button>
            </form>

            <table class="table table-bordered table-striped bg-white shadow-sm">
                <thead class="table-light">
                <tr>
                    <th>Vorschau</th>
                    <th>Dateiname</th>
                    <th>Kategorie</th>
                    <th>Layout-Klasse</th>
                    <th>Upload Datum</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($images) === 0): ?>
                    <tr><td colspan="6" class="text-center">Keine Bilder gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($images as $img): ?>
                        <tr>
                            <td><img src="/includes/plugins/gallery/images/upload/<?= htmlspecialchars($img['filename']) ?>" width="100" class="img-thumbnail" loading="lazy" alt="Vorschau"></td>
                            <td><?= htmlspecialchars($img['filename']) ?></td>
                            <td><?= htmlspecialchars($categories[$img['category_id']] ?? '—') ?></td>
                            <td><?= htmlspecialchars($img['class']) ?></td>
                            <td><?= htmlspecialchars($img['upload_date']) ?></td>
                            <td>
                                <a href="admincenter.php?site=admin_gallery&action=edit&id=<?= $img['id'] ?>" class="btn btn-sm btn-warning">Bearbeiten</a>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $img['id'] ?>, '<?= htmlspecialchars(addslashes($img['filename'])) ?>')">Löschen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="admincenter.php?site=admin_gallery&page=<?= $p ?>&filter_class=<?= urlencode($filterClass) ?>&filter_cat=<?= urlencode($filterCat) ?>&sort_by=<?= urlencode($sortBy) ?>&sort_dir=<?= urlencode($sortDir) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php }
?>
<script>
function confirmDelete(id, filename) {
    if (confirm('Bist du sicher, dass du dieses Bild löschen möchtest?')) {
        window.location.href = `admincenter.php?site=admin_gallery&delete=${id}&file=${encodeURIComponent(filename)}`;
    }
}
</script>