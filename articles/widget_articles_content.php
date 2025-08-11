<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

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

#echo $tpl->loadTemplate("articles", "head", $data_array, 'plugin');

$qry = safe_query("SELECT * FROM plugins_articles WHERE id!=0 ORDER BY id DESC LIMIT 0,5");
$anz = mysqli_num_rows($qry);

if ($anz) {

    echo $tpl->loadTemplate("articles", "widget_content_articles_head", $data_array, 'plugin');

    $n = 1;
    while ($ds = mysqli_fetch_array($qry)) {
        $dateString = $ds['date'] ?? '';
        $timestamp = strtotime($dateString) ?: time();  // fallback auf aktuelles Datum
        $tag = date("d", $timestamp);
        $monat = date("n", $timestamp);
        $year = date("Y", $timestamp);

        $monate = [
            1 => $languageService->get('jan'), 2 => $languageService->get('feb'),
            3 => $languageService->get('mar'), 4 => $languageService->get('apr'),
            5 => $languageService->get('may'), 6 => $languageService->get('jun'),
            7 => $languageService->get('jul'), 8 => $languageService->get('aug'),
            9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
            11 => $languageService->get('nov'), 12 => $languageService->get('dec')
        ];
        $monatname = $monate[$monat] ?? '';

        $question = $ds['title'] ?? '';
        $question_lang = $question;
        $answer = $ds['content'] ?? '';
        $id = $ds['id'] ?? 0;

        // username holen (z.B. aus userID)
        #$username = getusername($ds['userID']);

        $banner_image = $ds['banner_image'];
        $image = $banner_image ? "/includes/plugins/articles/images/article/" . $banner_image : "/includes/plugins/articles/images/no-image.jpg";

        $translate = new multiLanguage($lang);
        $translate->detectLanguages($question);
        $question = $translate->getTextByLanguage($question);

        $settings = safe_query("SELECT * FROM plugins_articles_settings");
        $dn = mysqli_fetch_array($settings);

        $maxarticleschars = 20;
        if (mb_strlen($question ?? '') > $maxarticleschars) {
            $question = mb_substr($question, 0, $maxarticleschars) . '...';
        }

        $maxarticleschars = $dn['articleschars'] ?? 110;
        if (mb_strlen($answer ?? '') > $maxarticleschars) {
            $answer = mb_substr($answer, 0, $maxarticleschars) . '...';
        }

        $articleUrl = SeoUrlHandler::convertToSeoUrl('index.php?site=articles&action=watch&id=' . intval($id));
        #$title = '<a href="' . htmlspecialchars($articleUrl) . '">' . htmlspecialchars($question) . '</a>';
        $translate = new multiLanguage($lang);
        $title = $translate->getTextByLanguage($ds['title']);


        $catID = (int)$ds['category_id'];
        $cat_query = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $catID");
        $cat = mysqli_fetch_assoc($cat_query);

        $cat_name = htmlspecialchars($cat['name'] ?? '');

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


        $article_catname = '<a href="' . 
            htmlspecialchars(SeoUrlHandler::convertToSeoUrl("index.php?site=articles&action=show&id=$catID")) . 
            '"><strong style="font-size: 12px">' . 
            htmlspecialchars($cat_name) . 
            '</strong></a>';
        $url_watch = "index.php?site=articles&action=watch&id=" . intval($id);
        $url_watch_seo = SeoUrlHandler::convertToSeoUrl($url_watch);

        $data_array = [
            'name'     => $article_catname,
            'title'    => $title,
            'text'     => $answer,
            'tag'      => $tag,
            'monat'    => $monatname,
            'year'     => $year,
            'username' => $username,
            'image'    => $image,
			'url_watch' => $url_watch_seo,
            'by'       => $languageService->get('by'),
            'read_more'      => $languageService->get('read_more'),
        ];

        echo $tpl->loadTemplate("articles", "widget_content_content", $data_array, 'plugin');
        $n++;
    }
    echo $tpl->loadTemplate("articles", "widget_content_articles_foot", $data_array, 'plugin');
} else {
    echo $languageService->get('no_articles');
}
?>
