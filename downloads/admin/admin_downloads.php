<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('downloads');

// Kategorien auslesen
$resultCats = safe_query("SELECT * FROM plugins_downloads_categories ORDER BY title ASC");
$cats = [];
while ($row = mysqli_fetch_array($resultCats, MYSQLI_ASSOC)) {
    $cats[$row['categoryID']] = $row['title'];
}

// Rollen auslesen
$role_result = safe_query("SELECT role_name FROM user_roles ORDER BY role_name ASC");
$allRoles = [];
while ($role = mysqli_fetch_array($role_result, MYSQLI_ASSOC)) {
    $allRoles[] = $role['role_name'];
}

// Aktion und ID
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kategorien-Formular
$catAction = $_GET['cataction'] ?? '';
$catID = isset($_GET['catid']) ? (int)$_GET['catid'] : 0;
$catErrors = [];
$catSuccess = '';
$catTitle = '';
$catDescription = ''; // neu

if ($catAction === 'delete' && $catID > 0) {
    safe_query("DELETE FROM plugins_downloads_categories WHERE categoryID=$catID");
    $catSuccess = "Kategorie gelöscht.";
    $catAction = '';
}

if ($catAction === 'edit' && $catID > 0) {
    $res = safe_query("SELECT * FROM plugins_downloads_categories WHERE categoryID=$catID");
    if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        $catTitle = $row['title'];
        $catDescription = $row['description']; // neu
    } else {
        $catErrors[] = "Kategorie nicht gefunden.";
        $catAction = '';
    }
}

