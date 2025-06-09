<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('articles');

use webspell\AccessControl;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}




$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('title'),
        'subtitle' => 'About'
    ];
    
    echo $tpl->loadTemplate("articles", "head", $data_array, 'plugin');

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}

if ($action == "show" && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
    $limit = 6;
    $offset = ($page - 1) * $limit;

    // Kategorie laden
    $getcat = safe_query("SELECT * FROM plugins_articles_categories WHERE id='$id'");
    $ds = mysqli_fetch_array($getcat);
    $name = $ds['name'];

    // Gesamtanzahl Artikel für Paginierung
    $total_articles_query = safe_query("SELECT COUNT(*) as total FROM plugins_articles WHERE id='$id' AND is_active ='1'");
    $total_articles_result = mysqli_fetch_array($total_articles_query);
    $total_articles = (int)$total_articles_result['total'];
    $total_pages = ceil($total_articles / $limit);

    // Artikel laden
    $ergebnis = safe_query("SELECT * FROM plugins_articles WHERE id='$id' AND is_active ='1' ORDER BY updated_at DESC LIMIT $offset, $limit");

    $data_array = [
        'name'    => $name,
        'title' => $languageService->get('title'),
        'title_categories' => $languageService->get('title_categories'),
        'categories' => $languageService->get('categories'),
        'category' => $languageService->get('category'),
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
            $timestamp = strtotime($ds['updated_at']);
            $tag = date("d", $timestamp);
            $monat = date("n", $timestamp);
            $year = date("Y", $timestamp);
            $monatname = $monate[$monat];
            $username = getusername($ds['userID']);

            $banner_image = $ds['banner_image'];
            $image = $banner_image ? "/includes/plugins/articles/images/article/" . $banner_image : "/includes/plugins/articles/images/no-image.jpg";

            // Übersetzung laden
            $translate = new multiLanguage($lang);
            $title = $translate->getTextByLanguage($ds['title']);

            // Optionale Kürzung
            $maxblogchars = 15;
            $short_content = (mb_strlen($title) > $maxblogchars)
                ? mb_substr($title, 0, $maxblogchars) . '...'
                : $title;
            $id = (int)$ds['id'];

            $data_array = [
                'name' => $name,
                'title' => $title,
                'username' => $username,
                'image' => $image,
                'tag' => $tag,
                'monat' => $monatname,
                'year' => $year,
                'id'             => $id,
                'lang_rating' => $languageService->get('rating'),
                'lang_votes' => $languageService->get('votes'),
                'link' => $languageService->get('link'),
                'info' => $languageService->get('info'),
                'stand' => $languageService->get('stand'),
                'by' => $languageService->get('by'),
                'on' => $languageService->get('on'),
            ];

            echo $tpl->loadTemplate("articles", "details", $data_array, 'plugin');
        }

        echo $tpl->loadTemplate("articles", "content_all_foot", $data_array, 'plugin');

        // Pagination
        if ($total_pages > 1) {
            echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-4">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i == $page) ? ' active' : '';
                echo '<li class="page-item' . $active . '">
                        <a class="page-link" href="index.php?site=articles&action=show&id=' . $id . '&page=' . $i . '">' . $i . '</a>
                      </li>';
            }
            echo '</ul></nav>';
        }
    } else {
        echo $plugin_language['no_articles'] . '<br><br>[ <a href="index.php?site=articles" class="alert-article">' . $plugin_language['go_back'] . '</a> ]';
    }
}



$plugin_name = 'articles'; // Plugin-Name für die globale Tabelle

// Rating speichern
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_rating'], $_POST['rating'], $_POST['itemID'])
) {
    $plugin = 'articles'; // Name des Plugins, hier 'articles'
    $rating = (int)$_POST['rating'];
    $itemID = (int)$_POST['itemID'];

    if (!$userID) {
        die('Du musst eingeloggt sein, um zu bewerten.');
    }

    if ($rating < 0 || $rating > 10) {
        die('Bitte gib eine Bewertung zwischen 0 und 10 ab.');
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
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

        safe_query("INSERT INTO comments (plugin, itemID, userID, comment, date, parentID, modulname) VALUES ('$plugin_name', $itemID, $userID, '$comment', NOW(), 0, '$plugin_name')");

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

    // Simple slugdecode Funktion (bitte ggf. anpassen oder deine eigene verwenden)
    function slugdecode(string $slug): string {
        return urldecode(str_replace('-', ' ', $slug));
    }

    $commentID = (int)$_GET['id'];
    $referer = isset($_GET['ref']) ? slugdecode($_GET['ref']) : 'index.php?site=articles';

    if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        die('Ungültiger CSRF-Token.');
    }

    // Kommentar aus der comments Tabelle löschen
    $res = safe_query("DELETE FROM comments WHERE commentID = $commentID");

    if ($res) {
        header("Location: $referer");
        exit();
    } else {
        die('Fehler beim Löschen des Kommentars.');
    }
}






// Bewertung speichern (falls in deinem Originalcode noch da, sonst ergänzen)

