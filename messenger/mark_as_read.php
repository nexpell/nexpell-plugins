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

$data = json_decode(file_get_contents('php://input'), true);
$currentUser = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
$senderId = isset($data['sender_id']) ? (int)$data['sender_id'] : 0;

if (!$currentUser || !$senderId) {
    http_response_code(400);
    echo json_encode(["error" => "Fehlende ID"]);
    exit();
}

// Markieren Sie alle Nachrichten, die der aktuelle Benutzer von senderId erhalten hat, als gelesen.
$stmt = $_database->prepare("UPDATE plugins_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
$stmt->bind_param("ii", $senderId, $currentUser);
$stmt->execute();

echo json_encode(["success" => true, "updated_rows" => $stmt->affected_rows]);
?>