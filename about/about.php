<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('about');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('title'),
        'subtitle' => 'About'
    ];
    
    echo $tpl->loadTemplate("about", "head", $data_array, 'plugin');

// Daten aus der Datenbank holen
$ergebnis = safe_query("SELECT * FROM plugins_about");

if (mysqli_num_rows($ergebnis)) {
    $ds = mysqli_fetch_array($ergebnis);
    $translate = new multiLanguage($lang);

    // Titel und Abschnitte übersetzen
    $translate->detectLanguages($ds['title']);
    $title = $translate->getTextByLanguage($ds['title']);

    $translate->detectLanguages($ds['intro']);
    $intro = $translate->getTextByLanguage($ds['intro']);

    $translate->detectLanguages($ds['history']);
    $history = $translate->getTextByLanguage($ds['history']);

    $translate->detectLanguages($ds['core_values']);
    $core_values = $translate->getTextByLanguage($ds['core_values']);

    $translate->detectLanguages($ds['team']);
    $team = $translate->getTextByLanguage($ds['team']);

    $translate->detectLanguages($ds['cta']);
    $cta = $translate->getTextByLanguage($ds['cta']);

    

    // CONTENT-Template (mit allen Abschnitten)
    $data_array = [
        'title' => $title,
        'intro' => $intro,
        'history' => $history,
        'core_values' => $core_values,
        'team' => $team,
        'cta' => $cta,
        'image_intro'  => '/includes/plugins/about/images/intro.jpg',
        'image_history'=> '/includes/plugins/about/images/history.jpg',
        'image_team'   => '/includes/plugins/about/images/team.jpg'
    ];

    echo $tpl->loadTemplate("about", "content", $data_array, 'plugin');

} else {
    // Fallback wenn keine Daten vorhanden
    $data_array = [
        'title' => $languageService->get('title'),
        'subtitle' => 'About Us'
    ];
    
    echo $tpl->loadTemplate("about", "head", $data_array, 'plugin');
    echo '<p>' . $languageService->get('no_about') . '</p>';
}
?>
