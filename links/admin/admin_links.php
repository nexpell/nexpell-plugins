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
$languageService->readPluginModule('links');

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('links');






function get_og_image($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return null;

    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
        return $matches[1];
    }
    if (preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
        return $matches[1];
    }
    $image_url = $matches[1];
return absolutize_url($url, $image_url);
}

function absolutize_url($base, $rel) {
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
    if ($rel[0] == '/') {
        $parts = parse_url($base);
        return $parts['scheme'] . '://' . $parts['host'] . $rel;
    }
    return rtrim($base, '/') . '/' . ltrim($rel, '/');
}

function save_image_locally($image_url, $save_dir = 'includes/plugins/links/images/') {
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $image_data = @file_get_contents($image_url);
    if (!$image_data) return null;

    if (!is_dir($save_dir)) {
        mkdir($save_dir, 0755, true);
    }

    $ext = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), ['jpg','jpeg','png','gif'])) {
        $ext = 'jpg';
    }

    $filename = uniqid('linkimg_') . '.' . $ext;
    $filepath = $save_dir . $filename;

    file_put_contents($filepath, $image_data);

    return $filepath;
}

// Einfaches Routing: action aus GET/POST
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

// Link-Daten (für edit)
$id = $_GET['id'] ?? null;

// Erfolgsmeldung
$msg = '';

// Kategorien laden für Auswahl
$categories = [];
$res = $_database->query("SELECT id, title FROM plugins_links_categories ORDER BY title");
while ($row = $res->fetch_assoc()) {
    $categories[$row['id']] = $row['title'];
}

// === Aktionen ===

if ($action === 'delete' && $id) {
    $stmt = $_database->prepare("DELETE FROM plugins_links WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $msg = "Link gelöscht.";
    }
    header("Location: admincenter.php?site=admin_links&msg=" . urlencode($msg));
    exit;
}

if (in_array($action, ['add', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $target = $_POST['target'] ?? '_blank';
    $visible = isset($_POST['visible']) ? 1 : 0;

    // Bild-Upload prüfen
    $imagePath = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['image']['type'], $allowed)) {
            $saveDir = 'includes/plugins/links/images/';
            if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('upload_') . '.' . $ext;
            $destination = $saveDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $imagePath = $destination;
            }
        }
    }

    // Wenn kein Upload-Bild, OG-Image holen
    if (!$imagePath) {
        $ogImageUrl = get_og_image($url);
        if ($ogImageUrl) {
            $localImage = save_image_locally($ogImageUrl, 'includes/plugins/links/images/');
            if ($localImage) {
                $imagePath = $localImage;
            }
        }
    }

    // Fallback Standardbild
    if (!$imagePath) {
        $imagePath = 'includes/plugins/links/images/default_thumb.jpg';
    }

    if ($action === 'add') {
        $stmt = $_database->prepare("INSERT INTO plugins_links (title, url, description, category_id, image, target, visible) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssissi", $title, $url, $description, $category_id, $imagePath, $target, $visible);
        if ($stmt->execute()) {
            $msg = "Link hinzugefügt.";
            header("Location: admincenter.php?site=admin_links&msg=" . urlencode($msg));
            exit;
        }
    } elseif ($action === 'edit' && $id) {
        // Wenn Bild nicht neu hochgeladen, alten Pfad behalten
        if (!$imagePath) {
            $stmt2 = $_database->prepare("SELECT image FROM plugins_links WHERE id = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $stmt2->bind_result($oldImage);
            $stmt2->fetch();
            $stmt2->close();
            $imagePath = $oldImage ?: 'includes/plugins/links/images/default_thumb.jpg';
        }
        $stmt = $_database->prepare("UPDATE plugins_links SET title=?, url=?, description=?, category_id=?, image=?, target=?, visible=? WHERE id=?");
        $stmt->bind_param("sssissii", $title, $url, $description, $category_id, $imagePath, $target, $visible, $id);
        if ($stmt->execute()) {
            $msg = "Link aktualisiert.";
            header("Location: admincenter.php?site=admin_links&msg=" . urlencode($msg));
            exit;
        }
    }
}

