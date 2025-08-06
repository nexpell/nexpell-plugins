<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('articles');

$tpl = new Template();
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style'] ?? '');

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Articles'
];

// Optional: Falls du Header ausgeben möchtest, kannst du es hier aktivieren
// echo $tpl->loadTemplate("articles", "head", $data_array, 'plugin');

// Hole die neuesten 5 aktiven Artikel, geordnet nach updated_at (Erstelldatum)
$articles_result = safe_query("SELECT * FROM plugins_articles WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 5");

if (mysqli_num_rows($articles_result)) {

    echo $tpl->loadTemplate("articles", "widget_content_articles_head", $data_array, 'plugin');

    // Monatsnamen in der Sprache laden
    $monate = [
        1 => $languageService->get('jan'), 2 => $languageService->get('feb'),
        3 => $languageService->get('mar'), 4 => $languageService->get('apr'),
        5 => $languageService->get('may'), 6 => $languageService->get('jun'),
        7 => $languageService->get('jul'), 8 => $languageService->get('aug'),
        9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
        11 => $languageService->get('nov'), 12 => $languageService->get('dec')
    ];

    while ($article = mysqli_fetch_array($articles_result)) {
        // Datum korrekt aus updated_at lesen
        $timestamp = (int)$article['updated_at'];
        if ($timestamp <= 0) {
            $timestamp = time(); // Fallback falls kein Datum vorhanden ist
        }

        $tag = date("d", $timestamp);
        $monat = date("n", $timestamp);
        $year = date("Y", $timestamp);

        $monatname = $monate[$monat] ?? '';

        // Kategorie laden
        $catID = (int)$article['category_id'];
        $cat_query = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $catID");
        $cat = mysqli_fetch_assoc($cat_query);

        $cat_name = htmlspecialchars($cat['name'] ?? '');
        $cat_description = htmlspecialchars($cat['description'] ?? '');

        $article_catname = '<a data-bs-toggle="tooltip" data-bs-title="' . $cat_description . '">' . $cat_name . '</a>';

        $question = $article['title'] ?? '';
        $answer = $article['content'] ?? '';
        $id = $article['id'] ?? 0;

        // Usernamen holen
        $username = '<a href="index.php?site=profile&amp;userID=' . $article['userID'] . '">
        <img src="' . getavatar($article['userID']) . '" class="img-fluid align-middle rounded-circle me-1" style="height: 23px; width: 23px;" alt="' . getusername($article['userID']) . '">
        <strong>' . getusername($article['userID']) . '</strong></a>';

        $translate = new multiLanguage($lang);
        $translate->detectLanguages($question);
        $question = $translate->getTextByLanguage($question);

        $settings = safe_query("SELECT * FROM plugins_articles_settings");
        $dn = mysqli_fetch_array($settings);

        $maxarticleschars_title = 20;
        if (mb_strlen($question) > $maxarticleschars_title) {
            $question = mb_substr($question, 0, $maxarticleschars_title) . '...';
        }

        $maxarticleschars_content = $dn['articleschars'] ?? 110;
        if (mb_strlen($answer) > $maxarticleschars_content) {
            $answer = mb_substr($answer, 0, $maxarticleschars_content) . '...';
        }

        $title = '<a href="index.php?site=articles&action=watch&id='.$id.'" data-toggle="tooltip" data-bs-html="true" title="'.htmlspecialchars($article['title']).'">'.htmlspecialchars($question).'</a>';

        $banner_image = $article['banner_image'];
        $image = $banner_image ? "/includes/plugins/articles/images/article/" . $banner_image : "/includes/plugins/articles/images/no-image.jpg";

        $data_array = [
            'title'      => $title,
            'text'       => $answer,
            'tag'        => $tag,
            'monat'      => $monatname,
            'year'       => $year,
            'username'   => $username,
            'name'       => $article_catname,
            'link'       => 'index.php?site=articles&action=watch&id='.$id,
            'image'      => $image,
            'id'         => $id,
            'by'         => $languageService->get('by'),
            'read_more'  => $languageService->get('read_more'),
        ];

        echo $tpl->loadTemplate("articles", "widget_content_content", $data_array, 'plugin');
    }
    echo $tpl->loadTemplate("articles", "widget_content_articles_foot", $data_array, 'plugin');

} else {
    echo $languageService->get('no_articles');
}
?>
