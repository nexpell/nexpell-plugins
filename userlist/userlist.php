<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('userlist');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('registered_users'),
    'subtitle' => 'Userlist'
];
echo $tpl->loadTemplate("userlist", "head", $data_array, "plugin");

// Hilfsfunktion zum Säubern von Texten (optional)
function clear($text)
{
    return str_replace("javascript:", "", strip_tags($text));
}

// Gesamtanzahl der Benutzer ermitteln
$alle = safe_query("SELECT userID FROM users");
$gesamt = mysqli_num_rows($alle);

// Einstellungen aus Plugin-Tabelle holen
$settings = safe_query("SELECT * FROM plugins_userlist");
$ds = mysqli_fetch_array($settings);

// Maximale Benutzer pro Seite (Default 10)
$maxusers = !empty($ds['users_list']) ? (int)$ds['users_list'] : 10;

// Seitenanzahl berechnen
$pages = 1;
for ($n = $maxusers; $n <= $gesamt; $n += $maxusers) {
    if ($gesamt > $n) $pages++;
}

// Aktuelle Seite aus URL, Default 1, min. 1
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Sortierung validieren (Whitelist)
$allowedSorts = ['username', 'lastlogin', 'registerdate', 'homepage'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts) ? $_GET['sort'] : 'username';

// Sortier-Typ (ASC / DESC), Default ASC
$type = (isset($_GET['type']) && $_GET['type'] === 'DESC') ? 'DESC' : 'ASC';

// Pagination-Link vorbereiten (mit Sortierparameter)
$page_link = $pages > 1 ? makepagelink("index.php?site=userlist&amp;sort=$sort&amp;type=$type", $page, $pages) : '';

// Start-Offset für LIMIT
$start = ($page - 1) * $maxusers;

// Benutzer abfragen mit Sortierung und Limit
$ergebnis = safe_query("SELECT * FROM users ORDER BY $sort $type LIMIT $start, $maxusers");

// Zähler je nach Sortierung initialisieren (für Reihenfolge-Anzeige, optional)
$n = ($type === "DESC") ? $gesamt - $start : $start + 1;

