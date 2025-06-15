<?php
#require_once '../../../system/core.php';
#require_once '../../../system/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $_database;

$userID = $_SESSION['userID'] ?? 0;

$action = $_GET['action'] ?? 'overview';

function getCategories() {
    $res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
    $categories = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $categories[] = $row;
    }
    return $categories;
}

function getThreadsByCategory($catID) {
    $res = safe_query("SELECT * FROM plugins_forum_threads WHERE catID = " . intval($catID) . " ORDER BY updated_at DESC");
    $threads = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $threads[] = $row;
    }
    return $threads;
}

switch ($action) {
    case 'thread':
        $threadID = intval($_GET['id'] ?? 0);
        if ($threadID <= 0) die("Ungültiger Thread.");

        $thread_res = safe_query("SELECT * FROM plugins_forum_threads WHERE threadID = $threadID");
        if (mysqli_num_rows($thread_res) === 0) die("Thread nicht gefunden.");
        $thread = mysqli_fetch_assoc($thread_res);

        safe_query("UPDATE plugins_forum_threads SET views = views + 1 WHERE threadID = $threadID");

        $posts_res = safe_query("
            SELECT p.*, u.username 
            FROM plugins_forum_posts p 
            LEFT JOIN users u ON p.userID = u.userID 
            WHERE p.threadID = $threadID 
            ORDER BY p.created_at ASC
        ");
        ?>
        <h1><?= htmlspecialchars($thread['title']) ?></h1>
        <a href="index.php?site=forum">Zurück zur Übersicht</a>

        <div class="posts my-4">
            <?php while ($post = mysqli_fetch_assoc($posts_res)): ?>
                <div class="post border rounded p-3 mb-3">
                    <div><strong><?= htmlspecialchars($post['username'] ?? 'Gast') ?></strong> schrieb am <?= date('d.m.Y H:i', $post['created_at']) ?>:</div>
                    <div><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($userID): ?>
            <h2>Antwort schreiben</h2>
            <form method="post" action="index.php?site=forum&action=reply">
                <input type="hidden" name="threadID" value="<?= $threadID ?>" />
                <textarea name="content" rows="5" cols="80" required></textarea><br/>
                <button type="submit">Absenden</button>
            </form>
        <?php else: ?>
            <p><em>Zum Antworten bitte einloggen.</em></p>
        <?php endif;
        break;

    case 'reply':
        if (!$userID) die("Bitte einloggen.");
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $threadID = intval($_POST['threadID'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            if ($threadID <= 0 || $content === '') die("Ungültige Eingaben.");

            $created_at = time();
            $content = mysqli_real_escape_string($_database, $content);

            safe_query("INSERT INTO plugins_forum_posts (threadID, userID, content, created_at) VALUES ($threadID, $userID, '$content', $created_at)");
            safe_query("UPDATE plugins_forum_threads SET updated_at = $created_at WHERE threadID = $threadID");

            header("Location: index.php?site=forum&action=thread&id=$threadID");
            exit;
        }
        header("Location: index.php?site=forum");
        exit;

    case 'new_thread':
        $catID = intval($_GET['catID'] ?? 0);
        if (!$userID) die("Bitte einloggen.");
        if ($catID <= 0) die("Ungültige Kategorie.");

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title === '' || $content === '') die("Bitte Titel und Inhalt eingeben.");

            $created_at = time();
            $title = mysqli_real_escape_string($_database, $title);
            $content = mysqli_real_escape_string($_database, $content);

            safe_query("INSERT INTO plugins_forum_threads (catID, userID, title, created_at, updated_at) VALUES ($catID, $userID, '$title', $created_at, $created_at)");
            $threadID = mysqli_insert_id($_database);
            safe_query("INSERT INTO plugins_forum_posts (threadID, userID, content, created_at) VALUES ($threadID, $userID, '$content', $created_at)");

            header("Location: index.php?site=forum&action=thread&id=$threadID");
            exit;
        }
        ?>
        <h1>Neues Thema erstellen</h1>
        <form method="post" action="">
            <label for="title">Titel:</label><br/>
            <input type="text" id="title" name="title" required /><br/><br/>

            <label for="content">Inhalt:</label><br/>
            <textarea id="content" name="content" rows="8" cols="80" required></textarea><br/><br/>

            <button type="submit">Thema erstellen</button>
        </form>
        <p><a href="index.php?site=forum">Zurück zur Übersicht</a></p>
        <?php
        break;

    case 'overview':
    default:
        $categories = getCategories(); ?>
        <div class="container py-4">
            <h1 class="mb-4">Forum Übersicht</h1>

            <?php foreach ($categories as $category): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0"><?= htmlspecialchars($category['title']) ?></h2>
                        <?php if ($userID): ?>
                            <a href="index.php?site=forum&action=new_thread&catID=<?= intval($category['catID']) ?>" class="btn btn-sm btn-primary">
                                Neues Thema erstellen
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?= nl2br(htmlspecialchars($category['description'])) ?></p>
                        <?php 
                        $threads = getThreadsByCategory($category['catID']);
                        if (count($threads) === 0): ?>
                            <div class="alert alert-info mb-0" role="alert">
                                Keine Themen in dieser Kategorie.
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($threads as $thread): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="index.php?site=forum&action=thread&id=<?= intval($thread['threadID']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($thread['title']) ?>
                                        </a>
                                        <span class="badge bg-secondary rounded-pill"><?= intval($thread['views']) ?> Aufrufe</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        break;
}
?>
