<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

$userID = (int)$_SESSION['userID'];
$postID = isset($_POST['postID']) ? (int)$_POST['postID'] : 0;
$action = $_POST['action'] ?? '';

if ($postID <= 0 || !in_array($action, ['like', 'unlike'])) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

#require_once '/pfad/zu/deiner/datenbankverbindung.php'; // Passe den Pfad an
$configPath = __DIR__ . '/../../../system/config.inc.php';
if (!file_exists($configPath)) {
    die("Fehler: Konfigurationsdatei nicht gefunden.");
}
require_once $configPath;

// Datenbankverbindung aufbauen
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    die("Datenbank-Verbindungsfehler: " . $_database->connect_error);
}

// Hilfsfunktion safe_query annehmen oder eigene nutzen
function safe_query($query) {
    global $_database;
    return mysqli_query($_database, $query);
}

// Prüfen, ob Beitrag existiert
$res = safe_query("SELECT 1 FROM plugins_forum_posts WHERE postID = $postID LIMIT 1");
if (mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'error' => 'Beitrag nicht gefunden']);
    exit;
}

// Like hinzufügen oder entfernen
if ($action === 'like') {
    // Einfügen, falls nicht schon vorhanden
    safe_query("INSERT IGNORE INTO plugins_forum_likes (postID, userID) VALUES ($postID, $userID)");
} else {
    // Entfernen
    safe_query("DELETE FROM plugins_forum_likes WHERE postID = $postID AND userID = $userID");
}

// Neue Like-Anzahl holen
$res = safe_query("SELECT COUNT(*) AS cnt FROM plugins_forum_likes WHERE postID = $postID");
$row = mysqli_fetch_assoc($res);
$likes = (int)($row['cnt'] ?? 0);

echo json_encode(['success' => true, 'likes' => $likes]);
