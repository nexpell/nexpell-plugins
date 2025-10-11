<?php
// Sitzung starten
/*if (session_status() == PHP_SESSION_NONE) {
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

/**
 * 1. Nutzer, mit denen schon Nachrichten ausgetauscht wurden
 */
/*$stmt = $_database->prepare("
    SELECT 
        u.userID,
        u.username,
        up.avatar,
        (SELECT COUNT(*) FROM plugins_messages 
         WHERE sender_id = u.userID AND receiver_id = ? AND is_read = 0) AS unread_count
    FROM users u
    LEFT JOIN user_profiles up ON up.userID = u.userID
    WHERE u.userID != ?
      AND u.userID IN (
          SELECT DISTINCT sender_id FROM plugins_messages WHERE receiver_id = ?
          UNION
          SELECT DISTINCT receiver_id FROM plugins_messages WHERE sender_id = ?
      )
    ORDER BY u.username
");
$stmt->bind_param("iiii", $currentUser, $currentUser, $currentUser, $currentUser);
$stmt->execute();
$result = $stmt->get_result();

$chattedUsers = [];
while ($row = $result->fetch_assoc()) {
    $avatar = !empty($row['avatar'])
        ? $row['avatar']
        : '/images/avatars/svg-avatar.php?name=' . urlencode($row['username']);

    $chattedUsers[] = [
        "id" => (int)$row['userID'],
        "username" => $row['username'],
        "avatar" => $avatar,
        "unread_count" => (int)$row['unread_count']
    ];
}
$stmt->close();

/**
 * 2. Alle anderen Nutzer für Select-Auswahl
 */
/*$stmt2 = $_database->prepare("
    SELECT u.userID, u.username
    FROM users u
    WHERE u.userID != ?
      AND u.userID NOT IN (
          SELECT DISTINCT sender_id FROM plugins_messages WHERE receiver_id = ?
          UNION
          SELECT DISTINCT receiver_id FROM plugins_messages WHERE sender_id = ?
      )
    ORDER BY u.username
");
$stmt2->bind_param("iii", $currentUser, $currentUser, $currentUser);
$stmt2->execute();
$result2 = $stmt2->get_result();

$otherUsers = [];
while ($row = $result2->fetch_assoc()) {
    $otherUsers[] = [
        "id" => (int)$row['userID'],
        "username" => $row['username']
    ];
}
$stmt2->close();

echo json_encode([
    "chatted" => $chattedUsers,
    "others" => $otherUsers
]);*/



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['userID'])) {
    http_response_code(403);
    echo json_encode(["error" => "Nicht angemeldet. Zugriff verweigert."]);
    exit;
}

require_once __DIR__ . '/../../../system/config.inc.php';

$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB Fehler: " . $_database->connect_error]);
    exit;
}

$currentUser = (int)$_SESSION['userID'];

/**
 * 1️⃣ Nutzer, mit denen schon Nachrichten ausgetauscht wurden (distinct!)
 *    + Sortierung nach letzter Nachricht DESC
 */
$stmt = $_database->prepare("
    SELECT DISTINCT
        u.userID,
        u.username,
        COALESCE(up.avatar, '') AS avatar,
        (
            SELECT COUNT(*) 
            FROM plugins_messages 
            WHERE sender_id = u.userID 
              AND receiver_id = ? 
              AND is_read = 0
        ) AS unread_count,
        (
            SELECT MAX(timestamp)
            FROM plugins_messages
            WHERE (sender_id = u.userID AND receiver_id = ?)
               OR (receiver_id = u.userID AND sender_id = ?)
        ) AS last_message
    FROM users u
    LEFT JOIN user_profiles up ON up.userID = u.userID
    INNER JOIN (
        SELECT sender_id AS uid FROM plugins_messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id AS uid FROM plugins_messages WHERE sender_id = ?
    ) AS rel ON rel.uid = u.userID
    WHERE u.userID != ?
    ORDER BY last_message DESC, u.username ASC
");
$stmt->bind_param("iiiiii", $currentUser, $currentUser, $currentUser, $currentUser, $currentUser, $currentUser);
$stmt->execute();
$result = $stmt->get_result();

$chattedUsers = [];
while ($row = $result->fetch_assoc()) {
    $avatar = !empty($row['avatar'])
        ? $row['avatar']
        : '/images/avatars/svg-avatar.php?name=' . urlencode($row['username']);

    $chattedUsers[] = [
        "id" => (int)$row['userID'],
        "username" => $row['username'],
        "avatar" => $avatar,
        "unread_count" => (int)$row['unread_count'],
        "last_message" => $row['last_message']
    ];
}
$stmt->close();

/**
 * 2️⃣ Alle anderen Nutzer (noch kein Chat)
 */
$stmt2 = $_database->prepare("
    SELECT u.userID, u.username
    FROM users u
    WHERE u.userID != ?
      AND u.userID NOT IN (
          SELECT sender_id FROM plugins_messages WHERE receiver_id = ?
          UNION
          SELECT receiver_id FROM plugins_messages WHERE sender_id = ?
      )
    ORDER BY u.username
");
$stmt2->bind_param("iii", $currentUser, $currentUser, $currentUser);
$stmt2->execute();
$result2 = $stmt2->get_result();

$otherUsers = [];
while ($row = $result2->fetch_assoc()) {
    $otherUsers[] = [
        "id" => (int)$row['userID'],
        "username" => $row['username']
    ];
}
$stmt2->close();

echo json_encode([
    "chatted" => $chattedUsers,
    "others" => $otherUsers
], JSON_UNESCAPED_UNICODE);

