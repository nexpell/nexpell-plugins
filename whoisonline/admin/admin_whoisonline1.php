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
            
            $last_activity_raw = $row['last_activity'] ?? null;
            if ($last_activity_raw) {
                $dt = new DateTime($last_activity_raw);
                $last_activity = $dt->format('d.m.Y H:i');
            } else {
                $last_activity = 'unbekannt';
            }

            $user_agent = strtolower($row['user_agent']);
            $device = 'Unbekannt';

            if (preg_match('/mobile|iphone|ipod|android|blackberry|phone/i', $user_agent)) {
                $device = 'Mobilgerät';
            } elseif (preg_match('/ipad|tablet/i', $user_agent)) {
                $device = 'Tablet';
            } elseif (preg_match('/windows nt 10.0|windows nt 6.3|windows nt 6.1|windows nt 6.2/i', $user_agent)) {
                $device = 'Windows-PC';
            } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
                $device = 'Mac';
            } elseif (preg_match('/linux/i', $user_agent)) {
                $device = 'Linux-PC';
            } elseif (preg_match('/cros/i', $user_agent)) {
                $device = 'Chrome OS';
            } else {
                $device = 'Unbekannt';
            }


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