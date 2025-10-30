<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\RoleManager;
use nexpell\SeoUrlHandler;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('articles');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HINZUGEFÜGT: Achievement-Plugin prüfen und Flagge setzen
$achievements_plugin_active = false;
$achievements_plugin_path = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/achievements/engine_achievements.php';
if (file_exists($achievements_plugin_path)) {
    require_once($achievements_plugin_path);
    $achievements_plugin_active = true;
}

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('title'),
        'subtitle' => 'Articles'
    ];
    
    echo $tpl->loadTemplate("articles", "head", $data_array, 'plugin');

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}

$maxStars = 5; // Maximalsterne

// Funktion zum Prüfen der Rolle eines Benutzers
function has_role(int $userID, string $roleName): bool {
    global $_database;


    $roleID = RoleManager::getUserRoleID($userID);
    if ($roleID === null) {
        return false;
    }    
}
if ($action == "show" && isset($_GET['id']) && is_numeric($_GET['id'])) {
    global $_database;
    $category_id = (int)$_GET['id'];

    $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
    $limit = 6;
    $offset = ($page - 1) * $limit;

    // Kategorie laden
    $getcat = safe_query("SELECT * FROM plugins_articles_categories WHERE id='$category_id'");
    $ds = mysqli_fetch_array($getcat);
    $name = $ds['name'];

    // Gesamtanzahl Artikel für Paginierung
    $total_articles_query = safe_query("SELECT COUNT(*) as total FROM plugins_articles WHERE category_id='$category_id' AND is_active='1'");
    $total_articles_result = mysqli_fetch_array($total_articles_query);
    $total_articles = (int)$total_articles_result['total'];
    $total_pages = ceil($total_articles / $limit);
    $title_url = SeoUrlHandler::convertToSeoUrl('index.php?site=articles');

    // Artikel laden
    $ergebnis = safe_query("SELECT * FROM plugins_articles WHERE category_id='$category_id' AND is_active='1' ORDER BY updated_at DESC LIMIT $offset, $limit");

    $data_array = [
        'name'              => $name,
        'title_url'         => $title_url,
        'title'             => $languageService->get('title'),
        'title_categories'  => $languageService->get('title_categories'),
        'categories'        => $languageService->get('categories'),
        'category'          => $languageService->get('category'),
    ];

    echo $tpl->loadTemplate("articles", "details_head", $data_array, 'plugin');
    echo $tpl->loadTemplate("articles", "content_all_head", $data_array, 'plugin');

    if (mysqli_num_rows($ergebnis)) {
        $monate = [
            1 => $languageService->get('jan'), 2 => $languageService->get('feb'),
            3 => $languageService->get('mar'), 4 => $languageService->get('apr'),
            5 => $languageService->get('may'), 6 => $languageService->get('jun'),
            7 => $languageService->get('jul'), 8 => $languageService->get('aug'),
            9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
            11 => $languageService->get('nov'), 12 => $languageService->get('dec')
        ];

        while ($ds = mysqli_fetch_array($ergebnis)) {
            $timestamp = (int)$ds['updated_at'];
            $tag = date("d", $timestamp);
            $monat = date("n", $timestamp);
            $year = date("Y", $timestamp);
            $monatname = $monate[$monat];
            $username = getusername($ds['userID']);

            $banner_image = $ds['banner_image'];
            $image = $banner_image
                ? "/includes/plugins/articles/images/article/" . $banner_image
                : "/includes/plugins/articles/images/no-image.jpg";

            // Übersetzung laden
            $translate = new multiLanguage($lang);
            $title = $translate->getTextByLanguage($ds['title']);

            // Optional kürzen
            $maxblogchars = 25;
            $short_content = (mb_strlen($title) > $maxblogchars)
                ? mb_substr($title, 0, $maxblogchars) . '...'
                : $title;

            $article_id = (int)$ds['id'];

            $profileUrl = SeoUrlHandler::convertToSeoUrl(
                'index.php?site=profile&userID=' . intval($ds['userID'])
            );

            $username = '<a href="' . htmlspecialchars($profileUrl) . '">
                <img src="' . htmlspecialchars(getavatar($ds['userID'])) . '" 
                     class="img-fluid align-middle rounded me-1" 
                     style="height: 23px; width: 23px;" 
                     alt="' . htmlspecialchars(getusername($ds['userID'])) . '">
                <strong>' . htmlspecialchars(getusername($ds['userID'])) . '</strong>
            </a>';

            $catID = (int)$ds['category_id'];
            $cat_query = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $catID");
            $cat = mysqli_fetch_assoc($cat_query);

            $cat_name = htmlspecialchars($cat['name'] ?? '');


            $article_catname = '<a href="' . 
                htmlspecialchars(SeoUrlHandler::convertToSeoUrl("index.php?site=articles&action=show&id=$catID")) . 
                '"><strong style="font-size: 12px">' . 
                htmlspecialchars($cat_name) . 
                '</strong></a>';
            $url_watch = "index.php?site=articles&action=watch&id=" . intval($article_id);
            $url_watch_seo = SeoUrlHandler::convertToSeoUrl($url_watch);

            $data_array = [
                'name'          => $article_catname,
                'title'         => $title,
                'username'      => $username,
                'image'         => $image,
                'tag'           => $tag,
                'monat'         => $monatname,
                'year'          => $year,
                'url_watch'     => $url_watch_seo,
                'lang_rating'   => $languageService->get('rating'),
                'lang_votes'    => $languageService->get('votes'),
                'link'          => $languageService->get('link'),
                'info'          => $languageService->get('info'),
                'stand'         => $languageService->get('stand'),
                'by'            => $languageService->get('by'),
                'on'            => $languageService->get('on'),
                'read_more'      => $languageService->get('read_more'),
            ];

            echo $tpl->loadTemplate("articles", "content_all", $data_array, 'plugin');
        }

        echo $tpl->loadTemplate("articles", "content_all_foot", $data_array, 'plugin');

        // Pagination
        if ($total_pages > 1) {
            echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-4">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i == $page) ? ' active' : '';
                echo '<li class="page-item' . $active . '">
                        <a class="page-link" href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=articles&action=show&id=' . intval($category_id) . '&page=' . intval($i))) . '">' . intval($i) . '</a>
                      </li>';
            }
            echo '</ul></nav>';
        }
    } else {
        echo $plugin_language['no_articles'] . '<br><br>[ <a href="' . 
         htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=articles')) . 
         '" class="alert-article">' . 
         $plugin_language['go_back'] . 
         '</a> ]';

    }

    $stmt = $_database->prepare("SELECT role_name FROM user_roles WHERE roleID = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $roleID);
    $stmt->execute();
    $stmt->bind_result($dbRoleName);

    $result = false;
    if ($stmt->fetch()) {
        $result = (strtolower($dbRoleName) === strtolower($roleName));
    }
    $stmt->close();

    return $result;
}

