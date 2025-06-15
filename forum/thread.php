<?php
// includes/plugins/forum/thread.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

$threadID = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($threadID <= 0) {
    die("Ungültiger Thread.");
}

// Thread und Posts laden
$thread_res = safe_query("SELECT * FROM plugins_forum_threads WHERE threadID = $threadID");
if (mysqli_num_rows($thread_res) === 0) {
    die("Thread nicht gefunden.");
}
$thread = mysqli_fetch_assoc($thread_res);

// Views erhöhen
safe_query("UPDATE plugins_forum_threads SET views = views + 1 WHERE threadID = $threadID");

$posts_res = safe_query("SELECT p.*, u.username FROM plugins_forum_posts p LEFT JOIN users u ON p.userID = u.userID WHERE p.threadID = $threadID ORDER BY p.created_at ASC");

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title><?=htmlspecialchars($thread['title'])?></title>
    <link rel="stylesheet" href="../../../themes/default/css/style.css" />
</head>
<body>
    <h1><?=htmlspecialchars($thread['title'])?></h1>

    <a href="index.php">Zurück zur Übersicht</a>

    <div class="posts">
        <?php while ($post = mysqli_fetch_assoc($posts_res)): ?>
            <div class="post">
                <div class="post-author"><?=htmlspecialchars($post['username'] ?? 'Gast')?></div>
                <div class="post-date"><?=date('d.m.Y H:i', $post['created_at'])?></div>
                <div class="post-content"><?=nl2br(htmlspecialchars($post['content']))?></div>
            </div>
        <?php endwhile; ?>
    </div>

    <h2>Antwort schreiben</h2>
    <form method="post" action="post_reply.php">
        <input type="hidden" name="threadID" value="<?=$threadID?>" />
        <textarea name="content" rows="5" cols="80" required></textarea><br/>
        <button type="submit">Absenden</button>
    </form>

</body>
</html>
