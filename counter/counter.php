<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService, $_database;

// Sprache ermitteln
$lang = $languageService->detectLanguage();
$languageService->readPluginModule('counter');

// Style aus DB
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Counter'
];
echo $tpl->loadTemplate("counter", "head", $data_array, 'plugin');






function getVisitorCounter(mysqli $_database): array {
    $bot_condition = getBotCondition(); // deine bestehende Funktion
    $today_date    = date('Y-m-d');
    $yesterday     = date('Y-m-d', strtotime('-1 day'));
    $month_start   = date('Y-m-01');
    $five_minutes_ago = time() - 300;

    // Heute (Hits aus daily_counter)
    $today_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
        WHERE DATE(date) = '$today_date'
    ")->fetch_assoc()['hits'];

    // Gestern
    $yesterday_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
        WHERE DATE(date) = '$yesterday'
    ")->fetch_assoc()['hits'];

    // Monat
    $month_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
        WHERE date >= '$month_start'
    ")->fetch_assoc()['hits'];

    // Gesamt
    $total_hits = (int)$_database->query("
        SELECT SUM(hits) AS hits
        FROM visitor_daily_counter
    ")->fetch_assoc()['hits'];

    // Online (letzte 5 Minuten, Bots raus)
    $online_visitors = (int)$_database->query("
        SELECT COUNT(DISTINCT ip_hash) AS cnt
        FROM visitor_statistics
        WHERE last_seen >= FROM_UNIXTIME($five_minutes_ago) $bot_condition
    ")->fetch_assoc()['cnt'];

    // MaxOnline (aus daily_counter)
    $max_online = (int)$_database->query("
        SELECT MAX(maxonline) AS maxcnt
        FROM visitor_daily_counter
    ")->fetch_assoc()['maxcnt'];

    return [
        'today'     => $today_hits,
        'yesterday' => $yesterday_hits,
        'month'     => $month_hits,
        'total'     => $total_hits,
        'online'    => $online_visitors,
        'maxonline' => $max_online
    ];
}


// --- Monatsstatistik (Progressbars) ---
$month_start = date('Y-m-01');

$res_month_days = safe_query("
    SELECT DATE(date) AS date, SUM(hits) AS visits
    FROM visitor_daily_counter
    WHERE date >= '$month_start'
    GROUP BY DATE(date)
    ORDER BY DATE(date) ASC
");

$month_stats = '';
$month_max   = 0;

// Maximalwert ermitteln
while ($row = mysqli_fetch_assoc($res_month_days)) {
    if ($row['visits'] > $month_max) $month_max = $row['visits'];
}

// Zweiter Durchlauf für Balken
mysqli_data_seek($res_month_days, 0);
while ($row = mysqli_fetch_assoc($res_month_days)) {
    $visits  = $row['visits'];
    $prozent = $month_max > 0 ? ($visits * 100 / $month_max) : 0;
    $full_date = date('d.m.Y', strtotime($row['date'])); // z.B. 01.09.2025

    $month_stats .= '<li class="list-group-item">
        <div class="row">
            <div class="col-2">' . $full_date . ' 
                <span class="badge bg-secondary">' . $visits . '</span>
            </div>
            <div class="col-9">
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" 
                         aria-valuenow="' . round($prozent) . '" 
                         aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <div class="col-1">' . round($prozent) . '%</div>
        </div>
    </li>';
}


function getUserStats(mysqli $_database): array {
    $res_users = $_database->query("
        SELECT 
            COUNT(u.userID) AS registered_users,
            MIN(NULLIF(up.birthday,'0000-00-00')) AS oldest_birthdate,
            MAX(NULLIF(up.birthday,'0000-00-00')) AS youngest_birthdate
        FROM users u
        LEFT JOIN user_profiles up ON u.userID = up.userID
        WHERE u.is_active = 1
    ");
    $user_data = $res_users->fetch_assoc();

    $registered_users = (int)$user_data['registered_users'];
    $youngest_user    = $user_data['youngest_birthdate'] ? date_diff(date_create($user_data['youngest_birthdate']), date_create('now'))->y : 0;
    $oldest_user      = $user_data['oldest_birthdate'] ? date_diff(date_create($user_data['oldest_birthdate']), date_create('now'))->y : 0;

    return [
        'registered_users' => $registered_users,
        'youngest_user'    => $youngest_user,
        'oldest_user'      => $oldest_user
    ];
}


$counter = getVisitorCounter($_database);
$userStats = getUserStats($_database);

// --- Template-Array ---
$data_array = [
    // Besucherzahlen
    'visits_title'      => $languageService->get('visits_title'),
    'visits_today'      => $languageService->get('visits_today'),
    'today'             => $counter['today'],
    'visits_yesterday'  => $languageService->get('visits_yesterday'),
    'yesterday'         => $counter['yesterday'],
    'visits_this_month' => $languageService->get('visits_this_month'),
    'month'             => $counter['month'],
    'visits_total'      => $languageService->get('visits_total'),
    'total'             => $counter['total'],

    // Online
    'online_title'      => $languageService->get('online_title'),
    'online_now'        => $languageService->get('online_now'),
    'online'            => $counter['online'],
    'online_maximum'    => $languageService->get('online_maximum'),
    'maxonline'         => $counter['maxonline'],

    // User-Statistik
    'user_stats_title'  => $languageService->get('user_stats_title'),
    'user_registered'   => $languageService->get('user_registered'),
    'registered_users'  => $userStats['registered_users'],
    'user_youngest'     => $languageService->get('user_youngest'),
    'youngest_user'     => $userStats['youngest_user'],
    'user_oldest'       => $languageService->get('user_oldest'),
    'oldest_user'       => $userStats['oldest_user'],
    'unit_years'        => $languageService->get('unit_years'),

    // Monatsstatistik
    'month_stats_title' => $languageService->get('month_stats_title'),
    'month_stats'       => $month_stats
];

// --- Template laden ---
echo $tpl->loadTemplate("counter", "main", $data_array, 'plugin');
