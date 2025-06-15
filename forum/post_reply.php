<?php
// includes/plugins/forum/post_reply.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

session_start();
$userID = $_SESSION['userID'] ?? 0;
if ($userID == 0) {
    die("Bitte zuerst einloggen, um zu antworten.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $threadID = intval($_POST['threadID'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if ($threadID <= 0 || $content === '') {
        die("Ungültige Eingaben.");
    }

    $created_at = time();

    $content = mysqli_real_escape_string($_database, $content);

    safe_query("INSERT INTO plugins_forum_posts (threadID, userID, content, created_at) VALUES ($threadID, $userID, '$content', $created_at)");

    // Aktualisiere updated_at des Threads
    safe_query("UPDATE plugins_forum_threads SET updated_at = $created_at WHERE threadID = $threadID");

    header("Location: thread.php?id=$threadID");
    exit;
}

header("Location: index.php");
exit;
