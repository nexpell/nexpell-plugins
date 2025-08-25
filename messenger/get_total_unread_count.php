<?php
// Sitzung starten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../../system/config.inc.php';

$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB Fehler"]);
    exit();
}

// Holen Sie die aktuelle Benutzer-ID aus der Sitzung
$currentUser = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
if (!$currentUser) {
    http_response_code(403);
    echo json_encode(["error" => "Nicht angemeldet."]);
    exit();
}

// Zählen Sie die ungelesenen Nachrichten, die an den aktuellen Benutzer gesendet wurden
$stmt = $_database->prepare("
    SELECT COUNT(*) AS total_count 
    FROM plugins_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $currentUser);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "total_unread" => (int)$result['total_count']
]);
?>