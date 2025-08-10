<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

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
$action = $_GET['action'] ?? 'board';


function getBoards(): array {
    $boards = [];
    $res = safe_query("SELECT * FROM plugins_forum_boards ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        $boards[] = $row;
    }
    return $boards;
}


function getCategories() {
    $res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
    $categories = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $categories[] = $row;
    }
    return $categories;
}

function getThreadsByCategory(int $catID): array {
    $threads = [];
    $res = safe_query("
        SELECT t.*, 
               (SELECT COUNT(*) FROM plugins_forum_posts p WHERE p.threadID = t.threadID) - 1 AS replies
        FROM plugins_forum_threads t
        WHERE t.catID = $catID
        ORDER BY t.updated_at DESC
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        // Falls keine Antworten, setze replies auf 0 (damit keine -1 entsteht)
        $row['replies'] = max(0, intval($row['replies']));
        $threads[] = $row;
    }
    return $threads;
}

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
            $thread['last_post_time'] = 0;
            $thread['last_username'] = 'Keine Beiträge';
            $thread['last_post_id'] = 0;
            $thread['last_post_page'] = 1;
        }
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

    // Views erhöhen
    safe_query("UPDATE plugins_forum_threads SET views = views + 1 WHERE threadID = $threadID");

    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = $per_Page ?? 10;
    $offset = ($page - 1) * $perPage;

    // Beiträge zählen (nur in diesem Thread)
    $count_res = safe_query("SELECT COUNT(*) as total FROM plugins_forum_posts WHERE threadID = $threadID");
    $total_posts = mysqli_fetch_assoc($count_res)['total'];
    $total_pages = ceil($total_posts / $perPage);

    // Beiträge abrufen
    $posts_res = safe_query("
    SELECT p.*, u.username, up.avatar, up.signatur
    FROM plugins_forum_posts p
    LEFT JOIN users u ON p.userID = u.userID
    LEFT JOIN user_profiles up ON p.userID = up.userID
    WHERE p.threadID = $threadID
    ORDER BY p.created_at ASC
    LIMIT $offset, $perPage
");

    // Rollen vorbereiten
    // Die roleIDs, die erlaubt sind
    $rolePriorityGroup = [1, 3];  // Priorität 1: Rolle 1 oder 3
    $fallbackRole = 12;           // Priorität 2: Rolle 12, wenn keine 1 oder 3

    $resRoleNames = safe_query("SELECT roleID, role_name FROM user_roles");
    $roleMap = [];
    while ($row = mysqli_fetch_assoc($resRoleNames)) {
        $roleMap[(int)$row['roleID']] = $row['role_name'];
    }

    $rolesByUserRaw = [];
    $resRoleRel = safe_query("SELECT userID, roleID FROM user_role_assignments");
    while ($row = mysqli_fetch_assoc($resRoleRel)) {
        $rolesByUserRaw[(int)$row['userID']][] = (int)$row['roleID'];
    }

    $rolesByUser = [];
    foreach ($rolesByUserRaw as $userID => $roleIDs) {
        // Rollen in Prioritätsgruppe filtern
        $priorityRoles = array_intersect($roleIDs, $rolePriorityGroup);

        if (!empty($priorityRoles)) {
            // Wenn Rolle 1 oder 3 vorhanden: nur diese nehmen
            $rolesByUser[$userID] = $priorityRoles;
        } elseif (in_array($fallbackRole, $roleIDs)) {
            // Sonst, wenn Rolle 12 vorhanden: nur diese nehmen
            $rolesByUser[$userID] = [$fallbackRole];
        }
        // Sonst wird für den User keine Rolle gesetzt (keine Anzeige)
    }

    // Funktion zur Punkteermittlung für einen Nutzer
    function getUserCount($table, $col, $userID) {
        global $_database;
        if (!tableExists($table)) return 0;
        $stmt = $_database->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count;
    }

    function getPointsForUser($userID) {
        $articles  = getUserCount('plugins_articles', 'userID', $userID);
        $comments  = getUserCount('comments', 'userID', $userID);
        $rules     = getUserCount('plugins_rules', 'userID', $userID);
        $links     = getUserCount('plugins_links', 'userID', $userID);
        $partners  = getUserCount('plugins_partners', 'userID', $userID);
        $sponsors  = getUserCount('plugins_sponsors', 'userID', $userID);
        $forum     = getUserCount('plugins_forum_posts', 'userID', $userID);
        $download  = getUserCount('plugins_downloads_logs', 'userID', $userID);

        global $_database;
        $stmt = $_database->prepare("SELECT COUNT(*) FROM user_sessions WHERE userID = ?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->bind_result($logins);
        $stmt->fetch();
        $stmt->close();

        return ($articles * 10) + ($comments * 2) + ($rules * 5) + ($links * 5) + ($partners * 5) +
               ($sponsors * 5) + ($forum * 2) + ($download * 2) + ($logins * 2);
    }

    function getRoleName($roleID, $roleMap) {
        return $roleMap[$roleID] ?? 'Unbekannt';
    }

    $category = null;
    $board = null;

    if (!empty($thread['catID'])) {
        $catID = (int) $thread['catID'];
        $catRes = safe_query("SELECT * FROM plugins_forum_categories WHERE catID = $catID");
        if (mysqli_num_rows($catRes)) {
            $category = mysqli_fetch_assoc($catRes);

            if (!empty($category['group_id'])) {
                $boardID = (int) $category['group_id'];
                $boardRes = safe_query("SELECT * FROM plugins_forum_boards WHERE id = $boardID");
                if (mysqli_num_rows($boardRes)) {
                    $board = mysqli_fetch_assoc($boardRes);
                }
            }
        }
    }

    function userLikedPost(int $postID, int $userID): bool {
        global $_database;
        $postID = intval($postID);
        $userID = intval($userID);
        if ($userID <= 0) return false; // z. B. nicht eingeloggt

        $res = safe_query("SELECT 1 FROM plugins_forum_likes WHERE postID = $postID AND userID = $userID LIMIT 1");
        return mysqli_num_rows($res) > 0;
    }

    function getLikeCount(int $postID): int {
        global $_database;
        $postID = intval($postID);

        $res = safe_query("SELECT COUNT(*) AS cnt FROM plugins_forum_likes WHERE postID = $postID");
        $row = mysqli_fetch_assoc($res);
        return (int)($row['cnt'] ?? 0);
    }

        ?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum')) ?>">Forum</a>
    </li>

    <?php if (!empty($board) && !empty($board['id'])): ?>
    <li class="breadcrumb-item">
      <?php $urlString = 'index.php?site=forum&action=overview&id=' . intval($board['id']); ?>
      <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($urlString)) ?>">
          <?= htmlspecialchars($board['title']) ?>
      </a>
    </li>
    <?php else: ?>
    <li class="breadcrumb-item">Kein Board ausgewählt</li>
    <?php endif; ?>

    <?php if (!empty($category) && !empty($category['catID'])): ?>
    <li class="breadcrumb-item">
      <?php #$urlString = 'index.php?site=forum&action=category&id=' . intval($category['catID']); ?>
      <?php $urlString = 'index.php?site=forum&action=category&id=' . (isset($category['catID']) ? intval($category['catID']) : 0); ?>
      <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($urlString)) ?>">
          <?= htmlspecialchars($category['title']) ?>
      </a>
    </li>
    <?php else: ?>
    <li class="breadcrumb-item">Keine Kategorie ausgewählt</li>
    <?php endif; ?>

    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($thread['title']) ?></li>
  </ol>
