<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('download');

// Style aus settings holen
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Download'
];
echo $tpl->loadTemplate("download", "head", $data_array, 'plugin');

$data_array = [
    'title' => $languageService->get('title'),
    'description' => $languageService->get('description'),
];

echo $tpl->loadTemplate("download", "content", $data_array, 'plugin');
