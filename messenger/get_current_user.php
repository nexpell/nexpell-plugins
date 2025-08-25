<?php
// Sitzung starten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Überprüfe, ob die Benutzer-ID in der Sitzung existiert
if (isset($_SESSION['userID'])) {
    echo json_encode(['userID' => (int)$_SESSION['userID']]);
} else {
    // Wenn keine Benutzer-ID gefunden wird, gib einen Fehler zurück
    http_response_code(403);
    echo json_encode(['error' => 'Nicht angemeldet']);
}
?>