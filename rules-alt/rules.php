<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('clan_rules');

// Action auslesen
$action = $_GET['action'] ?? '';

if ($action == "show") {
    $clan_rulesID = isset($_GET['clan_rulesID']) ? (int)$_GET['clan_rulesID'] : 0;

    if ($clan_rulesID > 0) {
        $config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
        $class = htmlspecialchars($config['selected_style']);

        // Header-Daten
        $data_array = [
            'class'    => $class,
            'title'    => $languageService->get('clan_rules'),
            'subtitle' => 'Clan Rules'
        ];
        echo $tpl->loadTemplate("clan_rules", "head", $data_array, "plugin");

        $get = safe_query("SELECT * FROM plugins_clan_rules WHERE clan_rulesID='$clan_rulesID' LIMIT 1");
        if ($ds = mysqli_fetch_array($get)) {
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

            echo $tpl->loadTemplate("clan_rules", "content_area", $data_array, "plugin");
        } else {
            echo $languageService->get('no_clan_rules');
        }

        echo $tpl->loadTemplate("clan_rules", "foot", [], "plugin");

    } else {
        echo $languageService->get('no_clan_rules');
    }

} else {
    // Liste anzeigen
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    $config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
    $class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('clan_rules'),
        'subtitle' => 'Clan Rules'
    ];
    echo $tpl->loadTemplate("clan_rules", "head", $data_array, "plugin");

    $alle = safe_query("SELECT clan_rulesID FROM plugins_clan_rules WHERE displayed = '1'");
    $gesamt = mysqli_num_rows($alle);

    $settings = safe_query("SELECT * FROM plugins_clan_rules_settings");
    $dn = mysqli_fetch_array($settings);
    $max = !empty($dn['clan_rules']) ? (int)$dn['clan_rules'] : 1;

    $pages = ceil($gesamt / $max);
    $page = max(1, min($page, $pages));
    $start = ($page - 1) * $max;

    $ergebnis = safe_query("SELECT * FROM plugins_clan_rules WHERE displayed = '1' ORDER BY `sort` LIMIT $start, $max");

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

            echo $tpl->loadTemplate("clan_rules", "content_area", $data_array, "plugin");
        }

        echo $tpl->renderPagination("index.php?site=clan_rules", $page, $pages);

    } else {
        echo $languageService->get('no_clan_rules');
    }

    #echo $tpl->loadTemplate("clan_rules", "foot", [], "plugin");
}
?>
