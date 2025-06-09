<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

// Sprache erkennen und Plugin-Sprachdatei laden
$lang = $languageService->detectLanguage();
$languageService->readPluginModule('userlist');

$tpl = new Template();

// Style aus DB lesen
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('lastregistered'),
    'subtitle' => $languageService->get('userlist')
];

echo $tpl->loadTemplate("userlist","head", $data_array, 'plugin');

// Letzte 5 registrierte Benutzer abrufen
$result = safe_query("SELECT * FROM users ORDER BY registerdate DESC LIMIT 5");

// Widget-Header ausgeben
echo $tpl->loadTemplate("userlist","widget_lastregistered_head", $data_array, 'plugin');

// Benutzerliste durchlaufen
while ($row = mysqli_fetch_array($result)) {
    
    $username = '<a href="index.php?site=profile&amp;id=' . (int)$row['userID'] . '">' . htmlspecialchars($row['username']) . '</a>';

    // Registerdate als DateTime-Objekt erzeugen (korrekt aus String)
    $register_date = new DateTime($row['registerdate']);

    // Heute auf Mitternacht setzen
    $today = new DateTime();
    $today->setTime(0, 0);

    // Differenz in Tagen (int mit Vorzeichen)
    $interval = (int)$register_date->diff($today)->format('%R%a');

    // Menschlich lesbares Anmeldedatum
    if ($interval === 0) {
        $register = $languageService->get('today');
    } elseif ($interval === 1) {
        $register = $languageService->get('yesterday');
    } elseif ($interval === -1) {
        $register = $languageService->get('tomorrow');
    } else {
        $register = $register_date->format('d.m.Y');
    }

    // Avatar prüfen
    $avatar = '';
    if ($getavatar = getavatar($row['userID'])) {
        $avatar = './images/avatars/' . htmlspecialchars($getavatar);
    }

    // Daten an Template übergeben
    $data_array = [
        'username' => $username,
        'register' => $register,
        'avatar'   => $avatar
    ];

    echo $tpl->loadTemplate("userlist","widget_lastregistered_content", $data_array, "plugin");
}

// Widget-Footer ausgeben
echo $tpl->loadTemplate("userlist","widget_lastregistered_foot", $data_array, "plugin");
?>
