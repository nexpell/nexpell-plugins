<?php

use webspell\LanguageService;

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
$languageService->readPluginModule('articles');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('articles');

$filepath = $plugin_path."images/article/";

// Parameter aus URL lesen
$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortDir = ($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Max Artikel pro Seite
$perPage = 10;

// Whitelist für Sortierung
$allowedSorts = ['title', 'created_at'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}

$uploadDir = __DIR__ . '/../images/'; // für allgemeine Uploads
$plugin_path = __DIR__ . '/../images/article/'; // für Bannerbild Upload

// --- AJAX-Löschung ---
if (($action ?? '') === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Artikelinformationen laden (inkl. optional Bildname)
    $stmt = $_database->prepare("SELECT banner_image FROM plugins_articles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($imageFilename);
    if ($stmt->fetch()) {
        $stmt->close();

        // Artikel aus DB löschen
        $stmtDel = $_database->prepare("DELETE FROM plugins_articles WHERE id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();
        $stmtDel->close();

        // Bilddatei löschen, wenn vorhanden
        if (!empty($imageFilename)) {
            @unlink($plugin_path . $imageFilename);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Artikel nicht gefunden']);
    }
    exit;
}

// --- Artikel hinzufügen / bearbeiten ---
if (($action ?? '') === "add" || ($action ?? '') === "edit") {
    $id = intval($_GET['id'] ?? 0);
    $isEdit = $id > 0;

    // Default-Daten
    $data = [
        'category_id'   => 0,
        'title'         => '',
        'content'       => '',
        'slug'          => '',
        'banner_image'  => '',
        'sort_order'    => 0,
        'is_active'     => 0,
        'allow_comments'=> 0,
    ];

    // Beim Edit vorhandene Daten laden
    if ($isEdit) {
        $stmt = $_database->prepare("SELECT category_id, title, content, slug, banner_image, sort_order, is_active, allow_comments FROM plugins_articles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result(
            $data['category_id'], $data['title'], $data['content'], $data['slug'],
            $data['banner_image'], $data['sort_order'], $data['is_active'], $data['allow_comments']
        );
        if (!$stmt->fetch()) {
            echo "<div class='alert alert-danger'>Artikel nicht gefunden.</div>";
            exit;
        }
        $stmt->close();
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cat           = intval($_POST['category_id'] ?? 0);
        $title         = trim($_POST['title'] ?? '');
        $content = $_POST['message'];
        $slug          = trim($_POST['slug'] ?? '');
        $sort_order    = intval($_POST['sort_order'] ?? 0);
        $is_active     = isset($_POST['is_active']) ? 1 : 0;
        $allow_comments= isset($_POST['allow_comments']) ? 1 : 0;
        $filename      = $data['banner_image'];

        // Bannerbild-Upload prüfen
        if (!empty($_FILES['banner_image']['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $imageType = mime_content_type($_FILES['banner_image']['tmp_name']);

            if (in_array($imageType, $allowedTypes)) {
                $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                // Dateiname bestimmen (bei Edit: ID.ext, sonst uniqid)
                $filename = $isEdit ? $id . '.' . $ext : uniqid() . '.' . $ext;
                $targetPath = $plugin_path . $filename;

                if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $targetPath)) {
                    // Altes Bild löschen bei Edit
                    if ($isEdit && $data['banner_image'] && $data['banner_image'] !== $filename && file_exists($plugin_path . $data['banner_image'])) {
                        @unlink($plugin_path . $data['banner_image']);
                    }
                } else {
                    $error = 'Fehler beim Speichern des Bildes.';
                }
            } else {
                $error = 'Ungültiger Bildtyp.';
            }
        } elseif (!$isEdit) {
            $error = 'Bannerbild ist erforderlich.';
        }

        if (!$error) {

            if ($isEdit) {                

                safe_query("
                    UPDATE plugins_articles SET
                    category_id = '$cat',
                    title = '$title',
                    content = '$content',
                    slug = '$slug',
                    banner_image = '$filename',
                    sort_order = '$sort_order',
                    is_active = '$is_active',
                    allow_comments = '$allow_comments',
                    updated_at = UNIX_TIMESTAMP()
                    WHERE id = '$id'
                ");
            } else {
                $userID = 1; 

                safe_query("
                    INSERT INTO plugins_articles
                    (category_id, title, content, slug, banner_image, sort_order, updated_at, userID, is_active, allow_comments)
                    VALUES
                    ('$cat', '$title', '$content', '$slug', '$filename', '$sort_order', UNIX_TIMESTAMP(), '$userID', '$is_active', '$allow_comments')
                ");
            }

            header("Location: admincenter.php?site=admin_articles");
            exit;
        }
    }
    ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-journal-text"></i> Artikel <?= $isEdit ? "bearbeiten" : "hinzufügen" ?>
        </div>
        <nav class="breadcrumb bg-light p-2">
            <a class="breadcrumb-item" href="admincenter.php?site=admin_articles">Artikel verwalten</a>
            <span class="breadcrumb-item active"><?= $isEdit ? "Bearbeiten" : "Hinzufügen" ?></span>
        </nav>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="container py-5">
            <form method="post" enctype="multipart/form-data" novalidate>
                

                <div class="mb-3">
                    <label for="category_id" class="form-label">Kategorie:</label>
                    <select class="form-select" name="category_id" id="category_id" required>
                        <option value="">Bitte wählen...</option>
                        <?php
                        $stmtCat = $_database->prepare("SELECT id, name FROM plugins_articles_categories ORDER BY name");
                        $stmtCat->execute();
                        $resCat = $stmtCat->get_result();
                        while ($cat = $resCat->fetch_assoc()) {
                            $selected = ($cat['id'] == $data['category_id']) ? 'selected' : '';
                            echo '<option value="' . (int)$cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
                        }
                        $stmtCat->close();
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="title" class="form-label">Titel:</label>
                    <input class="form-control" type="text" name="title" id="title" value="<?= htmlspecialchars($data['title']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="content_editor" class="form-label">Inhalt:</label>
                    <textarea class="ckeditor" name="message" rows="10"><?= $data['content'] ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug (URL-Teil):</label>
                    <input class="form-control" type="text" name="slug" id="slug" value="<?= htmlspecialchars($data['slug']) ?>">
                </div>

                <?php if ($isEdit && $data['banner_image'] && file_exists($plugin_path . $data['banner_image'])): ?>
                    <p><strong>Aktuelles Banner:</strong><br>
                        <img src="/includes/plugins/articles/images/article/<?= htmlspecialchars($data['banner_image']) ?>" class="img-thumbnail" width="200" alt="Banner">

                    </p>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="banner_image" class="form-label">Bannerbild (JPG/PNG/WebP/GIF):</label>
                    <input class="form-control" type="file" name="banner_image" id="banner_image" <?= $isEdit ? '' : 'required' ?>>
                </div>

                <div class="mb-3">
                    <label for="sort_order" class="form-label">Sortierung:</label>
                    <input class="form-control" type="number" name="sort_order" id="sort_order" value="<?= htmlspecialchars($data['sort_order']) ?>">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $data['is_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Aktiv</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="allow_comments" id="allow_comments" <?= $data['allow_comments'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_comments">Kommentare erlauben</label>
                </div>

                <button type="submit" class="btn btn-success"><?= $isEdit ? "Speichern" : "Hinzufügen" ?></button>
                <a href="admincenter.php?site=admin_articles" class="btn btn-secondary">Abbrechen</a>
            </form>
            </div>
        </div>
    </div>

    <?php

} elseif (($action ?? '') === 'categories') {
    // --- Kategorien verwalten ---
    $errorCat = '';

    // Kategorie löschen (via GET)
    if (isset($_GET['delcat'])) {
        $delcat = intval($_GET['delcat']);
        $stmt = $_database->prepare("DELETE FROM plugins_articles_categories WHERE id = ?");
        $stmt->bind_param("i", $delcat);
        $stmt->execute();
        $stmt->close();

        header("Location: admincenter.php?site=admin_articles&action=categories");
        exit;
    }

    // Neue Kategorie hinzufügen
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cat_name'])) {
        $cat_name = trim($_POST['cat_name']);
        
        if ($cat_name === '') {
            $errorCat = "Der Kategoriename darf nicht leer sein.";
        } else {
            // Kategorie speichern
            $stmt = $_database->prepare("INSERT INTO plugins_articles_categories (name) VALUES (?)");
            $stmt->bind_param("s", $cat_name);
            $stmt->execute();
            $stmt->close();
            header("Location: admincenter.php?site=admin_articles&action=categories");
            exit;
        }
    }

    // Kategorien laden
    $result = $_database->query("SELECT id, name FROM plugins_articles_categories ORDER BY name");
    ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-tags"></i> Kategorien verwalten
        </div>
        <nav class="breadcrumb bg-light p-2">
            <a class="breadcrumb-item" href="admincenter.php?site=admin_articles">Artikel verwalten</a>
            <span class="breadcrumb-item active">Kategorien</span>
        </nav>
        <div class="card-body">
        <div class="container py-5">
            <?php if ($errorCat): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorCat) ?></div>
            <?php endif; ?>

            <form method="post" class="mb-4">
                <div class="mb-3">
                    <label for="cat_name" class="form-label">Neue Kategorie hinzufügen:</label>
                    <input type="text" class="form-control" id="cat_name" name="cat_name" required>
                </div>

                <button type="submit" class="btn btn-primary">Kategorie hinzufügen</button>
            </form>

            <h5>Bestehende Kategorien:</h5>
            <table class="table table-striped">
                <thead>
                <tr><th>ID</th><th>Name</th><th>Aktion</th></tr>
                </thead>
                <tbody>
                <?php while ($cat = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$cat['id'] ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td>
                            <a href="admincenter.php?site=admin_articles&action=categories&delcat=<?= (int)$cat['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Kategorie wirklich löschen?')">Löschen</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <a href="admincenter.php?site=admin_articles" class="btn btn-secondary">Zurück</a>
        </div>
        </div>
    </div>

    <?php
} else {

    // --- Artikelliste anzeigen ---
    $result = $_database->query("SELECT a.id, a.title, a.sort_order, a.is_active, c.name as category_name FROM plugins_articles a LEFT JOIN plugins_articles_categories c ON a.category_id = c.id ORDER BY a.sort_order ASC, a.title ASC");
    ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> Artikel verwalten</div>
            <div>
                <a href="admincenter.php?site=admin_articles&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neu</a>
                <a href="admincenter.php?site=admin_articles&action=categories" class="btn btn-primary btn-sm"><i class="bi bi-tags"></i> Kategorien</a>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=admin_articles">Artikel verwalten</a></li>
                <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
            </ol>
        </nav> 
        <div class="card-body p-0">
            <div class="container py-5">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Titel</th>
                    <th>Kategorie</th>
                    <th>Sortierung</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                        <td><?= (int)$row['sort_order'] ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nein</span>' ?></td>
                        <td>
                            <a href="admincenter.php?site=admin_articles&action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Bearbeiten</a>
                            <a href="#" class="btn btn-sm btn-danger btn-delete-article" data-id="<?= (int)$row['id'] ?>"><i class="bi bi-trash"></i> Löschen</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
<?php
}  // schließt das else
?>
    <script>
    document.querySelectorAll('.btn-delete-article').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Artikel wirklich löschen?')) {
                const id = this.getAttribute('data-id');
                fetch('admincenter.php?site=admin_articles&action=delete&id=' + id)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Fehler beim Löschen: ' + (data.error || 'Unbekannt'));
                        }
                    });
            }
        });
    });
    </script>

