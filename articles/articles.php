<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\RoleManager;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('articles');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$maxStars = 5; // Set maximum stars here, used everywhere consistently

// Funktion zum Prüfen der Rolle eines Benutzers
function has_role(int $userID, string $roleName): bool {
    global $_database;

    $roleID = RoleManager::getUserRoleID($userID);
    if ($roleID === null) {
        return false;
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

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Articles'
];

echo $tpl->loadTemplate("articles", "head", $data_array, 'plugin');

$action = $_GET['action'] ?? '';

// Handle rating submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_rating'], $_POST['rating'], $_POST['itemID'])
) {
    $plugin = 'articles'; // Name des Plugins, hier 'articles'
    $rating = (int)$_POST['rating'];
    $itemID = (int)$_POST['itemID'];

    if (!$userID) {
        die('<div class="alert alert-warning">Du musst eingeloggt sein, um zu bewerten.</div>');
    }

    if ($rating < 0 || $rating > $maxStars) {
        die('Bitte gib eine Bewertung zwischen 0 und ' . $maxStars . ' ab.');
    }

    // Prüfen, ob User schon bewertet hat
    $check = safe_query("SELECT * FROM ratings WHERE plugin = '$plugin' AND itemID = $itemID AND userID = $userID");
    if (mysqli_num_rows($check) === 0) {
        // Insert neue Bewertung
        safe_query("INSERT INTO ratings (plugin, itemID, userID, rating, date) VALUES ('$plugin', $itemID, $userID, $rating, NOW())");
    } else {
        // Optional: Bewertung updaten
        safe_query("UPDATE ratings SET rating = $rating, date = NOW() WHERE plugin = '$plugin' AND itemID = $itemID AND userID = $userID");
    }

    // Durchschnitt neu berechnen und speichern (optional, falls du eine Summe brauchst)
    $res = safe_query("SELECT AVG(rating) AS avg_rating FROM ratings WHERE plugin = '$plugin' AND itemID = $itemID");
    if ($row = mysqli_fetch_assoc($res)) {
        $avg_rating = round($row['avg_rating'], 1);  // Beispiel 1 Dezimalstelle
        // Falls du eine Tabelle hast, wo der Durchschnitt gespeichert wird, z.B. articles
        safe_query("UPDATE plugins_articles SET rating = $avg_rating WHERE id = $itemID");
    }

    header("Location: index.php?site=articles&action=watch&id=$itemID");
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

        header("Location: index.php?site=articles&action=watch&id=$itemID");
        exit;
    } else {
        die("Ungültiger CSRF-Token oder fehlende Eingaben.");
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'deletecomment' && isset($_GET['id']) && is_numeric($_GET['id'])) {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();  // Session nur starten, wenn noch nicht aktiv
    }

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
        header("Location: $referer");
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

        $name = $category ? htmlspecialchars($category['name']) : 'Unbekannte Kategorie';

        $data_array = [
            'name' => $name,
            'title' => htmlspecialchars($article['title']),
            'id' => $article['category_id'],
            'title_categories' => $languageService->get('title_categories'),
            'categories' => $languageService->get('categories'),
            'category' => $languageService->get('category'),
        ];
        echo $tpl->loadTemplate("articles", "content_details_head", $data_array, 'plugin');

        // Views erhöhen
        safe_query("UPDATE plugins_articles SET views = views + 1 WHERE id = $id");

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

        // Bewertung anzeigen
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

        $username = '<a href="index.php?site=profile&amp;userID=' . $article['userID'] . '">
        <img src="' . getavatar($article['userID']) . '" class="img-fluid align-middle rounded-circle me-1" style="height: 23px; width: 23px;" alt="' . getusername($article['userID']) . '">
        <strong>' . getusername($article['userID']) . '</strong></a>';

        $link = $article['slug'] ? $languageService->get('link') . ': <a href="' . $article['slug'] . '" target="_blank">' . $article['slug'] . '</a>' : '';

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

            echo '<div class="mt-5"><h5>' . $languageService->get('comments') . '</h5><ul class="list-group">';
            while ($row = mysqli_fetch_array($comments)) {
                $deleteLink = '';
                $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
                $userID = $loggedin ? (int)$_SESSION['userID'] : 0;

                $canDelete = ($userID == $row['userID'] || has_role($userID, 'Admin'));

                if ($canDelete) {
                    $deleteLink = '<a href="index.php?site=articles&action=deletecomment&id=' . $row['commentID'] . '&ref=' . urlencode($_SERVER['REQUEST_URI']) . '&token=' . $_SESSION['csrf_token'] . '" class="btn btn-sm btn-danger ms-2" onclick="return confirm(\'Kommentar wirklich löschen?\')">' . $languageService->get('delete') . '</a>';
                }

                echo '<li class="list-group-item">
                        <strong>' . htmlspecialchars($row['username']) . '</strong><br>
                        ' . nl2br(htmlspecialchars($row['comment'])) . '
                        <div class="text-muted small">' . date('d.m.Y H:i', strtotime($row['date'])) . '</div>
                        <div>' . $deleteLink . '</div>
                      </li>';
            }
            echo '</ul></div>';
            $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
            $userID = $loggedin ? (int)$_SESSION['userID'] : 0;

            if ($loggedin) {
                echo '<form method="POST" action="index.php?site=articles&action=watch&id=' . $id . '" class="mt-4">
                    <textarea class="form-control" name="comment" rows="4" required></textarea>
                    <input type="hidden" name="id" value="' . $id . '">
                    <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                    <button type="submit" name="submit_comment" class="btn btn-success">Kommentar abschicken</button>
                </form>';
            } else {
                echo '<div class="alert alert-warning">' . $languageService->get('must_login_comment') . '</div>';
            }
        }
    } else {
        // Artikel nicht gefunden
        echo $languageService->get('article_not_found');
    }
}

