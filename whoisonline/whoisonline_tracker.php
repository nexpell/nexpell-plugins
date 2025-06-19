<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page = $_SERVER['REQUEST_URI'];
$ext = pathinfo(parse_url($page, PHP_URL_PATH), PATHINFO_EXTENSION);
$allowed_ext = ['php', 'html', 'htm', '']; 
if (!in_array(strtolower($ext), $allowed_ext)) {
    return;
}

$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ua = strtolower($user_agent);

$bot_patterns = [
    'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'wget', 'curl',
    'python-requests', 'httpclient', 'scrapy', 'aiohttp', 'go-http-client',
    'java', 'feedfetcher', 'gptbot', 'censysinspect', 'datadome', 'phantomjs',
    'yandex', 'semrush', 'ahrefs', 'mj12bot', 'facebookexternalhit', 'whatsapp',
    'discordbot', 'telegrambot', 'twitterbot', 'bingpreview', 'duckduckbot',
    'chrome-lighthouse', 'uptimerobot', 'checkly'
];

foreach ($bot_patterns as $bot) {
    if (str_contains($ua, $bot)) {
        return; // Bot erkannt, keine DB-Aktion
    }
}

$path = parse_url($page, PHP_URL_PATH) ?: '';
if (strlen($path) > 255) return;
if (preg_match('/(\/[a-z0-9\-_]+){4,}/i', $path)) {
    $segments = explode('/', trim($path, '/'));
    $counts = array_count_values($segments);
    foreach ($counts as $segment => $cnt) {
        if ($cnt > 3) return;
    }
}

$user_id = $_SESSION['userID'] ?? null;
$session_id = session_id();
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$is_guest = $user_id === null ? 1 : 0;
$now = date('Y-m-d H:i:s');

$user_id_int = $user_id === null ? null : intval($user_id);

// DB Verbindung $_database (mysqli) vorausgesetzt

// Cleanup veralteter Einträge (Timeout 10 Min)
$_database->query("DELETE FROM plugins_whoisonline WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

// Prüfen, ob Session existiert
$stmt = $_database->prepare("SELECT id, last_activity FROM plugins_whoisonline WHERE session_id = ?");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $last = strtotime($row['last_activity']);
    if (time() - $last < 30) {
        // Noch kein Update nötig (Throttling)
        return;
    }

    $stmt_update = $_database->prepare("UPDATE plugins_whoisonline SET user_id = ?, last_activity = ?, page = ?, ip_hash = ?, user_agent = ?, is_guest = ? WHERE session_id = ?");
    $stmt_update->bind_param(
        "issssis",
        $user_id_int,
        $now,
        $page,
        $ip_hash,
        $user_agent,
        $is_guest,
        $session_id
    );
    $stmt_update->execute();
    $stmt_update->close();

} else {
    $stmt_insert = $_database->prepare("INSERT INTO plugins_whoisonline (session_id, user_id, last_activity, page, ip_hash, user_agent, is_guest) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->bind_param(
        "sisssis",
        $session_id,
        $user_id_int,
        $now,
        $page,
        $ip_hash,
        $user_agent,
        $is_guest
    );
    $stmt_insert->execute();
    $stmt_insert->close();
}

$stmt->close();
