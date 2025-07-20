<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('about');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('info_title'),
        'subtitle' => 'Info'
    ];
    
    echo $tpl->loadTemplate("info", "head", $data_array, 'plugin');

    echo $tpl->loadTemplate("info", "content", [], 'plugin');
?>



