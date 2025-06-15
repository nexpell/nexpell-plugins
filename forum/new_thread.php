<?php
// includes/plugins/forum/new_thread.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

session_start();
$userID = $_SESSION['userID'] ?? 0;
if ($userID == 0) {
    die("Bitte zuerst einloggen, um ein neues Thema zu erstellen.");
}

$catID = intval($_GET['catID'] ?? 0);
if ($catID <= 0) {
    die("Ungültige Kategorie.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        die("Bitte Titel und Inhalt ausfüllen.");
    }

    $created_at = time();

    $title = mysqli_real_escape_string($_database, $title);
    $content = mysqli_real_escape_string($_database, $content);

    // Neuen Thread anlegen
    safe_query("INSERT INTO plugins_forum_threads (catID, userID, title, created_at, updated_at) VALUES ($catID, $userID, '$title', $created_at, $created_at)");
    $threadID = mysqli_insert_id($_database);

    // Ersten Beitrag anlegen
    safe_query("INSERT INTO plugins_forum_posts (threadID, userID, content, created_at) VALUES ($threadID, $userID, '$content', $created_at)");

    header("Location: thread.php?id=$threadID");
    exit;
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Neues Thema erstellen</title>
    <link rel="stylesheet" href="../../../themes/default/css/style.css" />
</head>
<body>
    <h1>Neues Thema erstellen</h1>
    <form method="post" action="">
        <label for="title">Titel:</label><br/>
        <input type="text" id="title" name="title" required /><br/><br/>

        <label for="content">Inhalt:</label><br/>
        <textarea id="content" name="content" rows="8" cols="80" required></textarea><br/><br/>

        <button type="submit">Thema erstellen</button>
    </form>

    <p><a href="index.php">Zurück zur Übersicht</a></p>
</body>
</html>
