<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();

GLOBAL $theme_name;

$filepath = $plugin_path . "images/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings"));

$ergebnis = safe_query("SELECT * FROM plugins_carousel_agency");
if (mysqli_num_rows($ergebnis)) {
    $i = 1;
    while ($db = mysqli_fetch_array($ergebnis)) {
        $agency_pic = $filepath . $db['agency_pic'];
        $agency_height = $ds['agency_height'];
        $description = $db['description'];
        $title = $db['title'];
        $link_raw = $db['link'];

        // Link korrekt einbauen
        if (!empty($link_raw)) {
            $link = '<a data-aos="fade-up" data-aos-delay="200" href="' . $link_raw . '" class="btn-get-started scrollto">' . $languageService->get('read_more') . '</a>';
        } else {
            $link = '';
        }

        // Mehrsprachigkeit
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($title);
        $title = $translate->getTextByLanguage($title);
        $translate->detectLanguages($description);
        $description = $translate->getTextByLanguage($description);

        // Template-Daten
        $data_array = [
            'agency_pic'    => $agency_pic,
            'agency_height' => $agency_height,
            'title'         => $title,
            'link'          => $link,
            'description'   => $description,
            'theme_name'    => $theme_name
        ];

        echo $tpl->loadTemplate("agency_header", "content", $data_array, "plugin");
        $i++;
    }
} else {
    echo '<div class="alert alert-danger" role="alert">' . $languageService->get('no_header') . '</div>';
}