if ($action == "watch" && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pluginName = 'articles';

    $articleQuery = safe_query("SELECT * FROM plugins_articles WHERE id = $id AND is_active = 1");
    if (mysqli_num_rows($articleQuery)) {
        $article = mysqli_fetch_array($articleQuery);

        $categoryQuery = safe_query("SELECT * FROM plugins_articles_categories WHERE id = " . (int)$article['category_id']);
        $category = mysqli_fetch_array($categoryQuery);

        $data_array = [
            'name' => htmlspecialchars($category['name']),
            'title' => htmlspecialchars($article['title']),
            'id' => $article['id'],
            'title_categories' => $languageService->get('title_categories'),
            'categories' => $languageService->get('categories'),
            'category' => $languageService->get('category'),
        ];
        echo $tpl->loadTemplate("articles", "content_details_head", $data_array, 'plugin');

        // Views erhöhen
        safe_query("UPDATE plugins_articles SET views = views + 1 WHERE id = $id");

        // Bewertung prüfen
        $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
        $userID = $loggedin ? (int)$_SESSION['userID'] : 0;

        $hasRated = false;
        if ($loggedin) {
            $check = safe_query("SELECT * FROM ratings WHERE plugin = '$pluginName' AND itemID = $id AND userID = $userID");
            $hasRated = mysqli_num_rows($check) > 0;
        }

        // Bewertungsformular
        if ($loggedin) {
            if ($hasRated) {
                $rateform = '<p><em>' . $languageService->get('you_have_already_rated') . '</em></p>';
            } else {
                $rateform = '<form method="post" action="" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label for="rating" class="form-label">' . $languageService->get('rate_now') . '</label>
                    </div>
                    <div class="col-auto">
                        <select name="rating" class="form-select">';
                for ($i = 0; $i <= 10; $i++) {
                    $rateform .= '<option value="' . $i . '">' . $i . '</option>';
                }
                $rateform .= '</select>
                        <input type="hidden" name="plugin" value="' . $pluginName . '">
                        <input type="hidden" name="itemID" value="' . $id . '">
                        <input type="hidden" name="submit_rating" value="1">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">' . $languageService->get('rate') . '</button>
                    </div>
                </form>';
            }
        } else {
            $rateform = '<p><em>' . $languageService->get('rate_have_to_reg_login') . '</em></p>';
        }

        // Bewertung anzeigen
        $r = safe_query("SELECT AVG(rating) AS avg_rating, COUNT(ratingID) AS votes FROM ratings WHERE plugin = 'articles' AND itemID = $id");
        $r = mysqli_fetch_assoc($r);
        $avg_rating = round($r['avg_rating'] ?? 0);
        $votes = (int)($r['votes'] ?? 0);
        $ratingpic = '<span title="' . $avg_rating . '/10">'
            . str_repeat('<img src="/includes/plugins/articles/images/rating_1.png" width="21" height="21" alt="">', $avg_rating)
            . str_repeat('<img src="/includes/plugins/articles/images/rating_0.png" width="21" height="21" alt="">', 10 - $avg_rating)
            . '</span>';

        $image = $article['banner_image'] ? "includes/plugins/articles/images/article/{$article['banner_image']}" : "includes/plugins/articles/images/no-image.jpg";
        $username = '<a href="index.php?site=profile&amp;userID=' . $article['userID'] . '"><strong>' . getusername($article['userID']) . '</strong></a>';
        $link = $article['slug'] ? '<a href="' . $article['slug'] . '" target="_blank">' . $article['slug'] . '</a>' : $languageService->get('no_link');

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


                // Nur anzeigen, wenn aktueller User = Autor oder Admin
                if ($userID == $row['userID']) {
                $canDelete = ($userID == $row['userID'] || has_role($userID, 'Admin'));
                
                    if ($canDelete) {    
                        $deleteLink = '<a href="index.php?site=articles&action=deletecomment&id=' . $row['commentID'] . '&ref=' . urlencode($_SERVER['REQUEST_URI']) . '&token=' . $_SESSION['csrf_token'] . '" class="btn btn-sm btn-danger ms-2" onclick="return confirm(\'Kommentar wirklich löschen?\')">' . $languageService->get('delete') . '</a>';
                    } else {
                        $deleteLink = '';
                    }
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

            // Kommentarformular
            if ($loggedin) {
                echo '<form method="POST" action="index.php?site=articles&action=watch&id=' . $id . '" class="mt-4">
                    <textarea class="form-control" name="comment" rows="4" required></textarea>
                    <input type="hidden" name="id" value="' . $id . '">
                    <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                    <button type="submit" name="submit_comment" class="btn btn-success">Kommentar abschicken</button>
                </form>';
            } else {
                echo '<p><em>' . $languageService->get('must_login_comment') . '</em></p>';
            }
        }
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

                // username holen (z.B. aus userID)
                $username = getusername($article['userID']);

                // Übersetzung (angenommen, dein multiLanguage-Objekt funktioniert so)
                $translate = new multiLanguage($lang);

                $translate->detectLanguages($article['title']);
                $title = $translate->getTextByLanguage($article['title']);

                // Optional kürzen (Titel auf max 50 Zeichen z.B.)
                $maxTitleChars = 50;
                $short_title = $title;
                if (mb_strlen($short_title) > $maxTitleChars) {
                    $short_title = mb_substr($short_title, 0, $maxTitleChars) . '...';
                }

                // Kategorie-Name laden
                $catID = (int)$article['category_id'];
                $cat_query = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $catID");
                $cat = mysqli_fetch_assoc($cat_query);

                $article_catname = '<a data-toggle="tooltip" title="' . htmlspecialchars($cat['description']) . '" href="index.php?site=articles&action=show&category_id=' . $catID . '"><strong style="font-size: 16px">' . htmlspecialchars($cat['name']) . '</strong></a>';

                $data_array = [
                    'name' => $article_catname,
                    'title'          => htmlspecialchars($short_title),
                    'tag'            => $tag,
                    'monat'          => $monatname,
                    'year'           => $year,
                    'image'          => $image,
                    'username'       => $username,
                    'id'             => $id,
                    'by'             => $languageService->get('by'),
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
