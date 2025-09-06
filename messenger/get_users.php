<?php
// Sitzung starten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    echo json_encode(["error" => "DB Fehler: " . $_database->connect_error]);
    exit();
}

$currentUser = (int)$_SESSION['userID'];

// Holen Sie alle anderen Benutzer inkl. Avatar aus user_profiles
$stmt = $_database->prepare("
    SELECT 
        u.userID,
        u.username,
        up.avatar,
        (SELECT COUNT(*) FROM plugins_messages WHERE sender_id = u.userID AND receiver_id = ? AND is_read = 0) AS unread_count
    FROM users u
    LEFT JOIN user_profiles up ON up.userID = u.userID
    WHERE u.userID != ?
");
$stmt->bind_param("ii", $currentUser, $currentUser);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    // Avatar: eigenes Bild oder SVG-Fallback
    $avatar = !empty($row['avatar'])
        ? $row['avatar']
        : '/images/avatars/svg-avatar.php?name=' . urlencode($row['username']);

    $users[] = [
        "id" => (int)$row['userID'],
        "username" => $row['username'],
        "avatar" => $avatar,
        "unread_count" => (int)$row['unread_count']
    ];
}

echo json_encode($users);
?>
