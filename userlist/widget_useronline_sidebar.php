<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $languageService;

$lang = $languageService->detectLanguage();
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

// Einstellungen aus Plugin-Tabelle lesen
// Einstellungen aus Plugin-Tabelle lesen
$settings = safe_query("SELECT * FROM plugins_userlist");
$ds_settings = mysqli_fetch_array($settings);
$maxusers = (int)$ds_settings['users_online'];

// Aktuelle Zeitstempel
$now = time();

// Benutzer abrufen, sortiert nach letzter Aktivität
$ergebnis = safe_query("
    SELECT userID, username, lastlogin, is_online 
    FROM users 
    ORDER BY lastlogin DESC 
    LIMIT " . $maxusers
);

echo $tpl->loadTemplate("userlist", "useronline_head", [], "plugin");

while ($ds = mysqli_fetch_array($ergebnis)) {
    $isOnline = (int)$ds['is_online']; // 1 oder 0 aus DB
    $last_activity = strtotime($ds['lastlogin']);

    if ($isOnline === 1) {
        $statuspic = '<span class="badge bg-success">' . $languageService->get('online') . '</span>';

        // Startzeit als Data-Attribut für JS
        $last_active = $languageService->get('now_on') 
            . ' (<span class="online-time" data-start="' . $last_activity . '"></span>)';
    } else {
        // Offline
        $statuspic = '<span class="badge bg-danger">' . $languageService->get('offline') . '</span>';

        // Zeit seit letztem Login
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

        // Letzte Aktivität
        $last_active = $languageService->get('was_online') . ': ' 
            . date("d.m.Y - H:i", $last_activity) 
            . ' <br>(' . $languageService->get('ago') . '' . $hours_text . $minutes_text . ' ' . $languageService->get('minutes') . ')';
    }

    $username = '<a href="' . SeoUrlHandler::convertToSeoUrl(
        'index.php?site=profile&id=' . (int)$ds['userID']
    ) . '">' . htmlspecialchars($ds['username']) . '</a>';

    // Avatar prüfen
    $avatar = '';
    if ($getavatar = getavatar($ds['userID'])) {
        $avatar = htmlspecialchars($getavatar);
    }

    $data_array = [
        'statuspic'    => $statuspic,
        'username'     => $username,
        'last_active'  => $last_active,
        'avatar'       => $avatar
    ];

    echo $tpl->loadTemplate("userlist", "useronline_content", $data_array, "plugin");
}

echo $tpl->loadTemplate("userlist", "useronline_foot", [], "plugin");
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    function updateOnlineTimes() {
        document.querySelectorAll(".online-time").forEach(function(el) {
            let start = parseInt(el.getAttribute("data-start")) * 1000;
            let diff = Date.now() - start;

            let hours = Math.floor(diff / (1000 * 60 * 60));
            let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((diff % (1000 * 60)) / 1000);

            let timeStr = 
                String(hours).padStart(2, '0') + ":" +
                String(minutes).padStart(2, '0') + ":" +
                String(seconds).padStart(2, '0');

            el.textContent = timeStr;
        });
    }
    updateOnlineTimes();
    setInterval(updateOnlineTimes, 1000);
});
</script>