elseif ($action == "show" && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $categoryID = (int)$_GET['id'];

    $cat_query = safe_query("SELECT * FROM plugins_articles_categories WHERE id = $categoryID");
    if (mysqli_num_rows($cat_query) === 0) {
        echo $languageService->get('no_categories');
        exit;
    }
    $category = mysqli_fetch_assoc($cat_query);
    $cat_name = htmlspecialchars($category['name']);
    $cat_description = htmlspecialchars($category['description']);

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = 6;
    $offset = ($page - 1) * $limit;

    $total_articles_result = safe_query("SELECT COUNT(*) AS total FROM plugins_articles WHERE is_active = 1 AND category_id = $categoryID");
    $total_articles_row = mysqli_fetch_assoc($total_articles_result);
    $total_articles = (int)$total_articles_row['total'];
    $total_pages = ceil($total_articles / $limit);

    $articles_result = safe_query("SELECT * FROM plugins_articles WHERE is_active = 1 AND category_id = $categoryID ORDER BY updated_at DESC LIMIT $offset, $limit");

    if (mysqli_num_rows($articles_result) > 0) {
        $monate = [
            1 => $languageService->get('jan'), 2 => $languageService->get('feb'),
            3 => $languageService->get('mar'), 4 => $languageService->get('apr'),
            5 => $languageService->get('may'), 6 => $languageService->get('jun'),
            7 => $languageService->get('jul'), 8 => $languageService->get('aug'),
            9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
            11 => $languageService->get('nov'), 12 => $languageService->get('dec')
        ];

        $data_array = [
            'title_categories' => $languageService->get('title_categories'),
            'category_name' => $cat_name,
            'category_description' => $cat_description
        ];
        echo $tpl->loadTemplate("articles", "category_show_head", $data_array, 'plugin');

        while ($article = mysqli_fetch_assoc($articles_result)) {
            $id = (int)$article['id'];
            $timestamp = (int)$article['updated_at'];
            $tag = date("d", $timestamp);
            $monat = (int)date("n", $timestamp);
            $year = date("Y", $timestamp);

            $monatname = $monate[$monat] ?? '';

            $banner_image = $article['banner_image'];
            $image = $banner_image ? "/includes/plugins/articles/images/article/" . $banner_image : "/includes/plugins/articles/images/no-image.jpg";

            $username = '<a href="index.php?site=profile&amp;userID=' . $article['userID'] . '">
            <img src="' . getavatar($article['userID']) . '" class="img-fluid align-middle rounded me-1" style="height: 23px; width: 23px;" alt="' . getusername($article['userID']) . '">
            <strong>' . getusername($article['userID']) . '</strong></a>';

            $translate = new multiLanguage($lang);
            $translate->detectLanguages($article['title']);
            $title = $translate->getTextByLanguage($article['title']);

            $maxTitleChars = 50;
            $short_title = $title;
            if (mb_strlen($short_title) > $maxTitleChars) {
                $short_title = mb_substr($short_title, 0, $maxTitleChars) . '...';
            }

            $article_catname = '<a data-toggle="tooltip" title="' . htmlspecialchars($category['description'] ?? '') . '">' . $cat_name . '</a>';

            $data_array = [
                'name'           => $article_catname,
                'title'          => htmlspecialchars($short_title),
                'tag'            => $tag,
                'monat'          => $monatname,
                'year'           => $year,
                'image'          => $image,
                'username'       => $username,
                'id'             => $id,
                'by'             => $languageService->get('by'),
                'read_more'      => $languageService->get('read_more'),
            ];

            echo $tpl->loadTemplate("articles", "content_all", $data_array, 'plugin');
        }

        echo $tpl->loadTemplate("articles", "content_all_foot", $data_array, 'plugin');

        if ($total_pages > 1) {
            echo '<nav><ul class="pagination justify-content-center">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i === $page) ? 'active' : '';
                echo '<li class="page-item ' . $active . '"><a class="page-link" href="index.php?site=articles&action=show&id=' . $categoryID . '&page=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
        }

    } else {
        echo $languageService->get('no_articles_in_category');
    }
}