// UserID definieren, damit keine "undefined variable" Warnungen entstehen
$loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
$userID = $loggedin ? (int)$_SESSION['userID'] : 0;

$action = $_GET['action'] ?? '';

// Handle rating submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_rating'], $_POST['rating'], $_POST['itemID'])
) {
    $plugin = 'articles';
    $rating = (int)$_POST['rating'];
    $itemID = (int)$_POST['itemID'];

    $userID = $_SESSION['userID'] ?? 0;
    if (!$userID) {
        die('<div class="alert alert-warning">Du musst eingeloggt sein, um zu bewerten.</div>');
    }

    if ($rating < 0 || $rating > $maxStars) {
        die('Bitte gib eine Bewertung zwischen 0 und ' . $maxStars . ' ab.');
    }

    $check = safe_query("SELECT * FROM ratings WHERE plugin = '$plugin' AND itemID = $itemID AND userID = $userID");
    if (mysqli_num_rows($check) === 0) {
        safe_query("INSERT INTO ratings (plugin, itemID, userID, rating, date) VALUES ('$plugin', $itemID, $userID, $rating, NOW())");
    } else {
        safe_query("UPDATE ratings SET rating = $rating, date = NOW() WHERE plugin = '$plugin' AND itemID = $itemID AND userID = $userID");
    }

    $res = safe_query("SELECT AVG(rating) AS avg_rating FROM ratings WHERE plugin = '$plugin' AND itemID = $itemID");
    if ($row = mysqli_fetch_assoc($res)) {
        $avg_rating = round($row['avg_rating'], 1);
        safe_query("UPDATE plugins_articles SET rating = $avg_rating WHERE id = $itemID");
    }

    $url = "index.php?site=articles&action=watch&id=$itemID";
    header("Location: " . SeoUrlHandler::convertToSeoUrl($url));
    exit();
}

