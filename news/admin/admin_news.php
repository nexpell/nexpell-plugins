<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\NavigationUpdater;// SEO Anpassung

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('news');

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('news');

$filepath = $plugin_path."images/news_images/";

// Parameter aus URL lesen
$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortDir = ($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Max News pro Seite
$perPage = 10;

// Whitelist für Sortierung
$allowedSorts = ['title', 'created_at'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}

$uploadDir = __DIR__ . '/../images/'; // für allgemeine Uploads

// --- AJAX-Löschung ---
if (($action ?? '') === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    
    if ($stmt->fetch()) {
        $stmt->close();

        // News aus DB löschen
        $stmtDel = $_database->prepare("DELETE FROM plugins_news WHERE id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();
        $stmtDel->close();

        // Bilddatei löschen, wenn vorhanden
        if (!empty($imageFilename)) {
            @unlink($plugin_path . $imageFilename);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'News nicht gefunden']);
    }
    exit;
}

function makeUniqueSlug($slug, $id = 0) {
    global $_database;

    $baseSlug = $slug;
    $i = 1;

    $stmt = $_database->prepare("SELECT id FROM plugins_news WHERE slug = ? AND id != ?");
    $stmt->bind_param("si", $slug, $id);

    while (true) {
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            break; // Slug ist frei
        }

        $slug = $baseSlug . '-' . $i;
        $i++;
    }

    $stmt->close();
    return $slug;
}

function makeUniqueSlugCategory(string $slug, int $ignoreId = 0): string {
    global $_database;
    $base = $slug;
    $i = 1;
    while (true) {
        if ($ignoreId > 0) {
            $stmt = $_database->prepare("SELECT id FROM plugins_news_categories WHERE slug = ? AND id != ?");
            $stmt->bind_param("si", $slug, $ignoreId);
        } else {
            $stmt = $_database->prepare("SELECT id FROM plugins_news_categories WHERE slug = ?");
            $stmt->bind_param("s", $slug);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return $slug;
        }
        $stmt->close();
        $slug = $base . '-' . $i++;
    }
}



function generateSlug(string $text): string {
    // Umlaute & Sonderzeichen ersetzen
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    // Kleinbuchstaben
    $text = strtolower($text);
    // Nicht alphanumerische Zeichen durch Bindestrich
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    // Mehrfache Bindestriche entfernen
    $text = preg_replace('/-+/', '-', $text);
    // Bindestriche am Rand entfernen
    $text = trim($text, '-');
    return $text ?: 'news-' . time(); // Fallback
}





