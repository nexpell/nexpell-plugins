<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page = $_SERVER['REQUEST_URI'];
$ext = pathinfo(parse_url($page, PHP_URL_PATH), PATHINFO_EXTENSION);
$allowed_ext = ['php', 'html', 'htm', '']; 

if (!in_array(strtolower($ext), $allowed_ext)) {
    // Ignoriere Assets
    return;
}

$user_id = $_SESSION['userID'] ?? null;
$session_id = session_id();
$page = $_SERVER['REQUEST_URI'];
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR']);
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_guest = $user_id === null ? 1 : 0;
$now = date('Y-m-d H:i:s');

$user_id_sql = $user_id === null ? "NULL" : intval($user_id);

// Cleanup veralteter Einträge (Timeout 10 Min)
safe_query("DELETE FROM plugins_whoisonline WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

// Insert oder Update
$res = safe_query("SELECT id FROM plugins_whoisonline WHERE session_id = '$session_id'");

if (mysqli_num_rows($res) > 0) {
    safe_query("UPDATE plugins_whoisonline SET
        user_id = $user_id_sql,
        last_activity = '$now',
        page = '" . addslashes($page) . "',
        ip_hash = '$ip_hash',
        user_agent = '" . addslashes($user_agent) . "',
        is_guest = $is_guest
        WHERE session_id = '$session_id'");
} else {
    safe_query("INSERT INTO plugins_whoisonline
        (session_id, user_id, last_activity, page, ip_hash, user_agent, is_guest)
        VALUES
        ('$session_id', $user_id_sql, '$now', '" . addslashes($page) . "', '$ip_hash', '" . addslashes($user_agent) . "', $is_guest)");
}
