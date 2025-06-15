<?php
// admin/plugins/forum/admin_categories.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

session_start();
if (!isAdmin()) { // Deine Admin-Check-Funktion
    die("Zugriff verweigert.");
}

// Kategorien anlegen, bearbeiten, löschen

// Formular-Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $catID = intval($_POST['catID'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $position = intval($_POST['position'] ?? 0);

    if ($action === 'add' && $title !== '') {
        $title = mysqli_real_escape_string($_database, $title);
        $description = mysqli_real_escape_string($_database, $description);

        safe_query("INSERT INTO plugins_forum_categories (title, description, position) VALUES ('$title', '$description', $position)");
        header("Location: admin_categories.php");
        exit;
    }

    if ($action === 'edit' && $catID > 0) {
        $title = mysqli_real_escape_string($_database, $title);
        $description = mysqli_real_escape_string($_database, $description);

        safe_query("UPDATE plugins_forum_categories SET title='$title', description='$description', position=$position WHERE catID=$catID");
        header("Location: admin_categories.php");
        exit;
    }

    if ($action === 'delete' && $catID > 0) {
        // Vorsicht: Threads & Posts vorher löschen oder verschieben
        safe_query("DELETE FROM plugins_forum_categories WHERE catID=$catID");
        header("Location: admin_categories.php");
        exit;
    }
}

// Kategorien laden
$res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($res)) {
    $categories[] = $row;
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Forum Kategorien Verwaltung</title>
    <link rel="stylesheet" href="../../../themes/default/css/admin.css" />
</head>
<body>
<h1>Kategorien verwalten</h1>

<h2>Neue Kategorie hinzufügen</h2>
<form method="post" action="">
    <input type="hidden" name="action" value="add" />
    <label>Titel:</label><br/>
    <input type="text" name="title" required /><br/>
    <label>Beschreibung:</label><br/>
    <textarea name="description" rows="3"></textarea><br/>
    <label>Position:</label><br/>
    <input type="number" name="position" value="0" /><br/><br/>
    <button type="submit">Kategorie anlegen</button>
</form>

<h2>Bestehende Kategorien</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th><th>Titel</th><th>Beschreibung</th><th>Position</th><th>Aktionen</th>
    </tr>
    <?php foreach ($categories as $cat): ?>
        <tr>
            <form method="post" action="">
                <td><?=intval($cat['catID'])?></td>
                <td><input type="text" name="title" value="<?=htmlspecialchars($cat['title'])?>" required /></td>
                <td><textarea name="description" rows="2"><?=htmlspecialchars($cat['description'])?></textarea></td>
                <td><input type="number" name="position" value="<?=intval($cat['position'])?>" /></td>
                <td>
                    <input type="hidden" name="catID" value="<?=intval($cat['catID'])?>" />
                    <button type="submit" name="action" value="edit">Speichern</button>
                    <button type="submit" name="action" value="delete" onclick="return confirm('Kategorie wirklich löschen? Alle Threads darin gehen verloren!')">Löschen</button>
                </td>
            </form>
        </tr>
    <?php endforeach; ?>
</table>

<p><a href="../admincenter.php">Zurück zum Admincenter</a></p>
</body>
</html>
