<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fehler nur ins Log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/shoutbox_error.log');

function json_response($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}


// Konfigurationsdatei laden
$configPath = __DIR__ . '/../../../system/config.inc.php';
if (!file_exists($configPath)) {
    die("Fehler: Konfigurationsdatei nicht gefunden.");
}
require_once $configPath;

// Datenbankverbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    die("Verbindung zur Datenbank fehlgeschlagen: " . $_database->connect_error);
}

// DB-Verbindung sicherstellen
#global $_database;
if (!isset($_database) || !$_database instanceof mysqli) {
    http_response_code(500);
    json_response(['status' => 'error', 'message' => 'Datenbank nicht verbunden.']);
}

// Username aus Session
$username = $_SESSION['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernamePost = $username !== '' ? $username : trim($_POST['username'] ?? '');
    $messagePost = trim($_POST['message'] ?? '');

    if ($usernamePost === '' || $messagePost === '') {
        json_response(['status' => 'error', 'message' => 'Name und Nachricht sind erforderlich']);
    }
    if (mb_strlen($messagePost) > 500) {
        json_response(['status' => 'error', 'message' => 'Nachricht darf maximal 500 Zeichen lang sein']);
    }

    $stmt = $_database->prepare("INSERT INTO plugins_shoutbox_messages (timestamp, username, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        json_response(['status' => 'error', 'message' => 'DB Fehler (prepare): ' . $_database->error]);
    }

    $now = date('Y-m-d H:i:s');
    $stmt->bind_param('sss', $now, $usernamePost, $messagePost);

    if (!$stmt->execute()) {
        json_response(['status' => 'error', 'message' => 'DB Fehler (execute): ' . $stmt->error]);
    }
    $stmt->close();

    json_response(['status' => 'success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $_database->query("SELECT id, timestamp, username, message FROM plugins_shoutbox_messages ORDER BY id DESC LIMIT 100");
    if (!$result) {
        json_response(['status' => 'error', 'message' => 'DB-Fehler bei Abfrage: ' . $_database->error]);
    }

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id'        => (int)$row['id'],
            'timestamp' => $row['timestamp'],
            'username'  => $row['username'],
            'message'   => $row['message'],
        ];
    }
    $result->free();

    json_response(['status' => 'success', 'messages' => $messages]);
}

http_response_code(405); // Method not allowed
json_response(['status' => 'error', 'message' => 'Ungültige Anfragemethode']);