</nav>


<div class="card">
    <div class="card-header">
        <h4><?= htmlspecialchars($thread['title']) ?></h4>
    </div>
    <div class="card-body">
        <div class="posts my-4">
            <?php while ($post = mysqli_fetch_assoc($posts_res)):
                $uid = (int)$post['userID'];
                $username = htmlspecialchars($post['username'] ?? 'Gast');
                $avatar = getavatar($uid) ?: 'default.png';
                $points = getPointsForUser($uid);
                $user_roles = $rolesByUser[$uid] ?? [];
            ?>
                <div class="card shadow-sm border rounded p-3 mb-4" id="post<?php echo intval($post['postID']); ?>">
                    <div class="row">
                        <div class="col-md-2 text-center border-end border-primary pe-3">
                            <img src="<?= htmlspecialchars($avatar) ?>" class="img-fluid mb-2" style="max-width: 80px;"><br>
                            <strong><?= $username ?></strong><br>
                            <small><?= getUserCount('plugins_forum_posts', 'userID', $uid) ?> Beiträge</small><br>
                            <div class="badge bg-primary mt-2">
                                <i class="bi bi-star-fill me-1"></i><?= $points ?> Punkte
                            </div>
                            <p class="text-muted mt-2">

                                <?php 
                                $currentUserID = (int)$post['userID']; // aktuelle User-ID einsetzen (z. B. aus Session)

                                $badgeClasses = [
                                    1 => 'badge bg-danger',      // Admin (rot)
                                    3 => 'badge bg-info',        // Moderator (blau)
                                    12 => 'badge bg-secondary',  // User (grau)
                                ];

                                if (!empty($rolesByUser[$currentUserID])) {
                                    $userRoleIDs = $rolesByUser[$currentUserID];

                                    // Nach Priorität sortieren: 1 (höchste), dann 3, dann 12
                                    $priorityRoles = [1, 3, 12];
                                    $selectedRoleID = null;

                                    foreach ($priorityRoles as $prioRoleID) {
                                        if (in_array($prioRoleID, $userRoleIDs)) {
                                            $selectedRoleID = $prioRoleID;
                                            break;
                                        }
                                    }

                                    if ($selectedRoleID !== null) {
                                        $roleName = htmlspecialchars($roleMap[$selectedRoleID] ?? 'Unbekannt');
                                        $badgeClass = $badgeClasses[$selectedRoleID] ?? 'badge bg-light';
                                        echo "<span class=\"$badgeClass me-1\">$roleName</span>";
                                    } else {
                                        echo '<span class="badge bg-warning">Keine Rolle</span>';
                                    }
                                } else {
                                    echo '<span class="badge bg-warning">Keine Rolle</span>';
                                }
                                ?>

                            </p>
                        </div>
                        <div class="col-md-10">
                            <div class="d-flex justify-content-between">
                                <div><i class="bi bi-calendar-plus"></i> <?= date('d.m.Y H:i', $post['created_at']) ?></div>
                                    <div>
                                        <?php
                                        $currentUserID = $_SESSION['userID'] ?? 0;
                                        $userRoles = [];

                                        if ($currentUserID > 0) {
                                            $currentUserID = (int)$currentUserID;
                                            $res = safe_query("
                                                SELECT ur.role_name
                                                FROM user_role_assignments ura
                                                JOIN user_roles ur ON ura.roleID = ur.roleID
                                                WHERE ura.userID = $currentUserID
                                            ");

                                            while ($row = mysqli_fetch_assoc($res)) {
                                                $userRoles[] = $row['role_name'];
                                            }
                                        }

                                        $uid = $post['userID'] ?? 0;
                                        $mayEdit = ($uid == $currentUserID) || in_array('Admin', $userRoles) || in_array('Moderator', $userRoles);
                                        ?>

                                        <?php if (isset($_SESSION['userID'])): ?>
                                            <?php
                                            $urlString = 'index.php?site=forum&action=quote&postID=' . intval($post['postID']) . '&threadID=' . intval($threadID);
                                            ?>
                                            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($urlString)) ?>" class="btn btn-sm btn-outline-secondary">Zitieren</a>
                                        <?php endif; ?>

                                        <?php if ($mayEdit): ?>
                                            <?php
                                            $urlString = 'index.php?site=forum&action=edit&postID=' . intval($post['postID']) . '&threadID=' . intval($threadID);
                                            ?>
                                            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($urlString)) ?>" class="btn btn-sm btn-outline-primary">Bearbeiten</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr>
                                <div><?= $post['content'] ?></div>
                                <div class="mt-4 pt-2 border-top text-muted" style="font-size: 0.9em;">
                                    <?= $post['signatur'] ?>
                                </div>
                                <div class="mt-3 text-end">
                                <?php 
                                    $postID = $post['postID'];
                                    $userID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;

                                    // Like-Anzahl immer holen – egal ob eingeloggt oder nicht
                                    $likeCount = getLikeCount($postID);

                                    if ($userID > 0) {
                                        $liked = userLikedPost($postID, $userID);
                                        ?>
                                        <button class="btn btn-outline-primary btn-sm like-btn" 
                                                data-postid="<?= $postID ?>" 
                                                data-liked="<?= $liked ? '1' : '0' ?>">
                                            <?= $liked ? 'Unlike' : 'Like' ?>
                                        </button>
                                    <?php 
                                    } else {
                                        // Nicht eingeloggt → Nur Text
                                        echo '<span class="text-muted">Like</span>';
                                    }
                                ?>
                                <span class="like-count ms-2"><?= $likeCount ?></span>
                                </div>
                                <?php if (!empty($post['signatur'])): ?> 
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                  const hash = window.location.hash;
                  if (hash.startsWith("#post")) {
                    const target = document.querySelector(hash);
                    if (target) {
                      target.classList.add("highlight-post");

                      // optional wieder entfernen nach 4 Sekunden
                      setTimeout(() => {
                        target.classList.remove("highlight-post");
                      }, 4000);
                    }
                  }
                });
            </script>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                        <?php
                        $url = "index.php?site=forum&action=thread&id=$threadID&page=$i";
                        ?>
                        <a class="page-link" href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <!-- Antwortformular -->
        <?php if (isset($_SESSION['userID'])): ?>
            <h4>Antwort schreiben</h4>
            <form method="post" action="index.php?site=forum&action=reply" id="replyform">
                <input type="hidden" name="threadID" value="<?= $threadID ?>" />
                <textarea id="ckeditor" name="content" class="ckeditor form-control" rows="6" style="resize: vertical; width: 100%;" required><?= $_SESSION['quote_content'] ?? '' ?></textarea>

                <div id="dropArea" class="mt-2 p-4 text-center border border-secondary rounded bg-light" style="cursor: pointer;">
                    📎 Hier klicken oder Bild per Drag & Drop einfügen
                </div>
                <input type="file" id="uploadImage" accept="image/*" style="display: none;"><br/>
                <button class="btn btn-success" type="submit">Absenden</button>
            </form>
            <?php unset($_SESSION['quote_content']); ?>
        <?php else: ?>
            <p><em>Zum Antworten bitte einloggen.</em></p>
        <?php endif; ?>
    </div>
