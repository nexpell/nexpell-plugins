<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seite (z. B. URI)
$page = $_SERVER['REQUEST_URI'] ?? '';

// Dateiendung der Seite ermitteln
$ext = pathinfo(parse_url($page, PHP_URL_PATH), PATHINFO_EXTENSION);

// Erlaubte Erweiterungen (nur "echte" Seiten)
$allowed_ext = ['php', 'html', 'htm', ''];

// Nur erlaubte Seiten zählen, sonst abbrechen (Assets ignorieren)
if (!in_array(strtolower($ext), $allowed_ext)) {
    // Ignoriere Assets wie css, js, png, jpg, etc.
    return; // oder exit; je nach Kontext
}

// IP anonymisiert (SHA256)
$ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

// User-Agent auslesen
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Gerätetyp erkennen
$is_mobile = preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $user_agent);
$device_type = $is_mobile ? 'Mobile' : 'Desktop';

// Referer (falls vorhanden)
$referer = $_SERVER['HTTP_REFERER'] ?? 'Direkt';

// Zeitstempel jetzt
$timestamp = date('Y-m-d H:i:s');

// SQL-Einfüge-Anweisung
$sql = "
  INSERT INTO plugins_counter (ip, user_agent, referer, timestamp, page, device_type) 
  VALUES (
    '" . addslashes($ip) . "', 
    '" . addslashes($user_agent) . "', 
    '" . addslashes($referer) . "', 
    '$timestamp', 
    '" . addslashes($page) . "', 
    '$device_type'
  )";

// Query ausführen
safe_query($sql);
