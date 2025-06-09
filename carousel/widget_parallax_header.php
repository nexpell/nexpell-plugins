<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();

$filepath = $plugin_path . "images/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings"));

$ergebnis = safe_query("SELECT * FROM plugins_carousel_parallax");
if (mysqli_num_rows($ergebnis)) {
    while ($db = mysqli_fetch_array($ergebnis)) {
        $parallax_pic = $filepath . $db['parallax_pic']; // Kein '' nötig, einfache Verkettung reicht
        $parallax_height = $ds['parallax_height'];

        $data_array = [
            'parallax_pic'    => $parallax_pic,
            'parallax_height' => $parallax_height
        ];

        echo $tpl->loadTemplate("parallax_header", "content", $data_array, 'plugin');
    }
} else {
    echo '<div class="alert alert-danger" role="alert">' . $languageService->get('no_header') . '</div>';
}
