<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $languageService;
$languageService->readPluginModule('userlist');

$tpl = new Template();

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('userlist_title'),
    'subtitle' => 'User online'
];
echo $tpl->loadTemplate("userlist", "head", $data_array, "plugin");

// === Einstellungen aus Plugin-Tabelle laden ===
$settingsResult = safe_query("SELECT * FROM plugins_userlist_settings WHERE id=1");
$settings = mysqli_fetch_assoc($settingsResult);

$maxUsers     = (int)($settings['users_widget_count'] ?? 5);
$widgetSort   = $settings['widget_sort'] ?? 'lastlogin';
$showOnlyOnline = (int)($settings['widget_show_online'] ?? 1);

// === Sortierung festlegen ===
$orderBy = match($widgetSort) {
    'username'    => 'username ASC',
    'registerdate'=> 'registerdate DESC',
    default       => 'lastlogin DESC'
};

// === WHERE-Bedingung (online oder alle) ===
$where = $showOnlyOnline ? "WHERE is_online = 1" : "";

$ergebnis = safe_query("
    SELECT userID, username, lastlogin, is_online
    FROM users
    $where
    ORDER BY $orderBy
    LIMIT $maxUsers
");

echo $tpl->loadTemplate("userlist", "useronline_head", [], "plugin");

// === Anzahl Zeilen ermitteln ===
$numRows = mysqli_num_rows($ergebnis);

if ($numRows === 0) {
    if ($showOnlyOnline) {
        echo '<div class="alert alert-info text-center">'
           . $languageService->get('no_users_online')
           . '</div>';
    } else {
        echo '<div class="alert alert-warning text-center">'
           . $languageService->get('no_users_found')
           . '</div>';
    }
} else {
    while ($ds = mysqli_fetch_array($ergebnis)) {
        $lastActivity = strtotime($ds['lastlogin']);

        if ((int)$ds['is_online'] === 1) {
            $statuspic = '<span class="badge bg-success">' . $languageService->get('online') . '</span>';
            $lastActiveText = $languageService->get('now_on') 
                . ' (<span class="online-time" data-start="' . $lastActivity . '"></span>)';
        } else {
            $statuspic = '<span class="badge bg-danger">' . $languageService->get('offline') . '</span>';
            $diff    = time() - $lastActivity;
            $days    = floor($diff / 86400); // 1 Tag = 86400 Sekunden
            $hours   = floor(($diff % 86400) / 3600);
            $minutes = floor(($diff % 3600) / 60);

            $timeParts = [];

            if ($days > 0) {
                $timeParts[] = $days . ' ' . $languageService->get($days === 1 ? 'widget_day' : 'widget_days');
            }
            if ($hours > 0) {
                $timeParts[] = $hours . ' ' . $languageService->get($hours === 1 ? 'widget_hour' : 'widget_hours');
            }
            if ($minutes > 0 && $days === 0) { 
                // Minuten nur anzeigen, wenn weniger als 1 Tag vergangen ist
                $timeParts[] = $minutes . ' ' . $languageService->get($minutes === 1 ? 'widget_minute' : 'widget_minutes');
            }

            $timeText = implode(' ', $timeParts);

            $lastActiveText = $languageService->get('widget_was_online') . ': ' 
                . date("d.m.Y - H:i", $lastActivity) 
                . '<br>(' . $languageService->get('widget_ago') . ' ' . $timeText . ')';


        }

        $username = '<a href="' . SeoUrlHandler::convertToSeoUrl(
            'index.php?site=profile&id=' . (int)$ds['userID']
        ) . '">' . htmlspecialchars($ds['username']) . '</a>';

        $avatar = '';
        if ($getavatar = getavatar($ds['userID'])) {
            $avatar = htmlspecialchars($getavatar);
        }

        $data_array = [
            'statuspic'   => $statuspic,
            'username'    => $username,
            'last_active' => $lastActiveText,
            'avatar'      => $avatar
        ];

        echo $tpl->loadTemplate("userlist", "useronline_content", $data_array, "plugin");
    }
}

echo $tpl->loadTemplate("userlist", "useronline_foot", [], "plugin");

?>
