<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('forum');

$per_Page = 10;



$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Forum'
];

echo $tpl->loadTemplate("forum", "head", $data_array, 'plugin');

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

function getThreadsByCategory($catID): array {
    global $_database;

    $catID = intval($catID);
    $threads = [];

    $result = safe_query("
        SELECT 
            t.*, 
            COUNT(p.postID) AS replies,
            MAX(p.created_at) AS last_post_time,
            MAX(p.userID) AS last_userID
        FROM plugins_forum_threads t
        LEFT JOIN plugins_forum_posts p ON t.threadID = p.threadID
        WHERE t.catID = $catID
        GROUP BY t.threadID
        ORDER BY t.created_at DESC
    ");

    while ($row = mysqli_fetch_assoc($result)) {
        // Letzten Usernamen holen
        $uid = intval($row['last_userID']);
        $userRes = safe_query("SELECT username FROM users WHERE userID = $uid");
        $user = mysqli_fetch_assoc($userRes);
        $row['last_username'] = $user['username'] ?? 'Unbekannt';

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

    // Pagination vorbereiten
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = $per_Page;
    $offset = ($page - 1) * $perPage;

    // Anzahl Beiträge zählen
    $count_res = safe_query("SELECT COUNT(*) as total FROM plugins_forum_posts WHERE threadID = $threadID");
    $total_posts = mysqli_fetch_assoc($count_res)['total'];
    $total_pages = ceil($total_posts / $perPage);

    // Beiträge mit LIMIT laden
    $posts_res = safe_query("
        SELECT p.*, u.username, u.avatar 
        FROM plugins_forum_posts p 
        LEFT JOIN users u ON p.userID = u.userID 
        WHERE p.threadID = $threadID 
        ORDER BY p.created_at ASC 
        LIMIT $offset, $perPage
    ");
    ?>

    <div class="card">
        <div class="card-header">
            <h4><?= htmlspecialchars($thread['title']) ?></h4>
        </div>
        <div class="card-body">
            <div class="posts my-4">
                <?php while ($post = mysqli_fetch_assoc($posts_res)): ?>
                    <?php
                        $avatar_url = !empty($post['avatar']) ? $post['avatar'] : 'noavatar.png';
                        $is_owner = $post['userID'] == $userID;
                    ?>
                    <div class="card border rounded p-3 mb-4">
                        <div class="row">
                            <div class="col-md-2 text-center border-end border-primary pe-3">
                                <div class="mb-2">
                                    <img src="/images/avatars/<?= htmlspecialchars($avatar_url) ?>" class="img-fluid" alt="Avatar" style="max-width: 80px;">
                                </div>
                                <strong><?= htmlspecialchars($post['username'] ?? 'Gast') ?></strong><br>
                                <small><?= (int)($post['posts'] ?? 0) ?> Beiträge</small>
                            </div>
                            <div class="col-md-10">
                                <div class="d-flex justify-content-between">
                                    <div><i class="bi bi-calendar-plus"></i> <?= date('d.m.Y H:i', $post['created_at']) ?></div>
                                    <div>
                                        <a href="index.php?site=forum&action=quote&postID=<?= $post['postID'] ?>&threadID=<?= $threadID ?>" class="btn btn-sm btn-outline-secondary me-2">Zitieren</a>
                                        <?php if ($is_owner): ?>
                                            <a href="index.php?site=forum&action=edit&postID=<?= $post['postID'] ?>&threadID=<?= $threadID ?>" class="btn btn-sm btn-outline-primary">Bearbeiten</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-2"><?= $post['content'] ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?site=forum&action=thread&id=<?= $threadID ?>&page=<?= ($page - 1) ?>">« Zurück</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?site=forum&action=thread&id=<?= $threadID ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?site=forum&action=thread&id=<?= $threadID ?>&page=<?= ($page + 1) ?>">Weiter »</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <!-- Nur auf letzter Seite anzeigen -->
            

            <?php if ($userID): ?>
                <h4>Antwort schreiben</h4>
                <form method="post" action="index.php?site=forum&action=reply">
                    <input type="hidden" name="threadID" value="<?= $threadID ?>" />
                    <textarea class="ckeditor form-control" name="content" rows="5" required><?= htmlspecialchars($_SESSION['quote_content'] ?? '') ?></textarea><br/>
                    <button class="btn btn-success" type="submit">Absenden</button>
                </form>
                <?php unset($_SESSION['quote_content']); ?>
            <?php else: ?>
                <p><em>Zum Antworten bitte einloggen.</em></p>
            <?php endif; ?>
        </div>
    </div>
    <a href="index.php?site=forum">Zurück zur Übersicht</a>
    <?php
    break;


    case 'reply':
    if (!$userID) die("Bitte einloggen.");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $threadID = intval($_POST['threadID'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($threadID <= 0 || $content === '') die("Ungültige Eingaben.");

        $created_at = time();
        $escaped_content = $content;

        safe_query("INSERT INTO plugins_forum_posts (threadID, userID, content, created_at) VALUES ($threadID, $userID, '$escaped_content', $created_at)");
        safe_query("UPDATE plugins_forum_threads SET updated_at = $created_at WHERE threadID = $threadID");

        // 🔁 Richtige Seitenanzahl berechnen (bei 10 Beiträgen pro Seite)
        $total_res = safe_query("SELECT COUNT(*) as total FROM plugins_forum_posts WHERE threadID = $threadID");
        $total = mysqli_fetch_assoc($total_res)['total'];
        $page = ceil($total / $per_Page);

        header("Location: index.php?site=forum&action=thread&id=$threadID&page=$page");
        exit;
    }
    break;


    case 'edit':
    $postID = intval($_GET['postID'] ?? 0);
    $threadID = intval($_GET['threadID'] ?? 0);
    if (!$userID || $postID <= 0) die("Ungültiger Zugriff.");

    $res = safe_query("SELECT * FROM plugins_forum_posts WHERE postID = $postID");
    $post = mysqli_fetch_assoc($res);
    if (!$post || $post['userID'] != $userID) die("Keine Berechtigung.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = trim($_POST['content'] ?? '');
        if ($content === '') die("Inhalt darf nicht leer sein.");
        $escaped_content = $content; // besser: mysqli_real_escape_string verwenden

        safe_query("UPDATE plugins_forum_posts SET content = '$escaped_content' WHERE postID = $postID");

        // 🔍 Position des bearbeiteten Posts im Thread ermitteln (nach created_at sortiert)
        $order_res = safe_query("SELECT postID FROM plugins_forum_posts WHERE threadID = $threadID ORDER BY created_at ASC");
        $position = 1;
        while ($row = mysqli_fetch_assoc($order_res)) {
            if ($row['postID'] == $postID) break;
            $position++;
        }

        // 📄 Seite berechnen (10 Beiträge pro Seite)
        $page = ceil($position / $per_Page);

        header("Location: index.php?site=forum&action=thread&id=$threadID&page=$page");
        exit;
    }
    #break;


        ?>

        <div class="card">
            <div class="card-header"><h4>Beitrag bearbeiten</h4></div>
            <div class="card-body">
                <form method="post">
                    <textarea class="ckeditor form-control" name="content" rows="6"><?= htmlspecialchars($post['content']) ?></textarea><br/>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                    <a href="index.php?site=forum&action=thread&id=<?= $threadID ?>" class="btn btn-secondary">Abbrechen</a>
                </form>
            </div>
        </div>
        <?php
        break;

    case 'quote':
        $postID = intval($_GET['postID'] ?? 0);
        $threadID = intval($_GET['threadID'] ?? 0);
        if ($postID <= 0 || $threadID <= 0) die("Ungültige Anfrage.");

        $res = safe_query("SELECT p.content, u.username FROM plugins_forum_posts p LEFT JOIN users u ON p.userID = u.userID WHERE postID = $postID");
        if (mysqli_num_rows($res) > 0) {
            $post = mysqli_fetch_assoc($res);
            #$quote = '<blockquote><strong>' . htmlspecialchars($post['username']) . ' schrieb:</strong><br>' 
       #. nl2br(htmlspecialchars($post['content'])) . '</blockquote><p><br></p>';

       $quote = '<blockquote class="blockquote p-3 mb-3 bg-light border-start border-primary">
  <footer class="blockquote-footer">' . htmlspecialchars($post['username']) . ' schrieb:</footer>
  <div>' . nl2br(htmlspecialchars($post['content'])) . '</div>
</blockquote><br><br>';


            $_SESSION['quote_content'] = $quote;
        }

        // 🔍 Position des bearbeiteten Posts im Thread ermitteln (nach created_at sortiert)
        $order_res = safe_query("SELECT postID FROM plugins_forum_posts WHERE threadID = $threadID ORDER BY created_at ASC");
        $position = 1;
        while ($row = mysqli_fetch_assoc($order_res)) {
            if ($row['postID'] == $postID) break;
            $position++;
        }

        // 📄 Seite berechnen (10 Beiträge pro Seite)
        $page = ceil($position / $per_Page);

        header("Location: index.php?site=forum&action=thread&id=$threadID&page=$page");
        exit;

    case 'new_thread':
        $catID = intval($_GET['catID'] ?? 0);
        if (!$userID || $catID <= 0) die("Nicht erlaubt.");

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = $_POST['content'];
            if ($title === '' || $content === '') die("Titel und Inhalt erforderlich.");

            $created_at = time();
            $title = mysqli_real_escape_string($_database, $title);
            $content = $content;

            safe_query("INSERT INTO plugins_forum_threads (catID, userID, title, created_at, updated_at) VALUES ($catID, $userID, '$title', $created_at, $created_at)");
            $threadID = mysqli_insert_id($_database);
            safe_query("INSERT INTO plugins_forum_posts (threadID, userID, content, created_at) VALUES ($threadID, $userID, '$content', $created_at)");

            header("Location: index.php?site=forum&action=thread&id=$threadID");
            exit;
        }
        ?>

        <div class="card">
            <div class="card-header">
                <h4>Neues Thema erstellen</h4>
            </div>
            <div class="card-body">
                <form method="post">
                    <label for="title">Titel:</label>
                    <input class="form-control" id="title" name="title" required><br/>

                    <label for="content">Inhalt:</label>
                    <textarea class="form-control" id="content" name="content" rows="8" required></textarea><br/>

                    <button class="btn btn-success" type="submit">Thema erstellen</button>
                </form>
            </div>
        </div>
        <a href="index.php?site=forum">Zurück zur Übersicht</a>
        <?php
        break;

    case 'overview':
default:
    $categories = getCategories();

    // Funktion, um Threads mit Infos zum letzten Post anzureichern
    function enrichThreadsWithLastPost(array $threads): array {
        foreach ($threads as &$thread) {
            $threadID = intval($thread['threadID']);

            // Letzten Post mit Username holen
            $res = safe_query("
                SELECT p.postID, p.created_at, u.username 
                FROM plugins_forum_posts p
                LEFT JOIN users u ON p.userID = u.userID
                WHERE p.threadID = $threadID
                ORDER BY p.created_at DESC
                LIMIT 1
            ");
            $lastPost = mysqli_fetch_assoc($res);

            if ($lastPost) {
                $thread['last_post_time'] = $lastPost['created_at'];
                $thread['last_username'] = $lastPost['username'] ?? 'Unbekannt';
                $thread['last_post_id'] = $lastPost['postID'];

                // Seite des letzten Posts berechnen (10 Posts pro Seite)
                $resPos = safe_query("
                    SELECT COUNT(*) AS pos 
                    FROM plugins_forum_posts 
                    WHERE threadID = $threadID 
                      AND created_at <= (SELECT created_at FROM plugins_forum_posts WHERE postID = {$lastPost['postID']})
                ");
                $posRow = mysqli_fetch_assoc($resPos);
                $pos = $posRow['pos'] ?? 1;
                $thread['last_post_page'] = ceil($pos / 10);
            } else {
                // Kein letzter Post vorhanden
                $thread['last_post_time'] = 0;
                $thread['last_username'] = 'Keine Beiträge';
                $thread['last_post_id'] = 0;
                $thread['last_post_page'] = 1;
            }
        }
        return $threads;
    }
    ?>
    <div class="container py-4">
        <h4 class="mb-4">Forum Übersicht</h4>

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
                    <p><?= htmlspecialchars($category['description']) ?></p>
                    <?php
                    $threads = getThreadsByCategory($category['catID']);
                    $threads = enrichThreadsWithLastPost($threads);
                    ?>
                    <?php if (empty($threads)): ?>
                        <div class="alert alert-info">Keine Themen in dieser Kategorie.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                        <?php foreach ($threads as $thread): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="index.php?site=forum&action=thread&id=<?= intval($thread['threadID']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($thread['title']) ?>
                                    </a>
                                    <div class="text-end">
                                        <span class="badge bg-info"><?= intval($thread['replies']) ?> Antworten</span>
                                        <span class="badge bg-secondary"><?= intval($thread['views']) ?> Aufrufe</span><br>

                                        <small class="text-muted">
                                            Letzter Beitrag:
                                            <?php if ($thread['last_post_id'] > 0): ?>
                                                <a href="index.php?site=forum&action=thread&id=<?= intval($thread['threadID']) ?>&page=<?= $thread['last_post_page'] ?>#post<?= $thread['last_post_id'] ?>">
                                                    <?= date('d.m.Y H:i', $thread['last_post_time']) ?>
                                                    von <?= htmlspecialchars($thread['last_username']) ?>
                                                </a>
                                            <?php else: ?>
                                                Keine Beiträge
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
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