// --- News hinzufügen / bearbeiten ---
if (($action ?? '') === "add" || ($action ?? '') === "edit") {
    $id = intval($_GET['id'] ?? 0);
    $isEdit = $id > 0;

    // Default-Daten
    $data = [
        'category_id'    => 0,
        'title'          => '',
        'slug'           => '',
        'link'           => '',
        'content'        => '',
        'sort_order'     => 0,
        'is_active'      => 0,
        'allow_comments' => 0,
    ];

    $oldSlug = ''; // alter Slug für SEO-Warnung

    // Beim Edit vorhandene Daten laden
    if ($isEdit) {
        $stmt = $_database->prepare("
            SELECT category_id, title, slug, link, content, sort_order, is_active, allow_comments
            FROM plugins_news
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result(
            $data['category_id'],
            $data['title'],
            $data['slug'],
            $data['link'],
            $data['content'],
            $data['sort_order'],
            $data['is_active'],
            $data['allow_comments']
        );
        if (!$stmt->fetch()) {
            echo "<div class='alert alert-danger'>News nicht gefunden.</div>";
            exit;
        }
        $stmt->close();

        $oldSlug = $data['slug']; // merken für späteren Vergleich
    }

    $error = '';
    $slugWarning = '';

    // Hilfsfunktion: Alle Formulardaten als hidden-Felder ausgeben
    function hiddenFields(array $data): string {
        $html = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) continue; // nur einfache Werte
            $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">' . "\n";
        }
        return $html;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cat            = intval($_POST['category_id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $slugInput      = trim($_POST['slug'] ?? '');
        $link           = trim($_POST['link'] ?? '');
        $sort_order     = intval($_POST['sort_order'] ?? 0);
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
        $content        = $_POST['content'];
        $confirmChange  = isset($_POST['confirm_slug_change']); // kommt vom Warn-Dialog

        if ($content === '') {
            die("Inhalt darf nicht leer sein.");
        }

        // Slug automatisch generieren, wenn leer
        if ($slugInput === '') {
            $slugInput = generateSlug($title); // deine vorhandene Funktion
        }

        // Slug automatisch generieren, wenn leer
        if ($slugInput === '') {
            if ($title !== '') {
                $slugInput = generateSlug($title); // deine vorhandene Funktion
            } else {
                // Fallback: Slug aus Timestamp, falls kein Titel vorhanden
                $slugInput = 'news-' . time();
            }
        }

        // Prüfen ob sich der Slug beim Edit geändert hat (nur wenn noch nicht bestätigt)
        if ($isEdit && !$confirmChange && $oldSlug !== '' && $slugInput !== $oldSlug) {
            echo "
                <div class='alert alert-warning'>
                    <strong>Achtung:</strong> Der SEO-Slug wurde geändert!<br><br>
                    Alte URL: <code>/news/{$oldSlug}</code><br>
                    Neue URL: <code>/news/{$slugInput}</code><br>
                    <small>Das kann bestehende Links und SEO-Rankings beeinflussen.</small>
                </div>

                <form method='post'>
                    " . hiddenFields($_POST) . "
                    <input type='hidden' name='confirm_slug_change' value='1'>
                    <button type='submit' class='btn btn-danger'>Weiter & speichern</button>
                    <a href='admincenter.php?site=admin_news&action=edit&id={$id}' class='btn btn-secondary'>Zurück, nicht speichern</a>
                </form>
            ";
            exit;
        }

        // Slug eindeutig machen
        $slug = makeUniqueSlug($slugInput, $isEdit ? $id : 0);

        if (!$error) {
            if ($isEdit) {
                safe_query("
                    UPDATE plugins_news SET
                        category_id   = '" . (int)$cat . "',
                        title         = '" . escape($title) . "',
                        slug          = '" . escape($slug) . "',
                        link          = '" . escape($link) . "',
                        content       = '" . $content . "',
                        sort_order    = '" . (int)$sort_order . "',
                        is_active     = '" . (int)$is_active . "',
                        allow_comments= '" . (int)$allow_comments . "'
                    WHERE id = '" . (int)$id . "'
                ");
            } else {
                $userID = 1;
                safe_query("
                    INSERT INTO plugins_news
                    (category_id, title, slug, link, content, sort_order, updated_at, userID, is_active, allow_comments)
                    VALUES
                    ('" . (int)$cat . "', '" . escape($title) . "', '" . escape($slug) . "', '" . escape($link) . "', '" . $content . "',
                     '" . (int)$sort_order . "', UNIX_TIMESTAMP(), '" . (int)$userID . "', '" . (int)$is_active . "', '" . (int)$allow_comments . "')
                ");
            }

            /////////////////////////////////////////////////////////////////////////////
            // Datei-Name des aktuellen Admin-Moduls ermitteln
            // Aktualisiert das Änderungsdatum in der Navigation für dieses Modul
            // Warum das wichtig ist:
            // ✅ Google liest das Änderungsdatum über die sitemap.xml (Tag <lastmod>)
            // ✅ Wenn sich Inhalte ändern, soll Google das bemerken
            // ✅ Dadurch werden Seiten öfter und gezielter gecrawlt (besseres SEO)
            // ✅ Das Datum bleibt so immer aktuell – automatisch und ohne Pflegeaufwand
            $admin_file = basename(__FILE__, '.php');
            echo NavigationUpdater::updateFromAdminFile($admin_file);
            /////////////////////////////////////////////////////////////////////////////

            header("Location: admincenter.php?site=admin_news");
            exit;
        }
    }
    ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-journal-text"></i> News <?= $isEdit ? "bearbeiten" : "hinzufügen" ?>
        </div>
        <nav class="breadcrumb bg-light p-2">
            <a class="breadcrumb-item" href="admincenter.php?site=admin_news">News verwalten</a>
            <span class="breadcrumb-item active"><?= $isEdit ? "Bearbeiten" : "Hinzufügen" ?></span>
        </nav>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?= $slugWarning ?>
            <div class="container py-5">
                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategorie:</label>
                        <select class="form-select" name="category_id" id="category_id" required>
                            <option value="">Bitte wählen...</option>
                            <?php
                            $stmtCat = $_database->prepare("SELECT id, name FROM plugins_news_categories ORDER BY name");
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
                        <textarea id="ckeditor" name="content" class="ckeditor" rows="6" style="resize: vertical; width: 100%;" required><?= $data['content'] ?></textarea>
                        <div id="dropArea" class="mt-2 p-4 text-center border border-secondary rounded bg-light" style="cursor: pointer;">
                            📎 Hier klicken oder Bild per Drag & Drop einfügen
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label">SEO-Slug (URL-Teil):</label>
                        <input class="form-control" type="text" name="slug" id="slug" value="<?= htmlspecialchars($data['slug']) ?>">
                        <div class="form-text">Wird in der URL genutzt, z. B. /news/123/dein-seo-slug</div>
                    </div>

                    <div class="mb-3">
                        <label for="link" class="form-label">Interner Link/Dateiname:</label>
                        <input class="form-control" type="text" name="link" id="link" value="<?= htmlspecialchars($data['link']) ?>">
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

                    <input type="file" id="uploadImage" accept="image/*" style="display: none;"><br/>
                    <button type="submit" class="btn btn-success"><?= $isEdit ? "Speichern" : "Hinzufügen" ?></button>
                    <a href="admincenter.php?site=admin_news" class="btn btn-secondary">Abbrechen</a>
                </form>
            </div>
        </div>
    </div>


    <?php


} elseif (($action ?? '') === 'addcategory' || ($action ?? '') === 'editcategory') {
    $isEdit = $action === 'editcategory';
    $errorCat = '';
    $cat_name = '';
    $cat_description = '';
    $cat_image = ''; // neues Feld
    $cat_slug = '';  // Slug-Feld
    $editId = 0;
    $slugWarning = '';

    if ($isEdit) {
        $editId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $stmt = $_database->prepare("SELECT name, description, image, slug FROM plugins_news_categories WHERE id = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $catData = $result->fetch_assoc();
        $stmt->close();

        if ($catData) {
            $cat_name = $catData['name'];
            $cat_description = $catData['description'];
            $cat_image = $catData['image']; // aktuelles Bild
            $cat_slug = $catData['slug'];
        } else {
            $errorCat = "Kategorie nicht gefunden.";
        }
    }

    // Speichern
    // Slug-Vergleich: alten Slug beim Edit merken
    $oldSlug = $isEdit ? $cat_slug : '';
    $confirmChange = isset($_POST['confirm_slug_change']); // Wurde schon bestätigt?

    // Speichern
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cat_name'])) {

        $cat_name = trim($_POST['cat_name']);
        $cat_description = trim($_POST['cat_description'] ?? '');
        $cat_image_path = $cat_image; // altes Bild
        $cat_slug_input = trim($_POST['cat_slug'] ?? '');
        $confirmChange = isset($_POST['confirm_slug_change']);

        // Slug automatisch erstellen
        if ($cat_slug_input === '') {
            $cat_slug_input = $cat_name !== '' ? generateSlug($cat_name) : 'category-' . time();
        }

        // Warnung beim geänderten Slug (nur Edit & noch nicht bestätigt)
        if ($isEdit && !$confirmChange && $oldSlug !== '' && $cat_slug_input !== $oldSlug) {
            $slugWarning = "
                <div class='alert alert-warning'>
                    <strong>Achtung:</strong> Der SEO-Slug wurde geändert!<br><br>
                    Alte URL: <code>/news/category/{$oldSlug}</code><br>
                    Neue URL: <code>/news/category/{$cat_slug_input}</code><br>
                    <small>Das kann bestehende Links und SEO-Rankings beeinflussen.</small>
                </div>
                <form method='post' enctype='multipart/form-data'>
                    " . hiddenFields($_POST) . "
                    <input type='hidden' name='confirm_slug_change' value='1'>
                    <button type='submit' class='btn btn-danger'>Weiter & speichern</button>
                    <a href='admincenter.php?site=admin_news&action=categories' class='btn btn-secondary'>Zurück, nicht speichern</a>
                </form>
            ";
        }

        // Wenn Warnung angezeigt wird, abbrechen
        if ($slugWarning === '') {

            // Slug eindeutig machen
            $cat_slug = makeUniqueSlugCategory($cat_slug_input, $isEdit ? $editId : 0);

            // Bild-Upload
            if (isset($_FILES['cat_image']) && $_FILES['cat_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/news/images/news_categories/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = time() . '_' . basename($_FILES['cat_image']['name']);
                $targetFile = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['cat_image']['tmp_name'], $targetFile)) {
                    $cat_image_path = $filename;
                } else {
                    $errorCat = "Fehler beim Hochladen der Datei!";
                }
            }

            if ($cat_name === '') {
                $errorCat = "Der Kategoriename darf nicht leer sein.";
            } else {
                if ($isEdit && $editId > 0) {
                    $stmt = $_database->prepare(
                        "UPDATE plugins_news_categories SET name = ?, slug = ?, description = ?, image = ? WHERE id = ?"
                    );
                    $stmt->bind_param("ssssi", $cat_name, $cat_slug, $cat_description, $cat_image_path, $editId);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $_database->prepare(
                        "INSERT INTO plugins_news_categories (name, slug, description, image) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssss", $cat_name, $cat_slug, $cat_description, $cat_image_path);
                    $stmt->execute();
                    $stmt->close();
                }

                header("Location: admincenter.php?site=admin_news&action=categories");
                exit;


            }
        }
    }    
    ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-tags"></i> <?= $isEdit ? 'Kategorie bearbeiten' : 'Neue Kategorie hinzufügen' ?>
        </div>
        <nav class="breadcrumb bg-light p-2">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_news">News verwalten</a></li>
            <a class="breadcrumb-item" href="admincenter.php?site=admin_news&action=categories">Kategorien</a>
            <span class="breadcrumb-item active"><?= $isEdit ? 'Bearbeiten' : 'Hinzufügen' ?></span>
        </nav>
        <div class="card-body">
            <div class="container py-5">
                <?php if ($errorCat): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errorCat) ?></div>
                <?php endif; ?>
                <?= $slugWarning ?>
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="cat_name" class="form-label">Kategoriename:</label>
                        <input type="text"
                               class="form-control"
                               id="cat_name"
                               name="cat_name"
                               value="<?= htmlspecialchars($cat_name) ?>"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="cat_slug" class="form-label">Slug (SEO-URL):</label>
                        <input type="text"
                               class="form-control"
                               id="cat_slug"
                               name="cat_slug"
                               value="<?= htmlspecialchars($cat_slug) ?>"
                               placeholder="leer lassen für automatische Erstellung">
                    </div>
                    <div class="mb-3">
                        <label for="cat_description" class="form-label">Beschreibung:</label>
                        <textarea class="form-control"
                                  id="cat_description"
                                  name="cat_description"
                                  rows="3"><?= htmlspecialchars($cat_description) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="cat_image" class="form-label">Bild (optional):</label>
                        <input type="file" class="form-control" id="cat_image" name="cat_image">
                        <?php if ($cat_image): ?>
                            <small class="text-muted">Aktuelles Bild:</small><br>
                            <?php $basePath = '/includes/plugins/news/images/news_categories/'; ?>
                            <img src="<?= htmlspecialchars($basePath . $cat_image) ?>" alt="Kategorie-Bild" style="height:60px;">
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? 'Änderungen speichern' : 'Kategorie hinzufügen' ?>
                    </button>
                    <a href="admincenter.php?site=admin_news&action=categories" class="btn btn-secondary">Abbrechen</a>
                </form>
            </div>
        </div>
    </div>

    <?php
}

 elseif (($action ?? '') === 'categories') {
    $errorCat = '';

    // Kategorie löschen
    if (isset($_GET['delcat'])) {
        $delcat = intval($_GET['delcat']);
        $stmt = $_database->prepare("DELETE FROM plugins_news_categories WHERE id = ?");
        $stmt->bind_param("i", $delcat);
        $stmt->execute();
        $stmt->close();
        header("Location: admincenter.php?site=admin_news&action=categories");
        exit;
    }

    // Kategorien laden inkl. Beschreibung
    $result = $_database->query("SELECT id, name, description, image FROM plugins_news_categories ORDER BY name");

    ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-tags"></i> Kategorien verwalten
        </div>
        <nav class="breadcrumb bg-light p-2">
            <a class="breadcrumb-item" href="admincenter.php?site=admin_news">News verwalten</a>
            <span class="breadcrumb-item active">Kategorien / Add & Edit</span>
        </nav>
        <div class="card-body">
            <div class="container py-5">

                <a href="admincenter.php?site=admin_news&action=addcategory"
                   class="btn btn-success mb-3">
                   <i class="bi bi-plus-circle"></i> Neue Kategorie hinzufügen
                </a>
                
                <h5>Bestehende Kategorien:</h5>
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bild</th> <!-- neue Spalte -->
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Aktion</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($cat = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$cat['id'] ?></td>
                            <td>
                                <?php if (!empty($cat['image'])):
                                    $basePath = '/includes/plugins/news/images/news_categories/'; ?>
                                    <img src="<?= htmlspecialchars($basePath . $cat['image']) ?>" alt="Kategorie-Bild" style="height:60px;">
                                <?php else: ?>
                                    <span class="text-muted">kein Bild</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['description']) ?></td>
                            <td>
                                <a href="admincenter.php?site=admin_news&action=editcategory&id=<?= (int)$cat['id'] ?>"
                                   class="btn btn-sm btn-warning">Bearbeiten</a>
                                <a href="admincenter.php?site=admin_news&action=categories&delcat=<?= (int)$cat['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Kategorie wirklich löschen?')">Löschen</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>


                <a href="admincenter.php?site=admin_news" class="btn btn-secondary">Zurück</a>
            </div>
        </div>
    </div>
    <?php
}

 else {

    // --- Newsliste anzeigen ---
    $result = $_database->query("SELECT a.id, a.title, a.sort_order, a.topnews_is_active, a.is_active, c.name as category_name FROM plugins_news a LEFT JOIN plugins_news_categories c ON a.category_id = c.id ORDER BY a.sort_order ASC, a.title ASC");
    ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> News verwalten</div>
            <div>
                <a href="admincenter.php?site=admin_news&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Neu</a>
                <a href="admincenter.php?site=admin_news&action=categories" class="btn btn-primary btn-sm"><i class="bi bi-tags"></i> Kategorien</a>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=admin_news">News verwalten</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add & Edit</li>
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
                    <th>Top News</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                    <th>Sortierung</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                        <td><?= $row['topnews_is_active'] ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nein</span>' ?></td>                        
                        <td><?= $row['is_active'] ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nein</span>' ?></td>
                        <td>
                            <a href="admincenter.php?site=admin_news&action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Bearbeiten</a>
                            <a href="#" class="btn btn-sm btn-danger btn-delete-news" data-id="<?= (int)$row['id'] ?>"><i class="bi bi-trash"></i> Löschen</a>
                        </td>
                        <td><?= (int)$row['sort_order'] ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
<?php
}
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('uploadImage');
  let editor = null;

  // Wenn keine DropArea im DOM -> Script sofort beenden
  if (!dropArea || !fileInput) {
    return;
  }

  // CKEditor-Instanz beobachten
  CKEDITOR.on('instanceReady', function(evt) {
    if (evt.editor.name === 'ckeditor') {
      editor = evt.editor;
    }
  });

  dropArea.addEventListener('click', () => fileInput.click());

  dropArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropArea.classList.add('bg-warning');
  });

  dropArea.addEventListener('dragleave', () => {
    dropArea.classList.remove('bg-warning');
  });

  dropArea.addEventListener('drop', (e) => {
    e.preventDefault();
    dropArea.classList.remove('bg-warning');
    if (e.dataTransfer.files.length > 0) {
      uploadImage(e.dataTransfer.files[0]);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
      uploadImage(fileInput.files[0]);
    }
  });

  function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);

    fetch('/includes/plugins/news/upload_image.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.url) {
        if (!editor) {
          console.error('CKEditor ist noch nicht bereit.');
          return;
        }
        editor.focus();
        // Bild als Link einfügen, 75% Breite, klickbar für Original
        const html = `<a href="${data.url}" target="_blank"><img src="${data.url}" style="width:75%; height:auto;" alt=""></a>`;
        editor.insertHtml(html);
      } else {
        alert(data.message || 'Upload fehlgeschlagen');
      }
    })
    .catch(err => {
      alert('Fehler beim Upload: ' + err.message);
    });
  }
});
</script>
<script>
document.querySelectorAll('.btn-delete-news').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('News wirklich löschen?')) {
            const id = this.getAttribute('data-id');
            fetch('admincenter.php?site=admin_news&action=delete&id=' + id)
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

