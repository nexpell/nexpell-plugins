<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('rules'); // Hinweis: neuer Sprachmodulname?

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('rules_title'),
    'subtitle' => 'Rules'
];
echo $tpl->loadTemplate("rules", "head", $data_array, "plugin");

// Anzahl aller aktiven Regeln zählen
$alle = safe_query("SELECT id FROM plugins_rules WHERE is_active = 1");
$gesamt = mysqli_num_rows($alle);

// Maximale Anzahl pro Seite (aus Einstellungen oder Standard 10)
$settings = safe_query("SELECT * FROM plugins_clan_rules_settings"); // Optional, anpassen wenn genutzt
$dn = mysqli_fetch_array($settings);
$max = !empty($dn['clan_rules']) ? (int)$dn['clan_rules'] : 10;

$pages = ceil($gesamt / $max);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, min($page, $pages));
$start = ($page - 1) * $max;

// Aktive Regeln holen
$ergebnis = safe_query(
    "SELECT * FROM plugins_rules WHERE is_active = 1 ORDER BY sort_order ASC LIMIT $start, $max"
);

if (mysqli_num_rows($ergebnis) > 0) {
    while ($ds = mysqli_fetch_array($ergebnis)) {
        $poster = '<a href="index.php?site=profile&amp;id=' . $ds['userID'] . '"><strong>' . getusername($ds['userID']) . '</strong></a>';

        $translate = new multiLanguage($lang);
        $translate->detectLanguages($ds['title']);
        $title = $translate->getTextByLanguage($ds['title']);
        $translate->detectLanguages($ds['text']);
        $text = $translate->getTextByLanguage($ds['text']);

        $date = !empty($ds['date']) ? date("d.m.Y", strtotime($ds['date'])) : '';

        $data_array = [
            'title'  => $title,
            'text'   => $text,
            'date'   => $date,
            'poster' => $poster,
            'info'   => $languageService->get('info'),
            'stand'  => $languageService->get('stand')
        ];

        echo $tpl->loadTemplate("rules", "main", $data_array, "plugin");
    }

    echo $tpl->renderPagination("index.php?site=rules", $page, $pages);
} else {
    echo '<div class="alert alert-info">' . $languageService->get('rules_no_entries') . '</div>';
}
?>

