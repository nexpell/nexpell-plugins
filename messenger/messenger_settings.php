<?php
// Sitzung starten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Überprüfe, ob der Benutzer angemeldet ist
if (!isset($_SESSION['userID'])) {
    http_response_code(403);
    echo json_encode(["error" => "Nicht angemeldet. Zugriff verweigert."]);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../../system/config.inc.php';

$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB Fehler"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$currentUser = (int)$_SESSION['userID']; // Aktuelle Benutzer-ID aus der Sitzung

if($method === 'GET') {
    $receiverId = isset($_GET['receiverId']) ? (int)$_GET['receiverId'] : 0;
    $afterId = isset($_GET['afterId']) ? (int)$_GET['afterId'] : 0;
    
    // Die SQL-Abfrage verwendet jetzt $currentUser korrekt
    $stmt = $_database->prepare("
        SELECT m.id, m.sender_id, m.receiver_id, m.text, m.timestamp, u.username AS sender_name
        FROM plugins_messages m
        JOIN users u ON m.sender_id = u.userID
        WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
        AND m.id > ?
        ORDER BY m.id ASC
    ");
    $stmt->bind_param("iiiii", $currentUser, $receiverId, $receiverId, $currentUser, $afterId);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode($messages);
    exit();
}

elseif($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if(!isset($data['receiver_id'], $data['text'])) {
        http_response_code(400);
        echo json_encode(["error"=>"Empfänger-ID und Text erforderlich"]);
        exit();
    }
    
    $stmt = $_database->prepare("INSERT INTO plugins_messages (sender_id, receiver_id, text) VALUES (?,?,?)");
    $stmt->bind_param("iis", $currentUser, $data['receiver_id'], $data['text']);
    $stmt->execute();
    
    echo json_encode(["message"=>"Gesendet","id"=>$_database->insert_id]);
    exit();
}

else {
    http_response_code(405);
    echo json_encode(["error"=>"Methode nicht erlaubt"]);
}
?>