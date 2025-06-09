<?php
use webspell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('lastlogin');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('lastlogin');

// Sprachdateien laden
$pm = new plugin_manager();

// Standard-Zeitraumklassen für farbliche Markierung
$colors = [
    2 => 'table-success',
    4 => 'table-warning',
    7 => 'table-danger',
    14 => 'table-info',
    30 => 'table-primary',
    90 => 'table-light',
    183 => 'table-secondary',
    365 => 'table-dark'
];

$squadTableExists = tableExists('plugins_squads_members');

// Filter und Sortierung aus GET holen
$searchUsername = trim($_GET['search_username'] ?? '');
$filterActivity = $_GET['filter_activity'] ?? 'all';
$filterSquad = $_GET['filter_squad'] ?? 'all';
$sortColumn = $_GET['sort'] ?? 'lastlogin';
$sortDirection = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Sicherheits-Whitelist für Sortierung
$allowedSortColumns = ['userID', 'username', 'lastlogin', 'activity', 'registerdate'];
if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'lastlogin';
}

// SQL vorbereiten (Prepared Statements)
$whereClauses = [];
$params = [];
$types = '';

// Suche Username
if ($searchUsername !== '') {
    $whereClauses[] = "u.username LIKE ?";
    $params[] = '%' . $searchUsername . '%';
    $types .= 's';
}

// Filter Aktivität
if ($filterActivity === 'active') {
    $whereClauses[] = "s.activity = 1";
} elseif ($filterActivity === 'inactive') {
    $whereClauses[] = "s.activity = 0";
}

// Filter Squad
if ($squadTableExists && $filterSquad !== 'all' && ctype_digit($filterSquad)) {
    $whereClauses[] = "s.squadID = ?";
    $params[] = (int)$filterSquad;
    $types .= 'i';
}

// Basis SQL (mit oder ohne Squad)
if ($squadTableExists) {
    $sql = "SELECT u.userID, u.username, u.lastlogin, u.email, u.registerdate, s.activity, s.squadID 
            FROM users u
            LEFT JOIN plugins_squads_members s ON u.userID = s.userID";
} else {
    $sql = "SELECT u.userID, u.username, u.lastlogin, u.email, u.registerdate, NULL as activity, NULL as squadID
            FROM users u";
}

// WHERE zusammenbauen
if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

// Sortierung
$sql .= " ORDER BY $sortColumn $sortDirection";

// Prepared Statement ausführen
$stmt = $_database->prepare($sql);
if ($stmt === false) {
    die("SQL Fehler: " . $_database->error);
}
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


// Squad-Auswahl für Filter (Dropdown)
$squads = [];
if ($squadTableExists) {
    $resSquad = $_database->query("SELECT squadID, name FROM plugins_squads ORDER BY name");
    while ($row = $resSquad->fetch_assoc()) {
        $squads[$row['squadID']] = $row['name'];
    }
}

?>

<!-- HTML & Filterformular -->

