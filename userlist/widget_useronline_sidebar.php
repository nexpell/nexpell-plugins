<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('userlist');

$tpl = new Template();

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('userlist_title'),
    'subtitle' => 'User online'
];

echo $tpl->loadTemplate("userlist", "head", $data_array, "plugin");

// Einstellungen aus Plugin-Tabelle lesen
$settings = safe_query("SELECT * FROM plugins_userlist");
$ds_settings = mysqli_fetch_array($settings);
$maxusers = (int)$ds_settings['users_online'];

// Zeitspanne in Sekunden, wie lange ein Nutzer als "online" gilt (z.B. 5 Minuten)
$online_threshold = 300; 

// Aktuelle Zeitstempel
$now = time();

// Benutzer abrufen, sortiert nach letzter Aktivität (Spalte 'lastlogin' in users)
$ergebnis = safe_query("SELECT userID, username, lastlogin FROM users ORDER BY lastlogin DESC LIMIT " . $maxusers);

echo $tpl->loadTemplate("userlist", "useronline_head", [], "plugin");

while ($ds = mysqli_fetch_array($ergebnis)) {
    // lastlogin ist DATETIME, daher in Timestamp umwandeln
    $last_activity = strtotime($ds['lastlogin']);

    if (($now - $last_activity) <= $online_threshold) {
        $statuspic = '<span class="badge bg-success">' . $languageService->get('online') . '</span>';
        $last_active = $languageService->get('now_on');
    } else {
        $statuspic = '<span class="badge bg-danger">' . $languageService->get('offline') . '</span>';

        $diff = $now - $last_activity;
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);

        if ($hours > 1) {
            $hours_text = $hours . ' ' . $languageService->get('hours_and') . ' ';
        } elseif ($hours === 1) {
            $hours_text = $hours . ' ' . $languageService->get('hour_and') . ' ';
        } else {
            $hours_text = '';
        }
        $minutes_text = str_pad($minutes, 2, "0", STR_PAD_LEFT);

        $last_active = $languageService->get('was_online') . ': ' . $hours_text . $minutes_text . ' ' . $languageService->get('minutes');
    }

    $username = '<a href="index.php?site=profile&amp;id=' . (int)$ds['userID'] . '">' . htmlspecialchars($ds['username']) . '</a>';
    // Avatar prüfen
    $avatar = '';
    if ($getavatar = getavatar($ds['userID'])) {
        $avatar = './images/avatars/' . htmlspecialchars($getavatar);
    }

    $data_array = [
    	'statuspic'    => $statuspic,
        'username'    => $username,
        'last_active' => $last_active,
        'avatar'   => $avatar
    ];

    echo $tpl->loadTemplate("userlist", "useronline_content", $data_array, "plugin");
}

echo $tpl->loadTemplate("userlist", "useronline_foot", [], "plugin");

?>