if (mysqli_num_rows($ergebnis)) {

    // Sortier-Icon und Link für aktuelle Sortierung
    $typeToggle = ($type === 'ASC') ? 'DESC' : 'ASC';
    $sorter = '<a href="index.php?site=userlist&amp;page=' . $page . '&amp;sort=' . $sort . '&amp;type=' . $typeToggle . '">'
        . $languageService->get('sort') . '</a>';
    $sorter .= $type === 'ASC' ? ' <i class="bi bi-arrow-down"></i>' : ' <i class="bi bi-arrow-up"></i>';

    // Header-Daten für Template
    $data_array = [
        'page_link' => $page_link,
        'gesamt' => $gesamt,
        'page' => $page,
        'sorter' => $sorter,
        'registered_users' => $languageService->get('registered_users'),
        'username' => $languageService->get('username'),
        'contact' => $languageService->get('contact'),
        'homepage' => $languageService->get('homepage'),
        'last_login' => $languageService->get('last_login'),
        'registration' => $languageService->get('registration')
    ];

    // Header-Template laden
    echo $tpl->loadTemplate("userlist", "header", $data_array, "plugin");

    // Benutzer-Daten ausgeben
    while ($ds = mysqli_fetch_array($ergebnis)) {
        $id = $ds['userID'];
        $username = '<a href="index.php?site=profile&amp;userID=' . $id . '">' . getusername($id) . '</a>';

        // Prüfen, ob Squad-Modul aktiv und User Mitglied
        $dx = mysqli_fetch_array(safe_query("SELECT * FROM settings_plugins WHERE modulname='squads'"));
        $member = (@$dx['modulname'] === 'squads' && isclanmember($id))
            ? ' <i class="bi bi-person" style="color: #5cb85c"></i>'
            : '';

        // E-Mail ausblenden wenn gewünscht
        $email = $ds['email_hide']
            ? '<span class=""><i class="bi bi-envelope-slash"></i> ' . $languageService->get('email_hidden') . '</span>'
            : '<a href="mailto:' . htmlspecialchars(mail_protect($ds['email'])) . '"><i class="bi bi-envelope"></i> ' . $languageService->get('email') . '</a>';

        // Homepage-Link prüfen, ggf. Protokoll ergänzen
        if ($ds['homepage']) {
            $protocol = stristr($ds['homepage'], "https://") || stristr($ds['homepage'], "http://") ? '' : 'http://';
            $homepage = '<a href="' . $protocol . htmlspecialchars($ds['homepage']) . '" target="_blank" rel="nofollow">'
                . '<i class="bi bi-house" style="font-size:18px;"></i> ' . $languageService->get('homepage') . '</a>';
        } else {
            $homepage = '<i class="bi bi-house-slash" style="font-size:18px;"></i><i> ' . $languageService->get('homepage') . '</i>';
        }

        // Aktueller eingeloggter User (für PM-Link)
        $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
        $userID = $loggedin ? (int)$_SESSION['userID'] : 0;

        // Private Message Link nur, wenn eingeloggter User und nicht sich selbst
        $pm = ($loggedin && $id != $userID)
            ? ' / <a href="index.php?site=messenger&amp;action=touser&amp;touser=' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
                . '<i class="bi bi-messenger"></i> ' . $languageService->get('message') . '</a>'
            : ' / <i class="bi bi-slash-circle"></i> ' . $languageService->get('message');

        // Lastlogin und Registration vorbereiten
        $lastlogin = $ds['lastlogin'] ?? '1970-01-01 00:00:00';
        $registerdate = $ds['registerdate'] ?? '1970-01-01 00:00:00';

        $lastActivityTimestamp = strtotime($lastlogin);
        $nowTimestamp = time();
        $onlineTimeout = 10 * 60; // 10 Minuten Timeout

        // Status prüfen (online/offline)
        if ($lastlogin === '1970-01-01 00:00:00' || $lastlogin === $registerdate) {
            $status = "offline";
        } else {
            $status = ($lastActivityTimestamp !== false && ($nowTimestamp - $lastActivityTimestamp) <= $onlineTimeout)
                ? "online"
                : "offline";
        }

        // Anzeige des Loginstatus
        if ($status === "offline") {
            $login = ($lastlogin === '1970-01-01 00:00:00' || $lastlogin === $registerdate)
                ? $languageService->get('n_a')
                : date("d.m.Y - H:i", $lastActivityTimestamp);
        } else {
            $login = '<span class="badge bg-success">' . $languageService->get('online') . '</span> ' . $languageService->get('now_on');
        }

        // Avatar anzeigen falls vorhanden
        $avatar = ($getavatar = getavatar($id))
            ? '<img class="img-fluid avatar_small" src="./images/avatars/' . htmlspecialchars($getavatar) . '" alt="Avatar">'
            : '';

        // Registrierungstag berechnen (heute, gestern, morgen, Zukunft)
        $today = new DateTime('today');
        $regDate = new DateTime($ds['registerdate']);
        $interval = $today->diff($regDate);
        $difference = (int)$interval->format('%r%a'); // Anzahl Tage (+/-)

        if ($difference === 0) {
            $register = $languageService->get('today');
        } elseif ($difference === 1) {
            $register = $languageService->get('tomorrow');
        } elseif ($difference === -1) {
            $register = $languageService->get('yesterday');
        } elseif ($difference > 1) {
            $register = $languageService->get('future_date');
        } else {
            $register = date("d.m.Y", $regDate->getTimestamp());
        }

        // Datenarray für Template
        $data_array = [
            'username' => $username,
            'avatar' => $avatar,
            'member' => $member,
            'homepage' => $homepage,
            'email' => $email,
            'pm' => $pm,
            'login' => $login,
            'register' => $register
        ];

        // Benutzer-Datenblock ausgeben
        echo $tpl->loadTemplate("userlist", "user", $data_array, "plugin");

        // Zähler inkrementieren (je nach Sortierung)
        $n = ($type === "DESC") ? $n - 1 : $n + 1;
    }

    // Footer-Template mit Paging
    echo $tpl->loadTemplate("userlist", "footer", ['page_link' => $page_link], "plugin");

} else {
    // Keine Benutzer gefunden
    echo '<div class="alert alert-warning">' . $languageService->get('no_users_found') . '</div>';
}
?>
