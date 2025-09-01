<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService,$_database;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('live_visitor');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => $languageService->get('list_live_visitors')
];
    
echo $tpl->loadTemplate("live_visitor", "head", $data_array, 'plugin');

// Zeiten
$ten_minutes_ago = date('Y-m-d H:i:s', time() - 600);
$yesterday_24h = date('Y-m-d H:i:s', time() - 86400);

// Statistiken
$today_date = date('Y-m-d');
$today = (int)$_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics WHERE DATE(created_at) = '$today_date'")->fetch_assoc()['cnt'];
$month = (int)$_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics WHERE MONTH(created_at) = MONTH('$today_date')")->fetch_assoc()['cnt'];
$total = (int)$_database->query("SELECT COUNT(*) AS cnt FROM visitor_statistics")->fetch_assoc()['cnt'];

$res_online = $_database->query("
    SELECT vs.*, u.username, up.avatar
    FROM visitor_statistics vs
    LEFT JOIN users u ON u.userID = vs.user_id AND u.is_active = 1
    LEFT JOIN user_profiles up ON up.userID = vs.user_id
    WHERE vs.created_at >= '$ten_minutes_ago'
    ORDER BY vs.created_at DESC
");
$online_count = $res_online->num_rows;

$res_history = $_database->query("
    SELECT vs.*, u.username, up.avatar
    FROM visitor_statistics vs
    LEFT JOIN users u ON u.userID = vs.user_id AND u.is_active = 1
    LEFT JOIN user_profiles up ON up.userID = vs.user_id
    WHERE vs.created_at >= '$yesterday_24h'
    ORDER BY vs.created_at DESC
");
$history_count = $res_history->num_rows;

?>

<style>
.avatar { width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:0.5rem; }
.visitor-item { display:flex; justify-content:space-between; align-items:center; padding:0.5rem 1rem; border-bottom:1px solid #dee2e6; }
.visitor-left { display:flex; align-items:center; gap:0.5rem; flex:1 1 40%; }
.visitor-middle { flex:1 1 30%; }
.visitor-right { flex:1 1 20%; text-align:right; font-size:0.85rem; color:#6c757d; }
.flag-img {
    width: 34px;        /* Breite */
    height: auto;       /* Höhe automatisch proportional */
    display: inline-block;
}
</style>

<div class="container py-4">

    <div class="row">
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('visits_today') ?></h6><span class="badge bg-primary"><?= $today ?></span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('visits_this_month') ?></h6><span class="badge bg-success"><?= $month ?></span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('visits_total') ?></h6><span class="badge bg-secondary"><?= $total ?></span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h6><?= $languageService->get('list_live_visitors') ?></h6><span class="badge bg-warning text-dark"><?= $online_count ?></span></div></div></div>
    </div>

<div class="card p-3 h-100 mb-3">
    <h5><?= $languageService->get('list_live_visitors') ?></h5>
    <ul class="list-group list-group-flush mb-4">
        <?php while($row = $res_online->fetch_assoc()): ?>
            <li class="list-group-item visitor-item">
                <div class="visitor-left">
                    <?php if(!empty($row['avatar'])): ?><img src="<?= $row['avatar'] ?>" class="avatar" alt="Avatar"><?php endif; ?>
                    <strong><?= htmlspecialchars($row['username'] ?? $languageService->get('visitor_guest')) ?></strong>
                </div>
                
                <?php
                // Sprache festlegen (z. B. aus Session oder globaler Variable $lang)
                $lang = $lang ?? 'de';

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


                // URL aus DB
$page_url = $row['page'] ?? '#';
$page_key = '#'; // Default unbekannt

// 1. Prüfen, ob ein "site=" Parameter existiert
if (preg_match('/site=([^&]+)/', $page_url, $m)) {
    $page_key = $m[1];
} else {
    // 2. SEO-URL: ersten Pfad nach /de/, /en/, /it/ nehmen
    if (preg_match('#^/(de|en|it)/([^/]+)#', $page_url, $m)) {
        $page_key = $m[2]; // z. B. "forum" aus /de/forum/thread/id/19
    } elseif (preg_match('#^/(de|en|it)/?$#', $page_url)) {
        // Startseite mit Sprachpräfix
        $page_key = 'start';
    } elseif ($page_url === '/' || $page_url === '') {
        // Root-URL ohne Präfix
        $page_key = 'start';
    }
}

// 3. Wenn URL direkt auf .php endet, als unbekannt markieren
if (preg_match('/\.php$/i', $page_key)) {
    $page_key = '#';
}

// 4. Anzeige aus Array holen
$page_display = $array_watching[$page_key][$lang] 
    ?? ($array_watching[$page_key]['en'] ?? $array_watching['#'][$lang] ?? $page_key);




    ?>

                <div class="visitor-middle text-start flex-grow-1">
                    <?= ($lang === 'de' ? 'schaut an ' : ($lang === 'en' ? 'watched ' : 'ha guardato ')) ?>
                    <a href="<?= htmlspecialchars($page_url) ?>"><?= htmlspecialchars($page_display) ?></a>
                </div>
        
        
                <div class="visitor-right text-end">
                    <?php 
                        $country_code = strtolower($row['country_code'] ?? 'unknown');
                        $flag_file = "/admin/images/flags/{$country_code}.svg";

                        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $flag_file)) {
                            $flag_file = "/admin/images/flags/unknown.svg";
                        }
                    ?>
                    <span class="flag me-1" style="display:inline-block; width:32px; height:22px; background-image: url('<?= htmlspecialchars($flag_file) ?>'); background-size: cover; background-position: center;"></span>
                    <br>
                    <small><?= htmlspecialchars($row['created_at']) ?></small>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
<div class="card p-3 h-100 mb-3">
    <h5><?= $languageService->get('list_historical_visitors') ?> <small class="text-muted fs-6"><?= $languageService->get('list_historical_visitors_subtitle') ?></small></h5>
    <ul class="list-group list-group-flush">
        <?php while($row = $res_history->fetch_assoc()): ?>
            <li class="list-group-item visitor-item">
                <div class="visitor-left">
                    <?php if(!empty($row['avatar'])): ?><img src="<?= $row['avatar'] ?>" class="avatar" alt="Avatar"><?php endif; ?>
                    <strong><?= htmlspecialchars($row['username'] ?? $languageService->get('visitor_guest')) ?></strong>
                </div>

                <?php
                // URL aus DB
$page_url = $row['page'] ?? '#';
$page_key = '#'; // Default unbekannt

// 1. Prüfen, ob ein "site=" Parameter existiert
if (preg_match('/site=([^&]+)/', $page_url, $m)) {
    $page_key = $m[1];
} else {
    // 2. SEO-URL: ersten Pfad nach /de/, /en/, /it/ nehmen
    if (preg_match('#^/(de|en|it)/([^/]+)#', $page_url, $m)) {
        $page_key = $m[2]; // z. B. "forum" aus /de/forum/thread/id/19
    } elseif (preg_match('#^/(de|en|it)/?$#', $page_url)) {
        // Startseite mit Sprachpräfix
        $page_key = 'start';
    } elseif ($page_url === '/' || $page_url === '') {
        // Root-URL ohne Präfix
        $page_key = 'start';
    }
}

// 3. Wenn URL direkt auf .php endet, als unbekannt markieren
if (preg_match('/\.php$/i', $page_key)) {
    $page_key = '#';
}

// 4. Anzeige aus Array holen
$page_display = $array_watching[$page_key][$lang] 
    ?? ($array_watching[$page_key]['en'] ?? $array_watching['#'][$lang] ?? $page_key);




                ?>

                <div class="visitor-middle text-start flex-grow-1">
                    <?= ($lang === 'de' ? 'schaute an ' : ($lang === 'en' ? 'watched ' : 'ha guardato ')) ?>
                    <a href="<?= htmlspecialchars($page_url) ?>"><?= htmlspecialchars($page_display) ?></a>
                </div>

       
                <div class="visitor-right text-end">
                    <?php 
                        $country_code = strtolower($row['country_code'] ?? 'unknown');
                        $flag_file = "/admin/images/flags/{$country_code}.svg";

                        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $flag_file)) {
                            $flag_file = "/admin/images/flags/unknown.svg";
                        }
                    ?>
                    <span class="flag me-1" style="display:inline-block; width:32px; height:22px; background-image: url('<?= htmlspecialchars($flag_file) ?>'); background-size: cover; background-position: center;"></span>
                    <br>
                    <small><?= htmlspecialchars($row['created_at']) ?></small>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
</div>