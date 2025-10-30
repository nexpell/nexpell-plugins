<?php
/**
 * /path/to/forum_like_toggle.php
 * AJAX-Endpoint: Like/Unlike für Forenposts mit Sperre für eigene Beiträge.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

// Nur POST zulassen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Eingeloggt?
$userID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
if ($userID <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

// Eingaben
$postID = isset($_POST['postID']) ? (int)$_POST['postID'] : 0;
$action = $_POST['action'] ?? '';
if ($postID <= 0 || !in_array($action, ['like', 'unlike'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

// === DB Bootstrap ===
$configPath = __DIR__ . '/../../../system/config.inc.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Konfigurationsdatei nicht gefunden']);
    exit;
}
require_once $configPath;

// Falls dein System bereits ein $_database und/oder safe_query bereitstellt, bitte diese nutzen.
// Hier: neutrale Initialisierung mit mysqli (utf8mb4).
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbank-Verbindungsfehler']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// --- Beitrag existiert? ---
$st = $mysqli->prepare('SELECT userID FROM plugins_forum_posts WHERE postID = ? LIMIT 1');
if (!$st) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB-Fehler (prepare A)']);
    exit;
}
$st->bind_param('i', $postID);
$st->execute();
$st->bind_result($postAuthorID);
$exists = $st->fetch();
$st->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Beitrag nicht gefunden']);
    exit;
}

// --- Harte Sperre: eigener Beitrag ---
if ((int)$postAuthorID === $userID) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Du kannst deinen eigenen Beitrag nicht liken.']);
    exit;
}

// --- Like/Unlike ---
if ($action === 'like') {
    // Eintrag erstellen (idempotent dank UNIQUE KEY (postID,userID))
    $st = $mysqli->prepare('INSERT IGNORE INTO plugins_forum_likes (postID, userID, created_at) VALUES (?, ?, NOW())');
    if (!$st) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB-Fehler (prepare B)']);
        exit;
    }
    $st->bind_param('ii', $postID, $userID);
    $st->execute();
    $st->close();
} else { // unlike
    $st = $mysqli->prepare('DELETE FROM plugins_forum_likes WHERE postID = ? AND userID = ?');
    if (!$st) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB-Fehler (prepare C)']);
        exit;
    }
    $st->bind_param('ii', $postID, $userID);
    $st->execute();
    $st->close();
}

// --- Neue Like-Anzahl holen ---
$st = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM plugins_forum_likes WHERE postID = ?');
if (!$st) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB-Fehler (prepare D)']);
    exit;
}
$st->bind_param('i', $postID);
$st->execute();
$st->bind_result($likes);
$st->fetch();
$st->close();

echo json_encode(['success' => true, 'likes' => (int)$likes]);
