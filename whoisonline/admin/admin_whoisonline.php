<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('whoisonline');

$res = safe_query("
    SELECT w.*, u.username
    FROM plugins_whoisonline w
    LEFT JOIN users u ON w.user_id = u.userID
    ORDER BY w.last_activity DESC
    LIMIT 10
");
?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> WhoIsOnline
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item active" aria-current="page">WhoIsOnline - Übersicht</li>
        </ol>
    </nav>  

    <div class="card-body">

<div class="container py-5">

    <h4>Angemeldete Benutzer</h4>
    <table class="table table-bordered table-striped bg-white shadow-sm">
                <thead class="table-light">
                    <tr><th>Benutzername</th><th>Aktuelle Seite</th><th>Letzte Aktivität</th><th>Gerät</th></tr></thead>
      <tbody>
      <?php
      while ($row = mysqli_fetch_assoc($res)) {
          $username = $row['username'] ?? 'Gast';
          $page = htmlspecialchars($row['page']);
          $last_activity = $row['last_activity'] ?? 'unbekannt';

          // Gerät erkennen
          $user_agent = strtolower($row['user_agent']);
          $device = 'Unbekannt';
          if (strpos($user_agent, 'mobile') !== false) $device = 'Mobilgerät';
          elseif (strpos($user_agent, 'windows') !== false) $device = 'Windows-PC';
          elseif (strpos($user_agent, 'macintosh') !== false) $device = 'Mac';
          elseif (strpos($user_agent, 'linux') !== false) $device = 'Linux-PC';

          echo "<tr>
              <td>".htmlspecialchars($username)."</td>
              <td>$page</td>
              <td>$last_activity</td>
              <td>$device</td>
          </tr>";
      }
      ?>
      </tbody>
      </table>
  </div></div></div>