// === Anzeigeformular für add/edit ===
if (in_array($action, ['add', 'edit'])) {
    $link = [
        'title' => '',
        'url' => '',
        'description' => '',
        'category_id' => '',
        'image' => '',
        'target' => '_blank',
        'visible' => 1,
    ];

    if ($action === 'edit' && $id) {
        $stmt = $_database->prepare("SELECT * FROM plugins_links WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $link = $result->fetch_assoc();
    }
    ?>

    
   <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> <?= $languageService->get('manage_links') ?></div>
        <div>
            <a href="admincenter.php?site=admin_links&action=add" class="btn btn-success btn-sm">
                <i class="bi bi-plus"></i> <?= $languageService->get('new_link') ?>
            </a>
            <a href="admincenter.php?site=admin_links_settings" class="btn btn-primary btn-sm">
                <i class="bi bi-tags"></i> <?= $languageService->get('links_settings') ?>
            </a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_links"><?= $languageService->get('manage_links') ?></a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <?= ($action === 'add' ? $languageService->get('add_link') : $languageService->get('edit_link')) ?>
            </li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>" />
                <div class="mb-3">
                    <label for="title" class="form-label"><?= $languageService->get('title') ?></label>
                    <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($link['title']) ?>">
                </div>
                <div class="mb-3">
                    <label for="url" class="form-label"><?= $languageService->get('url') ?></label>
                    <input type="url" class="form-control" id="url" name="url" required value="<?= htmlspecialchars($link['url']) ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label"><?= $languageService->get('description') ?></label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($link['description']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="category_id" class="form-label"><?= $languageService->get('category') ?></label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value=""><?= $languageService->get('please_select') ?></option>
                        <?php foreach ($categories as $catId => $catTitle): ?>
                            <option value="<?= $catId ?>" <?= ($catId == $link['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($catTitle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label"><?= $languageService->get('og_image_preview') ?></label>
                    <div id="og-preview" class="mb-2">
                        <p class="text-muted"><?= $languageService->get('no_url_entered') ?></p>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label"><?= $languageService->get('image_optional') ?></label>
                    <input class="form-control" type="file" id="image" name="image" accept="image/*" />
                </div>

                <div class="mb-3">
                    <label for="target" class="form-label"><?= $languageService->get('link_target') ?></label>
                    <select id="target" name="target" class="form-select">
                        <option value="_blank" <?= $link['target'] === '_blank' ? 'selected' : '' ?>>
                            <?= $languageService->get('new_window') ?>
                        </option>
                        <option value="_self" <?= $link['target'] === '_self' ? 'selected' : '' ?>>
                            <?= $languageService->get('same_window') ?>
                        </option>
                    </select>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" id="visible" name="visible" class="form-check-input" <?= $link['visible'] ? 'checked' : '' ?>>
                    <label for="visible" class="form-check-label"><?= $languageService->get('visible') ?></label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?= ($action === 'add' ? $languageService->get('add') : $languageService->get('update')) ?>
                </button>
                <a href="admin_links.php" class="btn btn-secondary"><?= $languageService->get('cancel') ?></a>
            </form>
        </div>
    </div>
</div>

    <?php

    
}
else {

$msg = '';
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Query: Links mit Kategorie und Klickanzahl laden
$res = $_database->query("
    SELECT l.*, c.title AS category,
        COALESCE(k.click_count, 0) AS clicks
    FROM plugins_links l
    LEFT JOIN plugins_links_categories c ON l.category_id = c.id
    LEFT JOIN (
        SELECT itemID, COUNT(*) AS click_count
        FROM link_clicks
        WHERE plugin = 'links'
        GROUP BY itemID
    ) k ON l.id = k.itemID
    ORDER BY c.title, l.title
");

?>
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
    <div><i class="bi bi-journal-text"></i> <?= $languageService->get('manage_links') ?></div>
    <div>
        <a href="admincenter.php?site=admin_links&action=add" class="btn btn-success btn-sm">
            <i class="bi bi-plus"></i> <?= $languageService->get('new_link') ?>
        </a>
        <a href="admincenter.php?site=admin_links_settings" class="btn btn-primary btn-sm">
            <i class="bi bi-tags"></i> <?= $languageService->get('links_settings') ?>
        </a>
    </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb t-5 p-2 bg-light">
        <li class="breadcrumb-item"><a href="admincenter.php?site=admin_links"><?= $languageService->get('manage_links') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('breadcrumb_overview') ?></li>
    </ol>
</nav>

<div class="card-body p-0">
        <div class="container py-5">

<table class="table table-bordered table-striped align-middle">
    <thead class="table-light">
        <tr>
            <th><?= $languageService->get('image') ?></th>
            <th><?= $languageService->get('title') ?></th>
            <th><?= $languageService->get('url') ?></th>
            <th><?= $languageService->get('category') ?></th>
            <th><?= $languageService->get('clicks_per_day') ?></th>
            <th><?= $languageService->get('visible') ?></th>
            <th><?= $languageService->get('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php while ($link = $res->fetch_assoc()): ?>
            <?php 
            $createdTimestamp = isset($link['created_at']) ? strtotime($link['created_at']) : time();
            $days = max(1, round((time() - $createdTimestamp) / (60 * 60 * 24)));
            $perday = round($link['clicks'] / $days, 2);
            ?>
            <tr>
                <td>
                    <?php if ($link['image'] && file_exists($link['image'])): ?>
                        <img src="../<?= htmlspecialchars($link['image']) ?>" alt="Bild" style="max-height: 50px;">
                    <?php else: ?>
                        <span class="text-muted"><?= $languageService->get('no_image') ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($link['title']) ?></td>
                <td>
                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <?= htmlspecialchars($link['url']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($link['category']) ?></td>
                <td><?= (int)$link['clicks'] ?> (Ø <?= $perday ?>/Tag)</td>
                <td><?= $link['visible'] ? '<span class="text-success fw-bold">' . $languageService->get('yes') . '</span>' : '<span class="text-danger fw-bold">' . $languageService->get('no') . '</span>' ?></td>
                <td>
                    <a href="admincenter.php?site=admin_links&action=edit&id=<?= (int)$link['id'] ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil"></i> <?= $languageService->get('edit') ?>
                    </a>
                    <a href="admincenter.php?site=admin_links&action=delete&id=<?= (int)$link['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('<?= $languageService->get('delete_confirm') ?>')">
                        <i class="bi bi-trash"></i> <?= $languageService->get('delete') ?>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
 </div></div></div>

<?php
}
?>
<script>
document.getElementById('url').addEventListener('blur', function () {
    const url = this.value;
    const preview = document.getElementById('og-preview');

    preview.innerHTML = '<p class="text-muted">Lade Vorschau …</p>';

    fetch('/includes/plugins/links/admin/og_parser.php?url=' + encodeURIComponent(url))
        .then(response => response.json())
        .then(data => {
            if (data.og_image) {
                preview.innerHTML = '<img src="' + data.og_image + '" alt="OG-Image" style="max-height: 100px;">';
            } else {
                preview.innerHTML = '<p class="text-muted">Kein OG-Image gefunden.</p>';
            }
        })
        .catch(() => {
            preview.innerHTML = '<p class="text-danger">Fehler beim Laden der Vorschau.</p>';
        });
});
</script>