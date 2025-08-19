<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;
use nexpell\Database;

global $languageService, $_database;

$currentLang = $languageService->detectLanguage();
$languageService->readPluginModule('search');

$tpl = new Template();

// --- CONFIG: Style laden ---
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// --- HEAD ---
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => $languageService->get('subtitle'),
];
echo $tpl->loadTemplate("search", "head", $data_array, "plugin");

// --- Suchparameter ---
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';

// --- Suchformular ---
$data_array = [
    'placeholder' => $languageService->get('placeholder'),
    'button'      => $languageService->get('button'),
    'query'       => htmlspecialchars($q),
];
echo $tpl->loadTemplate("search", "quick", $data_array, "plugin");