elseif ($action == "") {

    // Kategorien laden und anzeigen
    $cats_result = safe_query("SELECT * FROM plugins_articles_categories ORDER BY sort_order ASC");
    if (mysqli_num_rows($cats_result) > 0) {

        $data_array = [
            'title_categories' => $languageService->get('title_categories'),
        ];

        echo $tpl->loadTemplate("articles", "category", $data_array, 'plugin');

        // Head-Bereich der Artikelliste
        echo $tpl->loadTemplate("articles", "content_all_head", $data_array, 'plugin');

        // Pagination vorbereiten
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $limit = 6;
        $offset = ($page - 1) * $limit;

        // Gesamtanzahl aktiver Artikel ermitteln
        $total_articles_result = safe_query("SELECT COUNT(*) AS total FROM plugins_articles WHERE is_active = 1");
        $total_articles_row = mysqli_fetch_assoc($total_articles_result);
        $total_articles = (int)$total_articles_row['total'];
        $total_pages = ceil($total_articles / $limit);

        // Artikel laden (nur aktive)
        $articles_result = safe_query("SELECT * FROM plugins_articles WHERE is_active = 1 ORDER BY updated_at DESC LIMIT $offset, $limit");

        if (mysqli_num_rows($articles_result) > 0) {

            // Monatsnamen in der Sprache laden
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

                $username = '<a href="index.php?site=profile&amp;userID=' . $article['userID'] . '">
                <img src="' . getavatar($article['userID']) . '" class="img-fluid align-middle rounded me-1" style="height: 23px; width: 23px;" alt="' . getusername($article['userID']) . '">
                <strong>' . getusername($article['userID']) . '</strong></a>';

                $translate = new multiLanguage($lang);

                $translate->detectLanguages($article['title']);
                $title = $translate->getTextByLanguage($article['title']);

                $maxTitleChars = 50;
                $short_title = $title;
                if (mb_strlen($short_title) > $maxTitleChars) {
                    $short_title = mb_substr($short_title, 0, $maxTitleChars) . '...';
                }

                $catID = (int)$article['category_id'];
                $cat_query = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $catID");
                $cat = mysqli_fetch_assoc($cat_query);

                $cat_name = htmlspecialchars($cat['name'] ?? '');
                $cat_description = htmlspecialchars($cat['description'] ?? '');

                $article_catname = '<a data-toggle="tooltip" title="' . $cat_description . '">' . $cat_name . '</a>';

                $data_array = [
                    'name'           => $article_catname,
                    'title'          => htmlspecialchars($short_title),
                    'tag'            => $tag,
                    'monat'          => $monatname,
                    'year'           => $year,
                    'image'          => $image,
                    'username'       => $username,
                    'id'             => $id,
                    'by'             => $languageService->get('by'),
                    'read_more'      => $languageService->get('read_more'),
                ];

                echo $tpl->loadTemplate("articles", "content_all", $data_array, 'plugin');
            }
        }

        // Footer-Bereich der Artikelliste
        echo $tpl->loadTemplate("articles", "content_all_foot", $data_array, 'plugin');

        // Pagination Links ausgeben, falls mehrere Seiten
        if ($total_pages > 1) {
            echo '<nav><ul class="pagination justify-content-center">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i === $page) ? 'active' : '';
                echo '<li class="page-item ' . $active . '"><a class="page-link" href="index.php?site=articles&page=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul></nav>';
        }
    } else {
        echo $languageService->get('no_categories');
    }
}
?>
