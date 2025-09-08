<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService, $_database;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('live_visitor');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => $languageService->get('list_live_visitors')
];

echo $tpl->loadTemplate("live_visitor", "head", $data_array, 'plugin');

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

// --- Zeitpunkt für letzte 10 Minuten ---
$ten_minutes_ago = date('Y-m-d H:i:s', time() - 600);

// --- Bot-Bedingung ---
$bot_condition = getBotCondition(); // z.B. "user_agent NOT LIKE '%bot%' AND ..."
$bot_sql = '';
if (!empty($bot_condition)) {
    $bot_sql = "AND NOT ($bot_condition)";
}

$ten_minutes_ago = time() - 600; // Unix-Timestamp

$res_online = $_database->query("
    SELECT vs.*, u.username, up.avatar
    FROM visitors_live vs
    LEFT JOIN users u ON u.userID = vs.userID AND u.is_active = 1
    LEFT JOIN user_profiles up ON up.userID = vs.userID
    WHERE vs.time >= $ten_minutes_ago
    ORDER BY vs.time DESC
");

$online_count = $res_online->num_rows;


$yesterday_24h = time() - 86400; // letzte 24 Stunden

$sql = "
SELECT vh.*, u.username, up.avatar
FROM visitors_live_history vh
INNER JOIN (
    -- letzte Aktion vor dem letzten Eintrag pro User
    SELECT vh1.userID, MAX(vh1.time) AS prev_time
    FROM visitors_live_history vh1
    INNER JOIN (
        SELECT userID, MAX(time) AS last_time
        FROM visitors_live_history
        WHERE userID IS NOT NULL
        GROUP BY userID
    ) AS last_vh
    ON vh1.userID = last_vh.userID
    WHERE vh1.time < last_vh.last_time
    GROUP BY vh1.userID
) AS prev_vh
ON vh.userID = prev_vh.userID AND vh.time = prev_vh.prev_time
LEFT JOIN users u ON u.userID = vh.userID AND u.is_active = 1
LEFT JOIN user_profiles up ON up.userID = vh.userID
WHERE vh.time >= $yesterday_24h
ORDER BY vh.time DESC
";

$res_history = $_database->query($sql);
$history_count = $res_history->num_rows;

$counter = getVisitorCounter($_database);
?>

<div class="container py-4">

    <div class="row">
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('visits_today') ?></h6><span class="badge bg-primary"><?= $counter['today'] ?></span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('visits_this_month') ?></h6><span class="badge bg-success"><?= $counter['month'] ?></span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('visits_total') ?></h6><span class="badge bg-secondary"><?= $counter['total'] ?></span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('list_live_visitors') ?></h6><span class="badge bg-warning text-dark"><?= $counter['online'] ?></span></div></div></div>
    </div>

<div class="card p-3 h-100 mb-3">
<?php


function render_visitors_card(mysqli_result $res, string $type = 'online') {
    global $languageService;

    $lang = $languageService->detectLanguage();
    $languageService->readPluginModule('live_visitor');

    echo '<div class="card p-3 mb-3">';
    echo '<h5 class="mb-3">';
    echo ($type === 'online'
        ? $languageService->get('online_visitors')
        : $languageService->get('historical_visitors'));
    echo '</h5>';

    echo '<ul class="list-group list-group-flush">';

    while ($row = mysqli_fetch_assoc($res)) {
        $username = $row['username'] ?? $languageService->get('visitor_guest');

        if (!empty($row['userID'])) {
            $avatar = getavatar($row['userID']);
        } else {
            // Fallback für Gäste → direkt Initialen "G"
            $avatar = '/images/avatars/svg-avatar.php?name=Gast';
        }
        
        $page_url = $row['site'] ?? '#';

        // --- Seite bestimmen ---
        $page_key = 'start'; // Default
        $parsed = parse_url($page_url);
        $path   = $parsed['path'] ?? '/';
        if (!empty($parsed['query']) && preg_match('/site=([^&]+)/', $parsed['query'], $m)) {
            $page_key = $m[1];
        } elseif (preg_match('#^/(de|en|it)/([^/]+)#', $path, $m)) {
            $page_key = $m[2];
        }

        $page_key = strtolower(preg_replace('/[^a-z0-9_]/i', '', $page_key));

        // Mehrsprachige Anzeige
        // ---------- Mehrsprachige Liste ----------
        $array_watching = [
            'about' => [
                'de' => 'die About Us Seite',
                'en' => 'the About Us page',
                'it' => 'la pagina Chi siamo'
            ],
            'blog' => [
                'de' => 'den Blog',
                'en' => 'the blog',
                'it' => 'il blog'
            ],
            'forum' => [
                'de' => 'das Forum',
                'en' => 'the forum',
                'it' => 'il forum'
            ],
            'gallery' => [
                'de' => 'die Galerie',
                'en' => 'the gallery',
                'it' => 'la galleria'
            ],
            'counter' => [
                'de' => 'den Counter',
                'en' => 'the counter',
                'it' => 'il contatore'
            ],
            'live_visitor' => [
                'de' => 'den Live-Besucher',
                'en' => 'the Live Visitors',
                'it' => 'i visitatori in tempo reale'
            ],
            'shoutbox' => [
                'de' => 'die Shoutbox',
                'en' => 'the shoutbox',
                'it' => 'la shoutbox'
            ],
            'leistung' => [
                'de' => 'die Leistung',
                'en' => 'the service',
                'it' => 'il servizio'
            ],
            'info' => [
                'de' => 'die Info-Seite',
                'en' => 'the info page',
                'it' => 'la pagina info'
            ],
            'resume' => [
                'de' => 'der Lebenslauf',
                'en' => 'the resume',
                'it' => 'il curriculum'
            ],
            'todo' => [
                'de' => 'die ToDo-Liste',
                'en' => 'the todo list',
                'it' => 'la lista delle cose da fare'
            ],
            'articles' => [
                'de' => 'die Artikel',
                'en' => 'the articles',
                'it' => 'gli articoli'
            ],
            'achievements' => [
                'de' => 'die Erfolge',
                'en' => 'the achievements',
                'it' => 'i successi'
            ],
            'userlist' => [
                'de' => 'die Benutzerliste',
                'en' => 'the user list',
                'it' => 'la lista utenti'
            ],
            'downloads' => [
                'de' => 'die Downloads',
                'en' => 'the downloads',
                'it' => 'i download'
            ],
            'partners' => [
                'de' => 'die Partner',
                'en' => 'the partners',
                'it' => 'i partner'
            ],
            'wiki' => [
                'de' => 'das Wiki',
                'en' => 'the wiki',
                'it' => 'il wiki'
            ],
            'search' => [
                'de' => 'die Suche',
                'en' => 'the search',
                'it' => 'la ricerca'
            ],
            'contact' => [
                'de' => 'die Kontaktseite',
                'en' => 'the contact page',
                'it' => 'la pagina contatti'
            ],
            'gametracker' => [
                'de' => 'der GameTracker',
                'en' => 'the gametracker',
                'it' => 'il gametracker'
            ],
            'discord' => [
                'de' => 'der Discord-Server',
                'en' => 'the Discord server',
                'it' => 'il server Discord'
            ],
            'twitch' => [
                'de' => 'der Twitch-Kanal',
                'en' => 'the Twitch channel',
                'it' => 'il canale Twitch'
            ],
            'youtube' => [
                'de' => 'der YouTube-Kanal',
                'en' => 'the YouTube channel',
                'it' => 'il canale YouTube'
            ],
            'imprint' => [
                'de' => 'das Impressum',
                'en' => 'the imprint',
                'it' => 'l\'impressum'
            ],
            'privacy_policy' => [
                'de' => 'die Datenschutzrichtlinie',
                'en' => 'the privacy policy',
                'it' => 'la politica sulla privacy'
            ],
            'links' => [
                'de' => 'die Links',
                'en' => 'the links',
                'it' => 'i link'
            ],
            'pricing' => [
                'de' => 'die Preisübersicht',
                'en' => 'the pricing',
                'it' => 'i prezzi'
            ],
            'rules' => [
                'de' => 'die Regeln',
                'en' => 'the rules',
                'it' => 'le regole'
            ],
            'messenger' => [
                'de' => 'der Messenger',
                'en' => 'the messenger',
                'it' => 'il messenger'
            ],
                'start' => [
                'de' => 'Startseite',
                'en' => 'Homepage',
                'it' => 'Pagina iniziale'
            ],
            'login' => [
                'de' => 'Login',
                'en' => 'Login',
                'it' => 'Login'
            ],
            'register' => [
                'de' => 'Registrierung',
                'en' => 'Register',
                'it' => 'Registrazione'
            ],
            'lostpassword' => [
                'de' => 'Passwort vergessen',
                'en' => 'Lost password',
                'it' => 'Password dimenticata'
            ],
            'profile' => [
                'de' => 'Profil',
                'en' => 'Profile',
                'it' => 'Profilo'
            ],
            'edit_profile' => [
                'de' => 'Profil bearbeiten',
                'en' => 'Edit profile',
                'it' => 'Modifica profilo'
            ],
            '#' => [
                'de' => 'eine unbekannte Seite',
                'en' => 'an unknown page',
                'it' => 'una pagina sconosciuta'
            ]
        ];

        $page_display = $array_watching[$page_key][$lang] 
            ?? ($array_watching[$page_key]['en'] ?? $page_key);

        // --- Flagge ---
        $country_code = strtolower($row['country_code'] ?? 'unknown');
        $flag_file = "/admin/images/flags/{$country_code}.svg";
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $flag_file)) {
            $flag_file = "/admin/images/flags/unknown.svg";
        }

        // --- Zeit ---
        $timestamp = $row['time'] ?? time(); // Unix-Timestamp
        $time = date("d.m.Y H:i", $timestamp); // Direkt formatieren, nicht nochmal strtotime

        // --- Ausgabe ---
        echo '<li class="list-group-item visitor-item d-flex justify-content-between align-items-center">';
        echo '<div class="visitor-left d-flex align-items-center">';
        if ($avatar) {
            echo '<img src="' . htmlspecialchars($avatar) . '" class="avatar me-2" alt="Avatar">';
        }
        echo '<strong>' . htmlspecialchars($username) . '</strong>';
        echo '</div>';

        echo '<div class="visitor-middle text-start flex-grow-1 ms-3">';
        if ($type === 'online') {
            echo ($lang === 'de' ? 'schaut an ' : ($lang === 'en' ? 'is watching ' : 'sta guardando '));
        } else {
            echo ($lang === 'de' ? 'schaute an ' : ($lang === 'en' ? 'watched ' : 'ha guardato '));
        }
        echo '<a href="' . htmlspecialchars($page_url) . '">' . htmlspecialchars($page_display) . '</a>';
        echo '</div>';

        echo '<div class="visitor-right d-flex flex-column align-items-end">';
        echo '<span class="flag mb-1" style="display:inline-block; width:32px; height:22px; background-image:url(\'' 
            . htmlspecialchars($flag_file) . '\'); background-size:cover; background-position:center; border:1px solid #ccc; border-radius:2px;"></span>';
        echo '<small class="text-muted">' . htmlspecialchars($time) . '</small>';
        echo '</div>';

        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

// Aufruf bleibt unverändert
render_visitors_card($res_online, 'online');
render_visitors_card($res_history, 'history');