<div class="card">
    <div class="card-header"><i class="bi bi-person-fills"></i> <?= $languageService->get('lastlogin_activity_control') ?></div>
    <div class="card-body">

        <form method="get" action="admincenter.php" class="row g-3 mb-3" id="filterForm">
            <input type="hidden" name="site" value="admin_lastlogin">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search_username" placeholder="<?= $languageService->get('lastlogin_search_username') ?>" value="<?= htmlspecialchars($searchUsername) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="filter_activity">
                    <option value="all" <?= $filterActivity === 'all' ? 'selected' : '' ?>><?= $languageService->get('lastlogin_filter_all_activity') ?></option>
                    <option value="active" <?= $filterActivity === 'active' ? 'selected' : '' ?>><?= $languageService->get('lastlogin_activ') ?></option>
                    <option value="inactive" <?= $filterActivity === 'inactive' ? 'selected' : '' ?>><?= $languageService->get('lastlogin_inactiv') ?></option>
                </select>
            </div>
            <?php if ($squadTableExists): ?>
                <div class="col-md-3">
                    <select class="form-select" name="filter_squad">
                        <option value="all" <?= $filterSquad === 'all' ? 'selected' : '' ?>><?= $languageService->get('lastlogin_filter_all_squads') ?></option>
                        <?php foreach ($squads as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $filterSquad == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-2">
                <button type="submit" class="btn btn-danger w-100"><?= $languageService->get('lastlogin_submit') ?></button>
            </div>
        </form>

        <table id="userlistTable" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead>
                <tr>
                    <th><a href="<?= buildSortUrl('userID') ?>"><?= $languageService->get('lastlogin_id') ?></a></th>
                    <th><a href="<?= buildSortUrl('username') ?>"><?= $languageService->get('lastlogin_member') ?></a></th>
                    <?php if ($squadTableExists): ?>
                    <th><?= $languageService->get('lastlogin_squad') ?></th>
                    <?php endif; ?>
                    <th><a href="<?= buildSortUrl('lastlogin') ?>"><?= $languageService->get('lastlogin_lastlogin') ?></a></th>
                    <th><?= $languageService->get('lastlogin_in_days') ?></th>
                    <th><?= $languageService->get('lastlogin_activity') ?></th>
                    <th><a href="<?= buildSortUrl('email') ?>">E-Mail</a></th>
                    <th><a href="<?= buildSortUrl('registerdate') ?>">Registrierungsdatum</a></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $number = 1;
                while ($item = $result->fetch_assoc()):

                    $userID = htmlspecialchars($item['userID']);
                    $username = htmlspecialchars($item['username']);
                    $lastloginRaw = $item['lastlogin'];
                    $lastloginTimestamp = is_numeric($lastloginRaw) ? (int)$lastloginRaw : strtotime($lastloginRaw);
                    $lastloginTimestamp = $lastloginTimestamp ?: 0;

                    $loginDate = $lastloginTimestamp ? date("d.m.Y", $lastloginTimestamp) : '-';
                    $loginTime = $lastloginTimestamp ? date("H:i", $lastloginTimestamp) : '-';

                    $today = date("d.m.Y");
                    $yesterday = date("d.m.Y", strtotime("-1 day"));

                    // Berechnung Tage seit letztem Login
                    $daysSinceLogin = $lastloginTimestamp ? round((time() - $lastloginTimestamp) / 86400) : 9999;

                    if ($loginDate == $today) {
                        $tage = $languageService->get('lastlogin_today');
                    } elseif ($loginDate == $yesterday) {
                        $tage = $languageService->get('lastlogin_yesterday');
                    } elseif ($daysSinceLogin > 999) {
                        $tage = '-';
                    } else {
                        $tage = $languageService->get('lastlogin_before') . ' <b>' . $daysSinceLogin . '</b> ' . $languageService->get('lastlogin_days');
                    }

                    // Farb-Klasse bestimmen
                    $bgday = 'table-dark';
                    foreach ($colors as $limit => $class) {
                        if ($daysSinceLogin <= $limit) {
                            $bgday = $class;
                            break;
                        }
                    }

                    // Aktivitätsstatus
                    $activity = $item['activity'];
                    if ($activity === null) {
                        $aktiv = '-';
                        $bgaktiv = '';
                    } elseif ((int)$activity === 1) {
                        $aktiv = $languageService->get('lastlogin_activ');
                        $bgaktiv = 'table-success';
                    } else {
                        $aktiv = $languageService->get('lastlogin_inactiv');
                        $bgaktiv = 'table-danger';
                    }

                    // Squadname falls vorhanden
                    $squadname = ($squadTableExists && $item['squadID']) ? getsquadname($item['squadID']) : '-';

                    $email = htmlspecialchars($item['email'] ?? '-');
                    $registerdateRaw = $item['registerdate'] ?? null;
                    $registerdateTimestamp = $registerdateRaw ? (is_numeric($registerdateRaw) ? (int)$registerdateRaw : strtotime($registerdateRaw)) : 0;
                    $registerdate = $registerdateTimestamp ? date("d.m.Y", $registerdateTimestamp) : '-';

                    ?>
                    <tr>
                        <td><?= $number++ ?></td>
                        <td><a href="admincenter.php?site=users&action=profile&id=<?= $userID ?>" target="_blank"><?= $username ?></a></td>
                        <?php if ($squadTableExists): ?>
                        <td><?= htmlspecialchars($squadname) ?></td>
                        <?php endif; ?>
                        <td><b><?= $languageService->get('lastlogin_day') ?>:</b> <?= $loginDate ?> | <b><?= $languageService->get('lastlogin_clock') ?>:</b> <?= $loginTime ?></td>
                        <td class="<?= $bgday ?>"><?= $tage ?></td>
                        <td class="<?= $bgaktiv ?>" align="center"><?= $aktiv ?></td>
                        <td><?= $email ?></td>
                        <td><?= $registerdate ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</div>

<!-- DataTables & BootstrapJS & CSS -->
<link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" /> <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script> <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script> 

<script> $(document).ready(function() { $('#userlistTable').DataTable({ "paging": true, "lengthChange": false, "pageLength": 25, "searching": false, "ordering": false, "info": true, 
    "autoWidth": false, "language": { "url": "//cdn.datatables.net/plug-ins/1.13.5/i18n/de-DE.json" } }); }); </script> 
<?php // Hilfsfunktion: Sortier-URL bauen (sort & dir Parameter umschalten) 
function buildSortUrl($column) { $currentSort = $_GET['sort'] ?? 'lastlogin'; $currentDir = $_GET['dir'] ?? 'desc'; $newDir = 'asc'; if ($currentSort === $column && $currentDir === 'asc') { $newDir = 'desc'; } // Aktuelle Filter-Parameter übernehmen 
$params = $_GET; $params['sort'] = $column; $params['dir'] = $newDir; return 'admincenter.php?' . http_build_query($params); } // Helper Funktion: Squadname holen (wie in Originalcode) 
function getsquadname($squadID) { global $_database; $stmt = $_database->prepare("SELECT name FROM plugins_squads WHERE squadID = ?"); $stmt->bind_param("i", $squadID); $stmt->execute(); $stmt->bind_result($name); if ($stmt->fetch()) { return $name; } return '-'; } ?>
