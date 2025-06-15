<?php
// admin/plugins/forum/admin_threads.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

session_start();
if (!isAdmin()) {
    die("Zugriff verweigert.");
}

$threads_res = safe_query("
    SELECT t.threadID, t.title, t.created_at, c.title AS category_title, u.username
    FROM plugins_forum_threads t
    LEFT JOIN plugins_forum_categories c ON t.catID = c.catID
    LEFT JOIN users u ON t.userID = u.userID
    ORDER BY t.updated_at DESC
");

$threads = [];
while ($row = mysqli_fetch_assoc($threads_res)) {
    $threads[] = $row;
}

// Thread löschen (per POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_thread'])) {
    $threadID = intval($_POST['delete_thread']);
    // Lösche Posts zuerst
    safe_query("DELETE FROM plugins_forum_posts WHERE threadID = $threadID");
    safe_query("DELETE FROM plugins_forum_threads WHERE threadID = $threadID");
    header("Location: admin_threads.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Threads verwalten</title>
    <link rel="stylesheet" href="../../../themes/default/css/admin.css" />
</head>
<body>
<h1>Threads verwalten</h1>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th><th>Titel</th><th>Kategorie</th><th>Erstellt von</th><th>Erstellt am</th><th>Aktionen</th>
    </tr>
    <?php foreach ($threads as $thread): ?>
        <tr>
            <td><?=intval($thread['threadID'])?></td>
            <td><?=htmlspecialchars($thread['title'])?></td>
            <td><?=htmlspecialchars($thread['category_title'])?></td>
            <td><?=htmlspecialchars($thread['username'] ?? 'Gast')?></td>
            <td><?=date('d.m.Y H:i', $thread['created_at'])?></td>
            <td>
                <form method="post" style="display:inline" onsubmit="return confirm('Thread wirklich löschen?');">
                    <button type="submit" name="delete_thread" value="<?=intval($thread['threadID'])?>">Löschen</button>
                </form>
                <a href="admin_edit_thread.php?id=<?=intval($thread['threadID'])?>">Bearbeiten</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<p><a href="../admincenter.php">Zurück zum Admincenter</a></p>
</body>
</html>