// Kommentar speichern
if (isset($_POST['submit_comment'])) {
    $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
    $userID = $loggedin ? (int)$_SESSION['userID'] : 0;
    if (
        $loggedin &&
        !empty($_POST['comment']) &&
        is_numeric($_POST['id']) &&
        isset($_POST['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $comment = htmlspecialchars($_POST['comment']);
        $itemID = (int)$_POST['id'];

        safe_query("INSERT INTO comments (plugin, itemID, userID, comment, date, parentID, modulname) VALUES ('articles', $itemID, $userID, '$comment', NOW(), 0, 'articles')");

        $url = "index.php?site=articles&action=watch&id=$itemID";
        header("Location: " . htmlspecialchars(SeoUrlHandler::convertToSeoUrl($url)));
        exit;
    } else {
        die("Ungültiger CSRF-Token oder fehlende Eingaben.");
    }
}

// Kommentar löschen
if (isset($_GET['action']) && $_GET['action'] === 'deletecomment' && isset($_GET['id']) && is_numeric($_GET['id'])) {

    function slugdecode(string $slug): string {
        return urldecode(str_replace('-', ' ', $slug));
    }

    $commentID = (int)$_GET['id'];
    $referer = isset($_GET['ref']) ? slugdecode($_GET['ref']) : 'index.php?site=articles';

    if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        die('Ungültiger CSRF-Token.');
    }

    $res = safe_query("DELETE FROM comments WHERE commentID = $commentID");

    if ($res) {
        header("Location: " . htmlspecialchars(SeoUrlHandler::convertToSeoUrl($referer)));
        exit();
    } else {
        die('<div class="alert alert-danger">Fehler beim Löschen des Kommentars.</div>');
    }
}

if ($action == "watch" && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pluginName = 'articles';

    $articleQuery = safe_query("SELECT * FROM plugins_articles WHERE id = $id AND is_active = 1");
    if (mysqli_num_rows($articleQuery)) {
        $article = mysqli_fetch_array($articleQuery);

        $categoryQuery = safe_query("SELECT * FROM plugins_articles_categories WHERE id = " . (int)$article['category_id']);
        $category = mysqli_fetch_array($categoryQuery);

        if ($category) {
            $name = htmlspecialchars($category['name']);
        } else {
            $name = 'Unbekannte Kategorie';
        }

        $title_url = SeoUrlHandler::convertToSeoUrl('index.php?site=articles');
        $title_url_show = SeoUrlHandler::convertToSeoUrl('index.php?site=articles&action=show&id=' . intval($article['category_id']));

        $data_array = [
            'name' => $name,
            'title_url' => $title_url,
            'title_url_show' => $title_url_show,
            'title' => htmlspecialchars($article['title']),            
            'title_categories' => $languageService->get('title_categories'),
            'categories' => $languageService->get('categories'),
            'category' => $languageService->get('category'),
        ];
        echo $tpl->loadTemplate("articles", "content_details_head", $data_array, 'plugin');

        // Cookie-basierte View-Zählung (max. 1x pro Tag & Browser)
        $cookieName = 'article_view_' . $id;
        if (!isset($_COOKIE[$cookieName])) {
            safe_query("UPDATE plugins_articles SET views = views + 1 WHERE id = $id");
            setcookie($cookieName, '1', time() + 86400, '/', '', isset($_SERVER['HTTPS']), true);
            $article['views']++;
        }

        $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
        $userID = $loggedin ? (int)$_SESSION['userID'] : 0;

        $hasRated = false;
        if ($loggedin) {
            $check = safe_query("SELECT * FROM ratings WHERE plugin = '$pluginName' AND itemID = $id AND userID = $userID");
            $hasRated = mysqli_num_rows($check) > 0;
        }

        if ($loggedin) {
            if ($hasRated) {
                $rateform = '<div class="alert alert-warning">' . $languageService->get('you_have_already_rated') . '</div>';
            } else {
                $rateform = '
                <form method="post" action="" class="d-flex align-items-center" id="starRatingForm">
                    <label class="me-3 mb-0">' . $languageService->get('rate_now') . '</label>
                    <input type="hidden" name="rating" id="ratingInput" value="0" required>
                    <input type="hidden" name="plugin" value="' . $pluginName . '">
                    <input type="hidden" name="itemID" value="' . $id . '">
                    <input type="hidden" name="submit_rating" value="1">

                    <div role="radiogroup" aria-label="' . $languageService->get('rate_now') . '" id="starRating" style="font-size: 1.5rem; cursor: pointer; user-select:none;">
                ';

                for ($i = 1; $i <= $maxStars; $i++) {
                    $rateform .= '<i class="bi bi-star text-warning" data-value="' . $i . '" tabindex="0" role="radio" aria-checked="false" aria-label="' . $i . '"></i>';
                }

                $rateform .= '
                    </div>
                    <button type="submit" class="btn btn-primary ms-3">' . $languageService->get('rate') . '</button>
                </form>

                <style>
                    #starRating i:hover,
                    #starRating i:hover ~ i {
                        color: #ffc107;
                    }
                </style>

                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const stars = document.querySelectorAll("#starRating i");
                    const ratingInput = document.getElementById("ratingInput");
                    let currentRating = 0;

                    function setRating(rating) {
                        currentRating = rating;
                        ratingInput.value = rating;
                        stars.forEach((star, idx) => {
                            if (idx < rating) {
                                star.classList.remove("bi-star");
                                star.classList.add("bi-star-fill");
                                star.setAttribute("aria-checked", "true");
                            } else {
                                star.classList.remove("bi-star-fill");
                                star.classList.add("bi-star");
                                star.setAttribute("aria-checked", "false");
                            }
                        });
                    }

                    function fillStarsHover(rating) {
                        stars.forEach((star, idx) => {
                            if (idx < rating) {
                                star.classList.remove("bi-star");
                                star.classList.add("bi-star-fill");
                            } else {
                                star.classList.remove("bi-star-fill");
                                star.classList.add("bi-star");
                            }
                        });
                    }

                    stars.forEach(star => {
                        star.addEventListener("click", () => {
                            const val = parseInt(star.getAttribute("data-value"));
                            setRating(val);
                        });

                        star.addEventListener("keydown", (e) => {
                            if (e.key === "Enter" || e.key === " " || e.key === "Spacebar") {
                                e.preventDefault();
                                const val = parseInt(star.getAttribute("data-value"));
                                setRating(val);
                            }
                        });

                        star.addEventListener("mouseover", () => {
                            const val = parseInt(star.getAttribute("data-value"));
                            fillStarsHover(val);
                        });
                    });

                    document.getElementById("starRating").addEventListener("mouseleave", () => {
                        setRating(currentRating);
                    });

                    setRating(0);
                });
                </script>
                ';
            }
        } else {
            $rateform = '<div class="alert alert-warning">' . $languageService->get('rate_have_to_reg_login') . '</div>';
        }

        // Durchschnittsbewertung und Votes laden
        $r = safe_query("SELECT AVG(rating) AS avg_rating, COUNT(ratingID) AS votes FROM ratings WHERE plugin = 'articles' AND itemID = $id");
        $r = mysqli_fetch_assoc($r);
        $avg_rating = round($r['avg_rating'] ?? 0);
        $votes = (int)($r['votes'] ?? 0);

        if ($avg_rating > $maxStars) {
            $avg_rating = $maxStars;
        }

        $ratingpic = '<span title="' . $avg_rating . '/' . $maxStars . '" aria-label="Average rating">';
        for ($i = 1; $i <= $maxStars; $i++) {
            if ($i <= $avg_rating) {
                $ratingpic .= '<i class="bi bi-star-fill text-warning" aria-hidden="true"></i>';
            } else {
                $ratingpic .= '<i class="bi bi-star text-warning" aria-hidden="true"></i>';
            }
        }
        $ratingpic .= '</span>';

        $image = $article['banner_image'] ? "includes/plugins/articles/images/article/{$article['banner_image']}" : "includes/plugins/articles/images/no-image.jpg";

        $profileUrl = SeoUrlHandler::convertToSeoUrl(
            'index.php?site=profile&userID=' . intval($article['userID'])
        );

        $username = '<a href="' . htmlspecialchars($profileUrl) . '">
            <img src="' . htmlspecialchars(getavatar($article['userID'])) . '" 
                 class="img-fluid align-middle rounded-circle me-1" 
                 style="height: 23px; width: 23px;" 
                 alt="' . htmlspecialchars(getusername($article['userID'])) . '">
            <strong>' . htmlspecialchars(getusername($article['userID'])) . '</strong>
        </a>';

        $link = $article['slug'] ? $languageService->get('link') . ': <a href="' . $article['slug'] . '" target="_blank">' . $article['slug'] . '</a>' : '';

        // Beispiel: Link zum Artikel (wenn slug vorhanden)
        if (!empty($article['slug'])) {
            $link = '<a href="' . htmlspecialchars($article['slug']) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($article['slug']) . '</a>';
        } else {
            $link = $languageService->get('no_link');
        }
        $data_array = [
            'title' => $article['title'],
            'content' => $article['content'],
            'username' => $username,
            'date' => date('d.m.Y H:i', $article['updated_at']),
            'ratingpic' => $ratingpic,
            'votes' => $votes,
            'rateform' => $rateform,
            'views' => $article['views'],
            'image' => $image,
            'link' => $link,
            'lang_rating' => $languageService->get('rating'),
            'lang_votes' => $languageService->get('votes'),
            'lang_link' => $languageService->get('link'),
            'info' => $languageService->get('info'),
            'stand' => $languageService->get('stand'),
            'lang_views' => $languageService->get('views'),
        ];

        echo $tpl->loadTemplate("articles", "content_details", $data_array, 'plugin');

        // Kommentare anzeigen
        if ($article['allow_comments']) {
            $comments = safe_query("
                SELECT c.*, u.username 
                FROM comments c 
                JOIN users u ON c.userID = u.userID 
                WHERE c.plugin = '$pluginName' AND c.itemID = $id 
                ORDER BY c.date DESC
            ");

            echo '<div class="mt-5"><h5 class="border-bottom p-2">' . $languageService->get('comments') . '</h5><ul class="list-group">';
            while ($row = mysqli_fetch_array($comments)) {
                $deleteLink = '';
                $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
                $userID = $loggedin ? (int)$_SESSION['userID'] : 0;

                // Avatar + Username für den Kommentarautor
                    $commentUserID = (int)$row['userID'];
                    $username = '<a href="index.php?site=profile&amp;userID=' . $commentUserID . '">
                        <img src="' . getavatar($commentUserID) . '" class="img-fluid align-middle rounded me-1" style="height: 23px; width: 23px;" alt="' . getusername($commentUserID) . '">
                        <strong>' . getusername($commentUserID) . '</strong>
                    </a>';

                $canDelete = ($userID == $row['userID'] || has_role($userID, 'Admin'));

                if ($canDelete) {
                    $deleteLink = '<a href="index.php?site=articles&action=deletecomment&id=' . $row['commentID'] . '&ref=' . urlencode($_SERVER['REQUEST_URI']) . '&token=' . $_SESSION['csrf_token'] . '" class="btn btn-sm btn-danger ms-2" onclick="return confirm(\'Kommentar wirklich löschen?\')">' . $languageService->get('delete') . '</a>';
                }

                // KORRIGIERTER Block zum Abrufen der Achievements
                $achievements_widgets = ''; // Variable initialisieren
                if ($achievements_plugin_active) {
                    $achievements_widgets = achievements_get_user_icons_html($commentUserID);
                }

                echo '<li class="list-group-item border-bottom">
                    <div class="d-flex mt-4">
                        <div class="flex-shrink-0">
                            <a href="index.php?site=profile&amp;userID=' . (int)$row['userID'] . '">
                                <img src="' . getavatar((int)$row['userID']) . '" class="img-fluid rounded-circle me-3" style="height: 60px; width: 60px;" alt="' . htmlspecialchars($row['username']) . '">
                            </a>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <a href="index.php?site=profile&amp;userID=' . (int)$row['userID'] . '">
                                    <strong>' . htmlspecialchars($row['username']) . '</strong>
                                </a>
                                ' . $achievements_widgets . '
                                <span class="text-muted small">' . date('d.m.Y H:i', strtotime($row['date'])) . '</span>
                                ' . $deleteLink . '
                            </div>
                            <div class="mt-2 mb-4">
                                ' . nl2br(htmlspecialchars($row['comment'])) . '
                            </div>
                        </div>
                    </div>
                </li>';
            }
            echo '</ul></div>';

            if ($loggedin) {
                echo '<form method="POST" action="index.php?site=articles&action=watch&id=' . $id . '" class="mt-4">
                    <textarea class="form-control" name="comment" rows="6" required></textarea>
                    <input type="hidden" name="id" value="' . $id . '">
                    <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                    <button type="submit" name="submit_comment" class="btn btn-success mt-3">Kommentar abschicken</button>
                </form>';
            } else {
                echo '<div class="alert alert-warning">' . $languageService->get('must_login_comment') . '</div>';
            }
        }
    } else {
        echo $languageService->get('article_not_found');
    }

} elseif ($action == "") {
    // Kategorien laden und anzeigen
    $cats_result = safe_query("SELECT * FROM plugins_articles_categories ORDER BY sort_order ASC");
    if (mysqli_num_rows($cats_result) > 0) {

        

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $limit = 6;
        $offset = ($page - 1) * $limit;

        $total_articles_result = safe_query("SELECT COUNT(*) AS total FROM plugins_articles WHERE is_active = 1");
        $total_articles_row = mysqli_fetch_assoc($total_articles_result);
        $total_articles = (int)$total_articles_row['total'];
        $total_pages = ceil($total_articles / $limit);

        $articles_result = safe_query("SELECT * FROM plugins_articles WHERE is_active = 1 ORDER BY updated_at DESC LIMIT $offset, $limit");

        if (mysqli_num_rows($articles_result) > 0) {
        
            $data_array = [
                'title_categories' => $languageService->get('title_categories'),
            ];

            echo $tpl->loadTemplate("articles", "category", $data_array, 'plugin');

            // Head-Bereich der Artikelliste
            echo $tpl->loadTemplate("articles", "content_all_head", $data_array, 'plugin');
        
            $monate = [
                1 => $languageService->get('jan'), 2 => $languageService->get('feb'),
                3 => $languageService->get('mar'), 4 => $languageService->get('apr'),
                5 => $languageService->get('may'), 6 => $languageService->get('jun'),
                7 => $languageService->get('jul'), 8 => $languageService->get('aug'),
                9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
                11 => $languageService->get('nov'), 12 => $languageService->get('dec')
            ];

            while ($article = mysqli_fetch_assoc($articles_result)) {
                $id = (int)$article['id'];
                $timestamp = (int)$article['updated_at'];
                $tag = date("d", $timestamp);
                $monat = (int)date("n", $timestamp);
                $year = date("Y", $timestamp);

                $monatname = $monate[$monat] ?? '';

                $banner_image = $article['banner_image'];
                $image = $banner_image ? "/includes/plugins/articles/images/article/" . $banner_image : "/includes/plugins/articles/images/no-image.jpg";

                $profileUrl = SeoUrlHandler::convertToSeoUrl(
                    'index.php?site=profile&userID=' . intval($article['userID'])
                );

                $username = '<a href="' . htmlspecialchars($profileUrl) . '">
                    <img src="' . htmlspecialchars(getavatar($article['userID'])) . '" 
                         class="img-fluid align-middle rounded me-1" 
                         style="height: 23px; width: 23px;" 
                         alt="' . htmlspecialchars(getusername($article['userID'])) . '">
                    <strong>' . htmlspecialchars(getusername($article['userID'])) . '</strong>
                </a>';

                $translate = new multiLanguage($lang);

                $translate->detectLanguages($article['title']);
                $title = $translate->getTextByLanguage($article['title']);

                $maxTitleChars = 70;
                $short_title = $title;
                if (mb_strlen($short_title) > $maxTitleChars) {
                    $short_title = mb_substr($short_title, 0, $maxTitleChars) . '...';
                }

                $catID = (int)$article['category_id'];
                $cat_query = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $catID");
                $cat = mysqli_fetch_assoc($cat_query);

                $cat_name = htmlspecialchars($cat['name'] ?? '');
                

                $article_catname = '<a href="' . 
                    htmlspecialchars(SeoUrlHandler::convertToSeoUrl("index.php?site=articles&action=show&id=$catID")) . 
                    '"><strong style="font-size: 12px">' . 
                    htmlspecialchars($cat_name) . 
                    '</strong></a>';
                $url_watch = "index.php?site=articles&action=watch&id=" . intval($id);
                $url_watch_seo = SeoUrlHandler::convertToSeoUrl($url_watch);



                $data_array = [
                    'name'           => $article_catname,
                    'title'          => htmlspecialchars($short_title),
                    'tag'            => $tag,
                    'monat'          => $monatname,
                    'year'           => $year,
                    'image'          => $image,
                    'username'       => $username,
                    'url_watch'      => $url_watch_seo,
                    'by'             => $languageService->get('by'),
                    'read_more'      => $languageService->get('read_more'),
                ];

                echo $tpl->loadTemplate("articles", "content_all", $data_array, 'plugin');
            }
        } else {
            echo '<div class="alert alert-info">' . $languageService->get('no_articles_found') . '</div>';
        }

        echo $tpl->loadTemplate("articles", "content_all_foot", $data_array, 'plugin');

        if ($total_pages > 1) {
            echo '<nav><ul class="pagination justify-content-center">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i === $page) ? 'active' : '';
                echo '<li class="page-item ' . $active . '"><a class="page-link" href="index.php?site=articles&page=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
        }
    } else {
        echo '<div class="alert alert-info">'.$languageService->get('no_articles_categories_found').'</div>';
    }
}
