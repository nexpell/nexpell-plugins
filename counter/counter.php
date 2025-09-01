<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService,$_database;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('counter');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('title'),
        'subtitle' => 'Counter'
    ];
    
    echo $tpl->loadTemplate("counter", "head", $data_array, 'plugin');

// --- Besucherstatistik ---
$today_date   = date('Y-m-d');
$month_start  = date('Y-m-01');
$now          = date('Y-m-d H:i:s');
$ten_minutes_ago = date('Y-m-d H:i:s', time() - 600);

// Heute
$res_today = $_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics WHERE DATE(created_at) = '$today_date'");
$today = (int)$res_today->fetch_assoc()['cnt'];

// Gestern
$res_yesterday = $_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics WHERE DATE(created_at) = DATE_SUB('$today_date', INTERVAL 1 DAY)");
$yesterday = (int)$res_yesterday->fetch_assoc()['cnt'];

// Dieser Monat
$res_month = $_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics WHERE DATE(created_at) >= '$month_start'");
$month = (int)$res_month->fetch_assoc()['cnt'];

// Gesamt
$res_total = $_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics");
$total = (int)$res_total->fetch_assoc()['cnt'];

// Online (letzte 10 Minuten)
$res_online = $_database->query("SELECT COUNT(DISTINCT ip_hash) AS cnt FROM visitor_statistics WHERE created_at >= '$ten_minutes_ago'");
$online = (int)$res_online->fetch_assoc()['cnt'];

// Maximal online gleichzeitig (optional, Beispielwert)
$maxonline = 100;

// --- User-Statistik dynamisch aus users & user_profiles ---
$res_users = $_database->query("
    SELECT 
        COUNT(u.userID) AS registered_users,
        MIN(up.birthday) AS youngest_birthdate,
        MAX(up.birthday) AS oldest_birthdate
    FROM users u
    LEFT JOIN user_profiles up ON u.userID = up.userID
    WHERE u.is_active = 1
");
$user_data = $res_users->fetch_assoc();

$registered_users = (int) $user_data['registered_users'];

// Alter berechnen
$youngest_user = $user_data['youngest_birthdate'] ? date_diff(date_create($user_data['youngest_birthdate']), date_create('now'))->y : 0;
$oldest_user = $user_data['oldest_birthdate'] ? date_diff(date_create($user_data['oldest_birthdate']), date_create('now'))->y : 0;

// Monatsstatistik pro Tag
$res_month_days = $_database->query("SELECT DATE(created_at) AS day, COUNT(*) AS visits FROM visitor_statistics WHERE created_at >= '$month_start' GROUP BY day ORDER BY day ASC");
$month_stats = '';
$month_max = 0;

// Maximalwert für Monatsprogressbar
while ($row = $res_month_days->fetch_assoc()) {
    if ($row['visits'] > $month_max) $month_max = $row['visits'];
}

// Zweiter Durchlauf für die Balken
$res_month_days->data_seek(0); // Reset result pointer
while ($row = $res_month_days->fetch_assoc()) {
    $visits = $row['visits'];
    $prozent = $month_max > 0 ? ($visits * 100 / $month_max) : 0;
    $month_stats .= '<li class="list-group-item">
        <div class="row">
            <div class="col-2">' . $row['day'] . ' <span class="badge bg-secondary">' . $visits . '</span></div>
            <div class="col-9">
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" aria-valuenow="' . round($prozent) . '" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <div class="col-1">' . round($prozent) . '%</div>
        </div>
    </li>';
}



$data_array = [
    // Besucherzahlen
    'visits_title'      => $languageService->get('visits_title'),
    'visits_today'      => $languageService->get('visits_today'),
    'today'             => $today,
    'visits_yesterday'  => $languageService->get('visits_yesterday'),
    'yesterday'         => $yesterday,
    'visits_this_month' => $languageService->get('visits_this_month'),
    'month'             => $month,
    'visits_total'      => $languageService->get('visits_total'),
    'total'             => $total,

    // Online
    'online_title'      => $languageService->get('online_title'),
    'online_now'        => $languageService->get('online_now'),
    'online'            => $online,
    'online_maximum'    => $languageService->get('online_maximum'),
    'maxonline'         => $maxonline,

    // User-Statistik
    'user_stats_title'  => $languageService->get('user_stats_title'),
    'user_registered'   => $languageService->get('user_registered'),
    'registered_users'  => $registered_users,
    'user_youngest'     => $languageService->get('user_youngest'),
    'youngest_user'     => $youngest_user,
    'user_oldest'       => $languageService->get('user_oldest'),
    'oldest_user'       => $oldest_user,
    'unit_years'        => $languageService->get('unit_years'),

    // Monatsstatistik
    'month_stats_title' => $languageService->get('month_stats_title'),
    'month_stats'       => $month_stats
];

echo $tpl->loadTemplate("counter", "main", $data_array, 'plugin');
?>
