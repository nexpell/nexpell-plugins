<?php
// admin/plugins/forum/admin_users.php
require_once '../../../system/core.php';
require_once '../../../system/database.php';

session_start();
if (!isAdmin()) {
    die("Zugriff verweigert.");
}

// Benutzer laden
$res = safe_query("SELECT userID, username, email, created_at FROM users ORDER BY username ASC");
$users = [];
while ($row = mysqli_fetch_assoc($res)) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Benutzerübersicht</title>
    <link rel="stylesheet" href="../../../themes/default/css/admin.css" />
</head>
<body>
<h1>Benutzerübersicht</h1>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th><th>Benutzername</th><th>Email</th><th>Registriert am</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?=intval($user['userID'])?></td>
            <td><?=htmlspecialchars($user['username'])?></td>
            <td><?=htmlspecialchars($user['email'])?></td>
            <td><?=date('d.m.Y', $user['created_at'])?></td>
        </tr>
    <?php endforeach; ?>
</table>

<p><a href="../admincenter.php">Zurück zum Admincenter</a></p>
</body>
</html>
