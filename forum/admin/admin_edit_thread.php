<?php
// admin/plugins/forum/admin_edit_thread.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

session_start();
if (!isAdmin()) {
    die("Zugriff verweigert.");
}

$threadID = intval($_GET['id'] ?? 0);
if ($threadID <= 0) {
    die("Ungültiger Thread.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $catID = intval($_POST['catID'] ?? 0);

    if ($title === '' || $catID <= 0) {
        die("Titel und Kategorie sind Pflichtfelder.");
    }

    $title = mysqli_real_escape_string($_database, $title);

    safe_query("UPDATE plugins_forum_threads SET title='$title', catID=$catID WHERE threadID=$threadID");
    header("Location: admin_threads.php");
    exit;
}

// Thread laden
$res = safe_query("SELECT * FROM plugins_forum_threads WHERE threadID=$threadID");
if (mysqli_num_rows($res) === 0) {
    die("Thread nicht gefunden.");
}
$thread = mysqli_fetch_assoc($res);

// Kategorien laden
$cat_res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($cat_res)) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Thread bearbeiten</title>
    <link rel="stylesheet" href="../../../themes/default/css/admin.css" />
</head>
<body>
<h1>Thread bearbeiten</h1>

<form method="post" action="">
    <label>Titel:</label><br/>
    <input type="text" name="title" value="<?=htmlspecialchars($thread['title'])?>" required /><br/><br/>

    <label>Kategorie:</label><br/>
    <select name="catID" required>
        <?php foreach ($categories as $cat): ?>
            <option value="<?=intval($cat['catID'])?>" <?=($thread['catID']==$cat['catID'])?'selected':''?>><?=htmlspecialchars($cat['title'])?></option>
        <?php endforeach; ?>
    </select><br/><br/>

    <button type="submit">Speichern</button>
</form>

<p><a href="admin_threads.php">Zurück zur Thread-Übersicht</a></p>
</body>
</html>