if (in_array($catAction, ['add','edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $catTitleInput = trim($_POST['cat_title'] ?? '');
    $catDescriptionInput = trim($_POST['cat_description'] ?? ''); // neu

    if ($catTitleInput === '') {
        $catErrors[] = "Bitte einen Titel angeben.";
    }

    // Optional: weitere Validierung für Beschreibung hier (z.B. max Länge)

    if (empty($catErrors)) {
        $catTitleEscaped = $catTitleInput;
        $catDescriptionEscaped = $catDescriptionInput;

        if ($catAction === 'add') {
            safe_query("INSERT INTO plugins_downloads_categories (title, description) VALUES ('$catTitleEscaped', '$catDescriptionEscaped')");
            $catSuccess = "Kategorie hinzugefügt.";
        } else {
            safe_query("UPDATE plugins_downloads_categories SET title='$catTitleEscaped', description='$catDescriptionEscaped' WHERE categoryID=$catID");
            $catSuccess = "Kategorie aktualisiert.";
        }
        header("Location: ?site=admin_downloads&catsuccess=" . urlencode($catSuccess));
        exit;
    }
}


// Initialwerte Download
$dl = [
    'categoryID' => '',
    'title' => '',
    'description' => '',
    'file' => '',
    'access_roles' => json_encode([]),
];

// Fehlermeldungen
$errors = [];
$success = '';

if ($action === 'delete' && $id > 0) {
    $res = safe_query("SELECT file FROM plugins_downloads WHERE id = $id");
    if ($res && $res->num_rows) {
        $row = $res->fetch_assoc();
        $fileToDelete = __DIR__ . '/../files/' . $row['file'];
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
        safe_query("DELETE FROM plugins_downloads WHERE id = $id");
        $success = "Download gelöscht.";
    } else {
        $errors[] = "Download nicht gefunden.";
    }
    $action = '';
}

if ($action === 'edit' && $id > 0) {
    $res = safe_query("SELECT * FROM plugins_downloads WHERE id = $id");
    if ($res && $res->num_rows) {
        $dl = $res->fetch_assoc();
    } else {
        $errors[] = "Download nicht gefunden.";
        $action = '';
    }
}

// Formularverarbeitung add/edit Download
if (in_array($action, ['add', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryID   = (int)($_POST['categoryID'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $selectedRoles = $_POST['access_roles'] ?? [];

    if ($categoryID <= 0) $errors[] = "Bitte eine Kategorie wählen.";
    if ($title === '') $errors[] = "Bitte einen Titel angeben.";

    $uploadDir = __DIR__ . '/../files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = $dl['file'];

    if ($action === 'add' || (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE)) {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Datei-Upload fehlgeschlagen.";
        } else {
            $allowedExts = ['exe','zip','pdf','jpg','png'];
            $uploadedFilename = basename($_FILES['file']['name']);
            $ext = strtolower(pathinfo($uploadedFilename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts)) {
                $errors[] = "Nur EXE, ZIP, PDF, JPG oder PNG erlaubt.";
            } else {
                if ($action === 'edit' && $filename && file_exists($uploadDir . $filename)) {
                    unlink($uploadDir . $filename);
                }

                $originalName = pathinfo($uploadedFilename, PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '', $originalName);
                $filename = 'dl_' . $safeName . '_' . uniqid() . '.' . $ext;

                $dest = $uploadDir . $filename;

                if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    $errors[] = "Datei konnte nicht gespeichert werden.";
                }
            }
        }
    }

    if (empty($errors)) {
        $rolesJson = json_encode($selectedRoles);
        $esc_title = $title;
        $esc_description = $description;
        $esc_filename = $filename;

        if ($action === 'add') {
            safe_query("
                INSERT INTO plugins_downloads
                (categoryID, title, description, file, access_roles, downloads, uploaded_at)
                VALUES
                ('$categoryID', '$esc_title', '$esc_description', '$esc_filename', '$rolesJson', 0, NOW())"
            );
            $success = "Upload erfolgreich.";
        } else {
            safe_query("
                UPDATE plugins_downloads SET
                categoryID='$categoryID',
                title='$esc_title',
                description='$esc_description',
                file='$esc_filename',
                access_roles='$rolesJson',
                updated_at = NOW()
                WHERE id=$id
            ");
            $success = "Download aktualisiert.";
        }
        header("Location: ?site=admin_downloads&success=" . urlencode($success));
        exit;
    }
}

// Erfolgsmeldungen nach Redirect
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['catsuccess'])) {
    $catSuccess = htmlspecialchars($_GET['catsuccess']);
}

// Liste Downloads
$resultDownloads = safe_query("
    SELECT d.*, c.title AS category_title
    FROM plugins_downloads d
    LEFT JOIN plugins_downloads_categories c ON d.categoryID = c.categoryID
    ORDER BY d.uploaded_at DESC
");
$downloads = [];
while ($row = mysqli_fetch_array($resultDownloads, MYSQLI_ASSOC)) {
    $downloads[] = $row;
}

// Liste Kategorien (neu laden)
$resultCats = safe_query("SELECT * FROM plugins_downloads_categories ORDER BY title ASC");
$cats = [];
while ($row = mysqli_fetch_array($resultCats, MYSQLI_ASSOC)) {
    $cats[$row['categoryID']] = $row['title'];
}

// Download-Zähler aus der Log-Tabelle abrufen
$resultLogCounts = safe_query("
    SELECT fileID, COUNT(*) AS download_count
    FROM plugins_downloads_logs
    GROUP BY fileID
");

$downloadCounts = [];
while ($row = mysqli_fetch_array($resultLogCounts, MYSQLI_ASSOC)) {
    $downloadCounts[$row['fileID']] = $row['download_count'];
}
?>



<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> Downloads verwalten</div>
            <div>
            </div>
        </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_downloads">Downloads  verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>  

    <div class="card-body">

        <div class="container py-5">

 

  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($catSuccess): ?>
    <div class="alert alert-success"><?= $catSuccess ?></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul>
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul></div>
  <?php endif; ?>
  <?php if (!empty($catErrors)): ?>
    <div class="alert alert-danger"><ul>
      <?php foreach ($catErrors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
  <h4><?= $action === 'edit' ? 'Download bearbeiten' : 'Neuen Download hinzufügen' ?></h4>
  <form method="post" enctype="multipart/form-data" class="mb-5">
    <div class="mb-3">
      <label class="form-label">Kategorie</label>
      <select name="categoryID" class="form-select" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($cats as $catID => $catTitle): ?>
          <option value="<?= $catID ?>" <?= $dl['categoryID']==$catID?'selected':'' ?>><?= htmlspecialchars($catTitle) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Titel</label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($dl['title']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Beschreibung</label>
      <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($dl['description']) ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label"><?= $action==='edit'?'Datei ersetzen':'Datei' ?></label>
      <input type="file" name="file" class="form-control" <?= $action==='edit'?'':'required' ?> accept=".zip,.pdf,.jpg,.png">
      <?php if ($action==='edit' && $dl['file']): ?>
        <small class="form-text">Aktuelle Datei: <?= htmlspecialchars($dl['file']) ?></small>
      <?php endif; ?>
    </div>
    <div class="mb-3">
      <label class="form-label">Zugriffsrechte</label>
      <div>
      <?php
        $selectedRoles = json_decode($dl['access_roles'], true) ?: [];
        foreach ($allRoles as $role):
      ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="access_roles[]" id="role_<?= htmlspecialchars($role) ?>" value="<?= htmlspecialchars($role) ?>" <?= in_array($role,$selectedRoles)?'checked':'' ?>>
          <label class="form-check-label" for="role_<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></label>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-primary"><?= $action==='edit'?'Speichern':'Hochladen' ?></button>
    <a href="?site=admin_downloads" class="btn btn-secondary ms-2">Abbrechen</a>
  </form>
<?php elseif ($catAction==='add' || $catAction==='edit'): ?>
  <h4><?= $catAction==='edit'?'Kategorie bearbeiten':'Neue Kategorie hinzufügen' ?></h4>
    <form method="post" action="">
      <div class="mb-3">
        <label for="cat_title" class="form-label">Titel</label>
        <input type="text" id="cat_title" name="cat_title" class="form-control" value="<?= htmlspecialchars($catTitle) ?>" required>
      </div>
      <div class="mb-3">
        <label for="cat_description" class="form-label">Beschreibung</label>
        <textarea id="cat_description" name="cat_description" class="form-control" rows="3"><?= htmlspecialchars($catDescription) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><?= ($catAction === 'edit') ? 'Aktualisieren' : 'Hinzufügen' ?></button>
    </form>
<?php else: ?>
  <a href="?site=admin_downloads&action=add" class="btn btn-success mb-3">Neuen Download hinzufügen</a>
  <a href="?site=admin_downloads&cataction=add" class="btn btn-secondary mb-3 ms-2">Neue Kategorie hinzufügen</a>
  <a href="?site=admin_download_stats" class="btn btn-info mb-3 ms-2">Download Statistiken</a>

  <h4>Kategorien</h4>
  <table class="table table-bordered table-striped bg-white shadow-sm">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Titel</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($cats)): ?>
      <tr><td colspan="3">Keine Kategorien vorhanden.</td></tr>
    <?php else: ?>
      <?php foreach ($cats as $catID => $catTitle): ?>
        <tr>
          <td><?= $catID ?></td>
          <td><?= htmlspecialchars($catTitle) ?></td>
          <td>
            <a href="?site=admin_downloads&cataction=edit&catid=<?= $catID ?>" class="btn btn-sm btn-primary">Bearbeiten</a>
            <a href="?site=admin_downloads&cataction=delete&catid=<?= $catID ?>" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">Löschen</a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <h4>Downloads</h4>
  <table class="table table-bordered table-striped bg-white shadow-sm">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Kategorie</th>
        <th>Titel</th>
        <th>Datei</th>
        <th>Downloads</th>
        <th>Zugriff</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($downloads)): ?>
      <tr><td colspan="7" class="text-center">Keine Einträge gefunden.</td></tr>
    <?php else: ?>
      <?php foreach ($downloads as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['category_title']) ?></td>
          <td><?= htmlspecialchars($d['title']) ?></td>
          <td><?= htmlspecialchars($d['file']) ?></td>
          <td><?= isset($downloadCounts[$d['id']]) ? $downloadCounts[$d['id']] : 0 ?></td>
          <td>
            <?php
              $roles = json_decode($d['access_roles'],true) ?: [];
              echo htmlspecialchars(implode(', ',$roles));
            ?>
          </td>
          <td>
            <a href="?site=admin_downloads&action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-primary">Bearbeiten</a>
            <a href="?site=admin_downloads&action=delete&id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">Löschen</a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>

</div></div></div>
