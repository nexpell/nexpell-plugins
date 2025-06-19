<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($page, PHP_URL_PATH);

// Schutzmaßnahmen
if (strlen($path) > 255) return;
if (preg_match('/(\/[a-z0-9\-_]+){4,}/i', $path)) {
    $segments = explode('/', trim($path, '/'));
    $count = array_count_values($segments);
    foreach ($count as $segment => $cnt) {
        if ($cnt > 3) return;
    }
}

$ext = pathinfo($path, PATHINFO_EXTENSION);
$allowed_ext = ['php', 'html', 'htm', ''];
if (!in_array(strtolower($ext), $allowed_ext)) return;

// Bot-Erkennung
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ua = strtolower($user_agent);
$bot_patterns = ['bot', 'crawl', 'spider', 'slurp', 'wget', 'curl', 'python-requests', 'httpclient', 'scrapy', 'gptbot', 'semrush', 'ahrefs', 'uptimerobot'];
foreach ($bot_patterns as $bot) {
    if (str_contains($ua, $bot)) return;
}

// IP anonymisieren
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

// Referer validieren
$referer = $_SERVER['HTTP_REFERER'] ?? 'Direkt';
if (strlen($referer) > 300) $referer = 'Too long';

// Zeitstempel
$timestamp = date('Y-m-d H:i:s');

// Gerätetyp
if (preg_match('/mobile|iphone|ipod|android/i', $ua)) {
    $device_type = 'Mobile';
} elseif (preg_match('/ipad|tablet/i', $ua)) {
    $device_type = 'Tablet';
} else {
    $device_type = 'Desktop';
}

// Betriebssystem
if (preg_match('/windows nt 10.0/i', $ua)) $os = 'Windows 10';
elseif (preg_match('/windows nt 6.3/i', $ua)) $os = 'Windows 8.1';
elseif (preg_match('/windows nt 6.1/i', $ua)) $os = 'Windows 7';
elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'Mac OS X';
elseif (preg_match('/linux/i', $ua)) $os = 'Linux';
elseif (preg_match('/android/i', $ua)) $os = 'Android';
elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';
else $os = 'Unbekannt';

// Browser
if (preg_match('/firefox\/([0-9\.]+)/i', $user_agent, $matches)) {
    $browser = 'Firefox ' . $matches[1];
} elseif (preg_match('/chrome\/([0-9\.]+)/i', $user_agent, $matches)) {
    $browser = 'Chrome ' . $matches[1];
} elseif (preg_match('/safari\/([0-9\.]+)/i', $user_agent, $matches) && !preg_match('/chrome/i', $ua)) {
    $browser = 'Safari ' . $matches[1];
} elseif (preg_match('/msie ([0-9\.]+)/i', $user_agent, $matches)) {
    $browser = 'Internet Explorer ' . $matches[1];
} elseif (preg_match('/edge\/([0-9\.]+)/i', $user_agent, $matches)) {
    $browser = 'Edge ' . $matches[1];
} else {
    $browser = 'Unbekannt';
}

// --- Besucher-Logik: nur alle 30 Minuten neu zählen ---
$interval_minutes = 30;
$time_limit = date('Y-m-d H:i:s', strtotime("-{$interval_minutes} minutes"));

$check_stmt = $_database->prepare("
    SELECT COUNT(*) FROM plugins_counter_visitors 
    WHERE ip_hash = ? AND timestamp >= ?
");
$check_stmt->bind_param("ss", $ip_hash, $time_limit);
$check_stmt->execute();
$check_stmt->bind_result($visitor_count);
$check_stmt->fetch();
$check_stmt->close();

// Wenn neuer Besucher: eintragen
if ($visitor_count === 0) {
    $insert_visitor = $_database->prepare("
        INSERT INTO plugins_counter_visitors 
        (ip_hash, timestamp, device_type, os, browser, referer, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insert_visitor->bind_param("sssssss", $ip_hash, $timestamp, $device_type, $os, $browser, $referer, $user_agent);
    $insert_visitor->execute();
    $insert_visitor->close();
}

// Immer Klick speichern
$insert_click = $_database->prepare("
    INSERT INTO plugins_counter_clicks (ip_hash, page, timestamp)
    VALUES (?, ?, ?)
");
$insert_click->bind_param("sss", $ip_hash, $page, $timestamp);
$insert_click->execute();
$insert_click->close();