</div>
<?php
$url = "index.php?site=forum";
?>
<a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)) ?>">Zurück zur Übersicht</a>


<script>
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const postID = btn.dataset.postid;
        const liked = btn.dataset.liked === '1';
        const action = liked ? 'unlike' : 'like';

        fetch('/includes/plugins/forum/like_post_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `postID=${postID}&action=${action}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                btn.dataset.liked = liked ? '0' : '1';
                btn.textContent = liked ? 'Like' : 'Unlike';
                btn.nextElementSibling.textContent = data.likes;
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(() => alert('Netzwerkfehler'));
    });
});
</script>
<?php break;


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

        $url = "index.php?site=forum&action=thread&id=$threadID&page=$page";
        $seoUrl = SeoUrlHandler::convertToSeoUrl($url);
        header("Location: " . $seoUrl);
        exit;
    }
    break;




    case 'edit':
    $userID = $_SESSION['userID'] ?? 0;
    $userRoles = [];

    if ($userID > 0) {
        $userID = (int)$userID;
        $resRoles = safe_query("
            SELECT ur.role_name
            FROM user_role_assignments ura
            JOIN user_roles ur ON ura.roleID = ur.roleID
            WHERE ura.userID = $userID
        ");
        while ($row = mysqli_fetch_assoc($resRoles)) {
            $userRoles[] = $row['role_name'];
        }
    }

    $postID = intval($_GET['postID'] ?? 0);
    $threadID = intval($_GET['threadID'] ?? 0);
    if (!$userID || $postID <= 0) die("Ungültiger Zugriff.");

    $res = safe_query("SELECT * FROM plugins_forum_posts WHERE postID = $postID");
    $post = mysqli_fetch_assoc($res);
    if (!$post) die("Beitrag nicht gefunden.");

    // Berechtigung prüfen: Autor ODER Admin/Moderator
    $mayEdit = ($post['userID'] == $userID) || in_array('Admin', $userRoles) || in_array('Moderator', $userRoles);
    if (!$mayEdit) die("Keine Berechtigung.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = trim($_POST['content'] ?? '');
        if ($content === '') die("Inhalt darf nicht leer sein.");

        // Alte Bilder extrahieren
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $post['content'], $oldMatches);
        $oldImages = $oldMatches[1] ?? [];

        // Neue Bilder extrahieren
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $content, $newMatches);
        $newImages = $newMatches[1] ?? [];

        // Alte Bilder, die nicht mehr verwendet werden
        $deletedImages = array_diff($oldImages, $newImages);

        foreach ($deletedImages as $filename) {
            $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/forum/uploads/forum_images/' . basename($filename);

            error_log("Lösche Bild: $imagePath");
            if (file_exists($imagePath)) {
                error_log("Datei existiert, versuche zu löschen...");
                if (unlink($imagePath)) {
                    error_log("Datei gelöscht.");
                } else {
                    error_log("Datei konnte nicht gelöscht werden.");
                }
            } else {
                error_log("Datei existiert NICHT: $imagePath");
            }
        }

        // Inhalt speichern (hier solltest du mysqli_real_escape_string oder Prepared Statements nutzen)
        $escaped_content = $content;

        safe_query("UPDATE plugins_forum_posts SET content = '$escaped_content' WHERE postID = $postID");

        // Position des Posts im Thread finden
        $order_res = safe_query("SELECT postID FROM plugins_forum_posts WHERE threadID = $threadID ORDER BY created_at ASC");
        $position = 1;
        while ($row = mysqli_fetch_assoc($order_res)) {
            if ($row['postID'] == $postID) break;
            $position++;
        }

        $per_Page = 10; // Falls $per_Page nicht definiert ist, sonst entfernen
        $page = ceil($position / $per_Page);

        $url = "index.php?site=forum&action=thread&id=$threadID&pagenr=$page";
        $seoUrl = SeoUrlHandler::convertToSeoUrl($url);
        header("Location: " . $seoUrl);
        exit;
    }

    // Thread laden, um catID zu bekommen
    $threadRes = safe_query("SELECT * FROM plugins_forum_threads WHERE threadID = " . intval($post['threadID']));
    $thread = mysqli_fetch_assoc($threadRes);
    $catID = intval($thread['catID'] ?? 0);

    // Kategorie laden
    $category = null;
    if ($catID > 0) {
        $categoryRes = safe_query("SELECT * FROM plugins_forum_categories WHERE catID = $catID");
        $category = mysqli_fetch_assoc($categoryRes);
    }

    // Board laden
    $board = null;
    if (!empty($category['group_id'])) {
        $boardRes = safe_query("SELECT * FROM plugins_forum_boards WHERE id = " . intval($category['group_id']));
        $board = mysqli_fetch_assoc($boardRes);
    }
    ?>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum')) ?>">Forum</a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=overview&id=' . intval($board['id'] ?? 0))) ?>">
                <?= htmlspecialchars($board['title'] ?? 'Unbekanntes Board') ?>
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=category&id=' . intval($category['catID'] ?? 0))) ?>">
                <?= htmlspecialchars($category['title'] ?? 'Unbekannte Kategorie') ?>
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=thread&id=' . intval($thread['threadID'] ?? 0))) ?>">
                <?= htmlspecialchars($thread['title'] ?? 'Unbekannte Kategorie') ?>
            </a>
        </li>

        <li class="breadcrumb-item active" aria-current="page">Beitrag bearbeiten</li>
      </ol>
    </nav>

    <div class="card">
        <div class="card-header"><h4>Beitrag bearbeiten</h4></div>
        <div class="card-body">
            <form method="post">
                <textarea id="ckeditor" name="content" class="ckeditor form-control" rows="6" placeholder="Dein Beitrag..."><?= htmlspecialchars($post['content']) ?></textarea>

                <div id="dropArea" class="mt-2 p-4 text-center border border-secondary rounded bg-light" style="cursor: pointer;">
                  📎 Hier klicken oder Bild per Drag & Drop einfügen
                </div>
                <input type="file" id="uploadImage" accept="image/*" style="display: none;"><br/>

                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=thread&id=' . intval($threadID))) ?>" class="btn btn-secondary">Abbrechen</a>

            </form>
        </div>
    </div>

    <?php
    break;


    case 'quote':
    $lang = $_GET['lang'] ?? 'de';
    $per_Page = 10;

    $threadID = $_GET['threadID'] ?? ($_GET['id'] ?? 0);
    $postID = $_GET['postID'] ?? 0;

    $threadID = (int)$threadID;
    $postID = (int)$postID;

    if ($postID <= 0 || $threadID <= 0) {
        die("Ungültige Anfrage.");
    }

    $res = safe_query("SELECT p.content, u.username 
                       FROM plugins_forum_posts p 
                       LEFT JOIN users u ON p.userID = u.userID 
                       WHERE postID = $postID");

    if (mysqli_num_rows($res) > 0) {
        $post = mysqli_fetch_assoc($res);

        $quote = '<blockquote class="blockquote-primary">'
               . htmlspecialchars($post['username']) . ' schrieb:<br>'
               . htmlspecialchars($post['content']) .
               '</blockquote>';

        $_SESSION['quote_content'] = $quote;
    }

    $order_res = safe_query("SELECT postID FROM plugins_forum_posts WHERE threadID = $threadID ORDER BY created_at ASC");

    $position = 1;
    while ($row = mysqli_fetch_assoc($order_res)) {
        if ($row['postID'] == $postID) break;
        $position++;
    }

    $page = ceil($position / $per_Page);

    if (defined('SEO_URLS') && SEO_URLS) {
        $url = "/$lang/forum/thread/id/$threadID/pagenr/$page#replyform";

        // Nur umwandeln, wenn keine "schon fertige" SEO-URL
        if (!preg_match('#^/[a-z]{2}/#i', $url)) {
            $url = SeoUrlHandler::convertToSeoUrl($url);
        }
    } else {
        $url = "index.php?site=forum&action=thread&id=$threadID&page=$page#replyform";
        $url = SeoUrlHandler::convertToSeoUrl($url); // optional
    }

    header("Location: " . $url);
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

            $url = "index.php?site=forum&action=thread&id=$threadID";
            $seoUrl = SeoUrlHandler::convertToSeoUrl($url);
            header("Location: " . $seoUrl);
            exit;
        }

    // Kategorie laden
    $category = null;
    $board = null;

    $catRes = safe_query("SELECT * FROM plugins_forum_categories WHERE catID = $catID");
    if (mysqli_num_rows($catRes)) {
        $category = mysqli_fetch_assoc($catRes);

        if (!empty($category['group_id'])) {
            $boardID = (int) $category['group_id'];
            $boardRes = safe_query("SELECT * FROM plugins_forum_boards WHERE id = $boardID");
            if (mysqli_num_rows($boardRes)) {
                $board = mysqli_fetch_assoc($boardRes);
            }
        }
    }
    ?>
    
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum')) ?>">Forum</a></li>
            <li class="breadcrumb-item">
                <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=overview&id=' . (isset($board['id']) ? intval($board['id']) : 0))) ?>">
                    <?= htmlspecialchars($board['title'] ?? 'Unbekanntes Board') ?>
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=category&id=' . (isset($category['catID']) ? intval($category['catID']) : 0))) ?>">
                    <?= htmlspecialchars($category['title'] ?? 'Unbekannte Kategorie') ?>
                </a>
            </li>

          </ol>
        </nav>

        <div class="card">
            <div class="card-header">
                <h4>Neues Thema erstellen</h4>
            </div>
            <div class="card-body">
                <form method="post">
                    <label for="title">Titel:</label>
                    <input class="form-control" id="title" name="title" required><br/>

                    <label for="content">Inhalt:</label>
                    <textarea id="ckeditor" name="content" class="ckeditor form-control" rows="6"  required placeholder="Dein Beitrag..."></textarea>

                    <div id="dropArea" class="mt-2 p-4 text-center border border-secondary rounded bg-light" style="cursor: pointer;">
                      📎 Hier klicken oder Bild per Drag & Drop einfügen
                    </div>
                    <input type="file" id="uploadImage" accept="image/*" style="display: none;"><br/>

                    <button class="btn btn-success" type="submit">Thema erstellen</button>
                </form>
            </div>
        </div>
        <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=overview&id=' . intval($board['id']))) ?>" class="btn btn-sm btn-outline-secondary mb-3">
            &larr; Zurück zum Board "<?= htmlspecialchars($board['title']) ?>"
        </a>

        <?php
        break;

    case 'board': // oder default
    $boards = getBoards();

    ?>
    
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Forum</li>
          </ol>
        </nav>

        <h4 class="mb-4">Forum Boards</h4>

        <?php foreach ($boards as $board): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=overview&id=' . intval($board['id']))) ?>">
                            <?= htmlspecialchars($board['title'] ?? 'Unbekanntes Board') ?>
                        </a>

                    </h5>
                    <p class="card-text"><?= nl2br(htmlspecialchars($board['description'] ?? 'Keine Beschreibung')) ?></p>

                    <?php
                    // Kategorien für dieses Board laden
                    $resCats = safe_query("SELECT * FROM plugins_forum_categories WHERE group_id = " . intval($board['id']) . " ORDER BY position ASC");
                    $categories = [];
                    while ($cat = mysqli_fetch_assoc($resCats)) {
                        $categories[] = $cat;
                    }

                    if (empty($categories)) {
                        echo '<div class="alert alert-info">Keine Kategorien in diesem Board.</div>';
                    } else {
                        echo '<ul class="list-group mb-3">';
                        foreach ($categories as $cat) {
                            // Anzahl Threads
                            $resThreads = safe_query("SELECT COUNT(*) AS thread_count FROM plugins_forum_threads WHERE catID = " . intval($cat['catID']));
                            $threadCountRow = mysqli_fetch_assoc($resThreads);
                            $threadCount = $threadCountRow['thread_count'] ?? 0;

                            // Anzahl Beiträge (Posts)
                            $resPosts = safe_query("
                                SELECT COUNT(*) AS post_count 
                                FROM plugins_forum_posts p
                                JOIN plugins_forum_threads t ON p.threadID = t.threadID
                                WHERE t.catID = " . intval($cat['catID'])
                            );
                            $postCountRow = mysqli_fetch_assoc($resPosts);
                            $postCount = $postCountRow['post_count'] ?? 0;

                            // Letzter Beitrag
                            $resLastPost = safe_query("
                                SELECT p.postID, p.created_at, p.threadID, u.username 
                                FROM plugins_forum_posts p
                                LEFT JOIN users u ON p.userID = u.userID
                                JOIN plugins_forum_threads t ON p.threadID = t.threadID
                                WHERE t.catID = " . intval($cat['catID']) . "
                                ORDER BY p.created_at DESC
                                LIMIT 1
                            ");
                            $lastPost = mysqli_fetch_assoc($resLastPost);
                            $lastPostTime = $lastPost['created_at'] ?? null;
                            $lastPostUser = $lastPost['username'] ?? 'Keine Beiträge';
                            $lastPostID = $lastPost['postID'] ?? 0;
                            $lastPostThreadID = $lastPost['threadID'] ?? 0;

                            // Seite des letzten Beitrags berechnen (10 Posts pro Seite)
                            if ($lastPostID) {
                                $resPos = safe_query("
                                    SELECT COUNT(*) AS pos 
                                    FROM plugins_forum_posts 
                                    WHERE threadID = " . intval($lastPostThreadID) . " 
                                      AND created_at <= '" . mysqli_real_escape_string($GLOBALS['_database'], $lastPostTime) . "'
                                ");
                                $posRow = mysqli_fetch_assoc($resPos);
                                $pos = $posRow['pos'] ?? 1;
                                $postsPerPage = 10;
                                $lastPostPage = ceil($pos / $postsPerPage);
                            } else {
                                $lastPostPage = 1;
                            }
                    ?>

                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=category&id=' . intval($cat['catID']))) ?>">
                                    <?= htmlspecialchars($cat['title'] ?? 'Unbekannte Kategorie') ?>
                                </a>

                                <br>
                                <small class="text-muted"><?= htmlspecialchars($cat['description'] ?? '') ?></small>
                            </div>
                            <div class="text-end" style="min-width: 220px;">
                                <span class="badge bg-primary me-2">Themen: <?= intval($threadCount) ?></span>
                                <span class="badge bg-secondary me-2">Beiträge: <?= intval($postCount) ?></span>
                                <div class="small text-muted">
                                    Letzter Beitrag: 
                                    <?php if ($lastPostTime): ?>
                                        <?php
                                        $url = 'index.php?site=forum&action=thread&id=' . intval($lastPostThreadID) . '&page=' . intval($lastPostPage) . '#post' . intval($lastPostID);
                                        ?>
                                        <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)) ?>">
                                            <?= date('d.m.Y H:i', intval($lastPostTime)) ?> von <?= htmlspecialchars($lastPostUser) ?>
                                        </a>

                                    <?php else: ?>
                                        Keine Beiträge
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php
                    }
                    echo '</ul>';
                }
                ?>


                </div>
            </div>
        <?php endforeach; ?>
    
    <?php
    break;

    case 'overview':
    $boardID = intval($_GET['id'] ?? 0);
    if (!$boardID) {
        echo '<div class="alert alert-danger">Kein Board ausgewählt.</div>';
        break;
    }

    $res = safe_query("SELECT * FROM plugins_forum_boards WHERE id = $boardID");
    $board = mysqli_fetch_assoc($res);

    if (!$board) {
        echo '<div class="alert alert-danger">Board nicht gefunden.</div>';
        break;
    }

    $resCats = safe_query("SELECT * FROM plugins_forum_categories WHERE group_id = $boardID ORDER BY position ASC");
    $categories = [];
    while ($cat = mysqli_fetch_assoc($resCats)) {
        $categories[] = $cat;
    }
    ?>
    
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum')) ?>">Forum</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($board['title']) ?></li>
          </ol>
        </nav>
        <h4 class="mb-3"><?= htmlspecialchars($board['title']) ?></h4>
        <p><?= nl2br(htmlspecialchars($board['description'])) ?></p>

        <?php if (empty($categories)): ?>
            <div class="alert alert-info">Keine Kategorien in diesem Board.</div>
        <?php else: ?>
            <ul class="list-group mb-4">
                <?php foreach ($categories as $category):
                    // Anzahl Threads (Themen) in der Kategorie
                    $resThreads = safe_query("SELECT COUNT(*) AS thread_count FROM plugins_forum_threads WHERE catID = " . intval($category['catID']));
                    $threadCountRow = mysqli_fetch_assoc($resThreads);
                    $threadCount = $threadCountRow['thread_count'] ?? 0;

                    // Anzahl Beiträge (Posts) in der Kategorie (über alle Threads)
                    $resPosts = safe_query("
                        SELECT COUNT(*) AS post_count 
                        FROM plugins_forum_posts p
                        JOIN plugins_forum_threads t ON p.threadID = t.threadID
                        WHERE t.catID = " . intval($category['catID'])
                    );
                    $postCountRow = mysqli_fetch_assoc($resPosts);
                    $postCount = $postCountRow['post_count'] ?? 0;

                    // Letzter Beitrag in der Kategorie (PostID, Zeit, User, ThreadID)
                    $resLastPost = safe_query("
                        SELECT p.postID, p.created_at, u.username, t.threadID
                        FROM plugins_forum_posts p
                        LEFT JOIN users u ON p.userID = u.userID
                        JOIN plugins_forum_threads t ON p.threadID = t.threadID
                        WHERE t.catID = " . intval($category['catID']) . "
                        ORDER BY p.created_at DESC
                        LIMIT 1
                    ");
                    $lastPost = mysqli_fetch_assoc($resLastPost);
                    $lastPostTime = $lastPost['created_at'] ?? null;
                    $lastPostUser = $lastPost['username'] ?? 'Keine Beiträge';
                    $lastPostID = $lastPost['postID'] ?? 0;
                    $lastPostThreadID = $lastPost['threadID'] ?? 0;

                    // Optional: Berechnung der Seite des letzten Beitrags, falls Pagination da ist
                    // Angenommen 10 Beiträge pro Seite:
                    $lastPostPage = 1;
                    if ($lastPostID && $lastPostThreadID) {
                        $resPos = safe_query("
                            SELECT COUNT(*) AS post_position 
                            FROM plugins_forum_posts 
                            WHERE threadID = $lastPostThreadID AND postID <= $lastPostID
                        ");
                        $posRow = mysqli_fetch_assoc($resPos);
                        $postPosition = $posRow['post_position'] ?? 1;
                        $lastPostPage = ceil($postPosition / 10);
                    }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <?php
                            $url = 'index.php?site=forum&action=category&id=' . intval($category['catID']);
                            ?>
                            <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)) ?>">
                                <?= htmlspecialchars($category['title']) ?>
                            </a>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars($category['description']) ?></small>
                        </div>
                        <div class="text-end" style="min-width: 240px;">
                            <span class="badge bg-primary me-2">Themen: <?= intval($threadCount) ?></span>
                            <span class="badge bg-secondary me-2">Beiträge: <?= intval($postCount) ?></span>
                            <div class="small text-muted">
                                Letzter Beitrag:
                                <?php if ($lastPostTime && $lastPostID && $lastPostThreadID): ?>
                                    <?php
                                    $url = 'index.php?site=forum&action=thread&id=' . intval($lastPostThreadID) . '&page=' . intval($lastPostPage) . '#post' . intval($lastPostID);
                                    ?>
                                    <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)) ?>">
                                        <?= date('d.m.Y H:i', strtotime($lastPostTime)) ?> von <?= htmlspecialchars($lastPostUser) ?>
                                    </a>

                                <?php else: ?>
                                    Keine Beiträge
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    
    <?php
    break;


    case 'category':
    $catID = intval($_GET['id'] ?? 0);
    if (!$catID) {
        echo '<div class="alert alert-danger">Keine Kategorie ausgewählt.</div>';
        break;
    }

    $categoryRes = safe_query("SELECT * FROM plugins_forum_categories WHERE catID = $catID");
    $category = mysqli_fetch_assoc($categoryRes);

    if (!$category) {
        echo '<div class="alert alert-danger">Kategorie nicht gefunden.</div>';
        break;
    }

    // Board zur Kategorie laden
    $boardID = intval($category['group_id']);
    $boardRes = safe_query("SELECT * FROM plugins_forum_boards WHERE id = $boardID");
    $board = mysqli_fetch_assoc($boardRes);

    $threads = getThreadsByCategory($catID);
    $threads = enrichThreadsWithLastPost($threads);
    ?>
    
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum')) ?>">Forum</a></li>
            <li class="breadcrumb-item">
                <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=overview&id=' . intval($board['id']))) ?>">
                    <?= htmlspecialchars($board['title'] ?? 'Unbekanntes Board') ?>
                </a>
            </li>

            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($category['title']) ?></li>
          </ol>
        </nav>
        <h4 class="mb-3"><?= htmlspecialchars($category['title']) ?></h4>
        <p><?= htmlspecialchars($category['description']) ?></p>

            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><?= htmlspecialchars($category['title']) ?></h2>
                    <?php if ($userID): ?>
                        <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=new_thread&catID=' . intval($category['catID']))) ?>" class="btn btn-sm btn-primary">
                            Neues Thema erstellen
                        </a>

                    <?php endif; ?>
                </div>
                <div class="card-body">


        <?php if (empty($threads)): ?>
            <div class="alert alert-info">Keine Themen in dieser Kategorie.</div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
            <?php foreach ($threads as $thread): ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=thread&id=' . intval($thread['threadID']))) ?>" class="text-decoration-none">
                            <?= htmlspecialchars($thread['title']) ?>
                        </a>

                        <div class="text-end">
                            <span class="badge bg-info"><?= intval($thread['replies']) ?> Antworten</span>
                            <span class="badge bg-secondary"><?= intval($thread['views']) ?> Aufrufe</span><br>
                            <small class="text-muted">
                                Letzter Beitrag:
                                <?php if ($thread['last_post_id'] > 0): ?>
                                    <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=forum&action=thread&id=' . intval($thread['threadID']) . '&page=' . intval($thread['last_post_page']) . '#post' . intval($thread['last_post_id']))) ?>">
                                        <?= date('d.m.Y H:i', $thread['last_post_time']) ?> von <?= htmlspecialchars($thread['last_username']) ?>
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
    </div></div>
    <?php
    break;
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('uploadImage');

  let editor = null;

  // CKEditor bereit?
  if (typeof CKEDITOR === 'undefined') {
    console.error('CKEditor wurde nicht gefunden.');
    return;
  }

  CKEDITOR.on('instanceReady', function(evt) {
    if (evt.editor.name === 'ckeditor') {
      editor = evt.editor;
      console.log('Editor bereit:', editor.name);
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

    fetch('/includes/plugins/forum/upload_image.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        alert('Fehlerhafte Serverantwort:\n' + text);
        return;
      }

      if (data.success && data.url) {
        if (editor) {
          editor.focus();
          editor.insertHtml('<img src="' + data.url + '" alt="">');
        } else {
          alert('Editor noch nicht bereit');
        }
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
