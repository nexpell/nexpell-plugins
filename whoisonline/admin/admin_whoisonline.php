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

        <h4>Angemeldete Benutzer</h4>

        <div class="accordion" id="whoisonlineAccordion">
            <?php
            $index = 0;
            while ($row = mysqli_fetch_assoc($res)) {
                $index++;
                $id = 'whoisonlineItem' . $index;

                $username = $row['username'] ?? 'Gast';
                $page = htmlspecialchars($row['page'] ?? '');

                $last_activity_raw = $row['last_activity'] ?? null;
                if ($last_activity_raw) {
                    $dt = new DateTime($last_activity_raw);
                    $last_activity = $dt->format('d.m.Y H:i');
                } else {
                    $last_activity = 'unbekannt';
                }

                $user_agent_raw = $row['user_agent'] ?? '';
                $user_agent = !empty($user_agent_raw) ? htmlspecialchars($user_agent_raw) : 'Nicht verfügbar';

                $ip_hash = !empty($row['ip_hash']) ? htmlspecialchars($row['ip_hash']) : 'Nicht verfügbar';
                $session_id = !empty($row['session_id']) ? htmlspecialchars($row['session_id']) : 'Nicht verfügbar';

                $ua = strtolower($user_agent_raw);
                $device = 'Unbekannt';

                if (preg_match('/mobile|iphone|ipod|android|blackberry|phone/i', $ua)) {
                    $device = 'Mobilgerät';
                } elseif (preg_match('/ipad|tablet/i', $ua)) {
                    $device = 'Tablet';
                } elseif (preg_match('/windows nt 10.0|windows nt 6.3|windows nt 6.1|windows nt 6.2/i', $ua)) {
                    $device = 'Windows-PC';
                } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
                    $device = 'Mac';
                } elseif (preg_match('/linux/i', $ua)) {
                    $device = 'Linux-PC';
                } elseif (preg_match('/cros/i', $ua)) {
                    $device = 'Chrome OS';
                }
            ?>

            <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?= $index ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $id ?>" aria-expanded="false" aria-controls="<?= $id ?>">
                        <div class="d-flex justify-content-between w-100">
                            <span><?= htmlspecialchars($username) ?></span>
                            <span><?= $page ?></span>
                            <span><?= $last_activity ?></span>
                            <span><?= $device ?></span>
                        </div>
                    </button>
                </h2>
                <div id="<?= $id ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#whoisonlineAccordion">
                    <div class="accordion-body bg-light">
                        <strong>User-Agent:</strong> <?= $user_agent ?><br>
                        <strong>IP-Hash:</strong> <?= $ip_hash ?><br>
                        <strong>Session-ID:</strong> <?= $session_id ?>
                    </div>
                </div>
            </div>

            <?php
            }
            ?>
        </div>

    </div>
</div>
