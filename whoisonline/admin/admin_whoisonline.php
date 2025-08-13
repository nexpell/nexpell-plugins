<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('whoisonline');

$res = safe_query("
    SELECT w.*, u.username
    FROM plugins_whoisonline w
    LEFT JOIN users u ON w.user_id = u.userID
    ORDER BY w.last_activity DESC
    LIMIT 10
");
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> WhoIsOnline
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item active" aria-current="page">WhoIsOnline - Übersicht</li>
        </ol>
    </nav>  

    <div class="card-body">

        <h4>Angemeldete Benutzer</h4>

        <div class="accordion" id="whoisonlineAccordion">
            <?php
            $index = 0;
            while ($row = mysqli_fetch_assoc($res)) {
                $index++;
                $id = 'whoisonlineItem' . $index;

                $username = $row['username'] ?? 'Gast';
                $page = htmlspecialchars($row['page'] ?? '');

                $last_activity_raw = $row['last_activity'] ?? null;
                if ($last_activity_raw) {
                    $dt = new DateTime($last_activity_raw);
                    $last_activity = $dt->format('d.m.Y H:i');
                } else {
                    $last_activity = 'unbekannt';
                }

                $user_agent_raw = $row['user_agent'] ?? '';
                $user_agent = !empty($user_agent_raw) ? htmlspecialchars($user_agent_raw) : 'Nicht verfügbar';

                $ip_hash = !empty($row['ip_hash']) ? htmlspecialchars($row['ip_hash']) : 'Nicht verfügbar';
                $session_id = !empty($row['session_id']) ? htmlspecialchars($row['session_id']) : 'Nicht verfügbar';

                
$ua = strtolower(trim($user_agent_raw));
$device = 'Unbekannt';

// Leerer oder sehr kurzer User-Agent (häufig Bot oder Scanner)
if ($ua === '' || $ua === '-' || $ua === 'null' || strlen($ua) < 10) {
    $device = 'Nicht verfügbar (vermutlich Bot/Scanner)';


// Erweiterte Bot- und Scanner-Erkennung inkl. häufige SEO- und Sicherheits-Crawler
} elseif (preg_match('/
    bot|crawl|spider|slurp|mediapartners|bingpreview|facebookexternalhit|
python-requests|python-urllib|wget|curl|libwww|scanner|nikto|sqlmap|
masscan|nmap|fuzzer|ahrefsbot|semrushbot|mj12bot|dotbot|baiduspider|
winhttp|httpclient|yandex|http_request2|botzilla|screaming frog|heritrix|
zmeu|seznambot|rogerbot|linkedinbot|applebot|duckduckbot|embedly|
pinterest|quora link preview|showyoubot|twitterbot|vodafone|yeti|adsbot|
avgbot|netcraftsurveyagent|newsgatorbot|sogou|yandexbot|oegp|exabot|
gigabot|semalt|sitebot|tweetmeme|yandeximages|msnbot|ia_archiver|googlebot|
bingbot|facebot|applebot|twitterbot|linkedinbot|petalbot|seznam|exaleadbot|
blexbot|whatsapp|slackbot|telegrambot|discordbot|proofpoint|mimecast|
barracuda|burpsuite|zap|wfuzz|fiddler|acunetix|nessus|archive.org_bot|
perl lwp|amazon cloudfront|google cloud|microsoft azure|fastly|akamai|
cloudflare|incapsula|sucuri|shopbot|pricespider|pricegrabber|linkpadbot|
headlesschrome|phantomjs|google structured data testing tool|apple news|adsbot-google
    /ix', $ua)) {
    $device = 'Bot/Scanner/Hacker';

// Proxy, Anonymizer, TOR etc. (häufig missbraucht von Bots und Hackern)
} elseif (preg_match('/
    torbrowser|tor client|privoxy|proxy|anonymouse|anonymizer|cloak|vpn|vpn client|
    zscaler|cloudflare|incapsula|akamai|fastly|squid|wget|curl
    /ix', $ua)) {
    $device = 'Proxy / Anonymizer / VPN';

// Mobile Geräte (inklusive exoten und browser-spezifisch)
} elseif (preg_match('/
    mobile|iphone|ipod|android|blackberry|phone|phoneos|opera mini|windows phone|
    silk|symbian|webos|palm|bb10|nokia|meego|kindle|playbook|bada|tizen|maemo
    /ix', $ua)) {
    $device = 'Mobilgerät';

// Tablets inkl. Kindle, Playbook, iPad, Android Tablets etc.
} elseif (preg_match('/ipad|tablet|kindle|playbook|silk|nexus 7|nexus 10|galaxy tab/i', $ua)) {
    $device = 'Tablet';

// Windows OS Varianten
} elseif (preg_match('/windows nt|windows 10|windows 8|windows 7|windows xp|win64|wow64/i', $ua)) {
    $device = 'Windows-PC';

// Mac OS Varianten
} elseif (preg_match('/macintosh|mac os x|mac_powerpc/i', $ua)) {
    $device = 'Mac';

// Linux und gängige Distributionen
} elseif (preg_match('/linux|ubuntu|fedora|red hat|debian|gentoo|arch linux/i', $ua)) {
    $device = 'Linux-PC';

// Chrome OS
} elseif (preg_match('/cros/i', $ua)) {
    $device = 'Chrome OS';

// Browser mit unbekanntem OS (Typische Browser Engines / Browserkennung)
} elseif (preg_match('/mozilla\/|gecko\/|chrome\/|safari\/|firefox\/|edge\/|trident\/|applewebkit\//i', $ua)) {
    $device = 'Browser (OS unbekannt)';

// Sonstige unbekannte User Agents — optional loggen für Analyse
} else {
    error_log("Unbekannter User-Agent: " . $user_agent_raw);
}




            ?>

            <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?= $index ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $id ?>" aria-expanded="false" aria-controls="<?= $id ?>">
    <div class="d-flex justify-content-between w-100">
        <div><strong>Benutzer:</strong> <span style="width: 70px; display: inline-block;"><?= htmlspecialchars($username) ?></span></div>
        <div><strong>Seite:</strong> <span style="width: 350px; display: inline-block;"><?= $page ?></span></div>
        <div><strong>Zuletzt aktiv:</strong> <span style="width: 120px; display: inline-block;"><?= $last_activity ?></span></div>
        <div><strong>Gerät:</strong> <span style="width: 350px; display: inline-block;"><?= $device ?></span></div>
    </div>
</button>
                </h2>
                <div id="<?= $id ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#whoisonlineAccordion">
                    <div class="accordion-body bg-light">
                        <strong>User-Agent:</strong> <?= $user_agent ?><br>
                        <strong>IP-Hash:</strong> <?= $ip_hash ?><br>
                        <strong>Session-ID:</strong> <?= $session_id ?><br>
                        <strong>Page:</strong> <?= $page ?><br>
                        <strong>Gerät:</strong> <?= $device ?>
                    </div>
                </div>
            </div>

            <?php
            }
            ?>
        </div>

    </div>
</div>
