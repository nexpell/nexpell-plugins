<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('gametracker');

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('gametracker');

use xPaw\SourceQuery\SourceQuery;
require __DIR__ . '/../GameQ/Autoloader.php';
use GameQ\GameQ;

function stripColorCodes(?string $text): string {
    return preg_replace('/\^\d/', '', $text ?? '');
}

// Server löschen
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    safe_query("DELETE FROM plugins_gametracker_servers WHERE id = " . $id);
    header("Location: admincenter.php?site=admin_gametracker");
    exit;
} 

// Server hinzufügen oder bearbeiten
if (isset($_POST['save_server'])) {
    $id = (int)$_POST['id'];
    $ip = escape($_POST['ip']);
    $port = (int)$_POST['port'];
    $query_port = isset($_POST['query_port']) ? (int)$_POST['query_port'] : null;
    $game = escape($_POST['game']);
    $game_pic = $_POST['game_pic'];
    $sort_order = (int)$_POST['sort_order'];
    $active = isset($_POST['active']) ? 1 : 0;

    if ($id > 0) {
        safe_query("UPDATE plugins_gametracker_servers SET 
            ip='$ip',
            port=$port,
            query_port=" . ($query_port !== null ? $query_port : 'NULL') . ",
            game='$game',
            game_pic='$game_pic',  -- NEU
            sort_order=$sort_order,
            active=$active 
            WHERE id=$id");
    } else {
        safe_query("INSERT INTO plugins_gametracker_servers 
            (ip, port, query_port, game, game_pic, sort_order, active) 
            VALUES (
                '$ip',
                $port,
                " . ($query_port !== null ? $query_port : 'NULL') . ",
                '$game',
                '$game_pic',  -- NEU
                $sort_order,
                $active
            )");
    }

    header("Location: admincenter.php?site=admin_gametracker");
    exit;
}


// Mapping Game-Typ zu GameTracker-Ordnernamen für Map-Bilder
    $imageFolderOverrides = [
        'coduo'       => 'uo',
        'cs16'     => 'cs',
        'css'         => 'cs_source',
        'dods'        => 'dod_source',
        'hl2mp'       => 'hl2dm',
        'tf'          => 'tfc',
        'ins'         => 'insurgency',
        'gmod'        => 'garrysmod',
        'l4d2'        => 'left4dead2',
        'l4d'         => 'left4dead',
        'arma'        => 'arma',
        'arma2'       => 'arma2',
        'arma3'       => 'arma3',
        'samp'        => 'samp',
        'mta'         => 'mta',
        'fivem'       => 'fivem',
        'ut'          => 'ut',
        'ut2003'      => 'ut2003',
        'ut2004'      => 'ut2004',
        'ut3'         => 'ut3',
        'quake3'      => 'q3a',
        'quake4'      => 'q4',
        'cod'         => 'cod',
        'cod2'        => 'cod2',
        'cod4'        => 'cod4',
        'codmw2'      => 'codmw2',
        'codmw3'      => 'codmw3',
        'codbo'       => 'codbo',
        'codbo2'      => 'codbo2',
        'codwaw'      => 'codwaw',
        'mohaa'       => 'mohaa',
        'moh'         => 'moh',
        'mohwf'       => 'mohwf',
        'bf1942'      => 'bf1942',
        'bfv'         => 'bfv',
        'bf2'         => 'bf2',
        'bf2142'      => 'bf2142',
        'bf3'         => 'bf3',
        'bf4'         => 'bf4',
        'bfbc2'       => 'bfbc2',
        'ravenshield' => 'ravenshield',
        'sof2'        => 'sof2',
        'et'          => 'et',
        'rtcw'        => 'rtcw',
        'minecraft'   => 'minecraft',
        'terraria'    => 'terraria',
        'rust'        => 'rust',
        'valheim'     => 'valheim',
        'hurtworld'   => 'hurtworld',
        '7dtd'        => '7daystodie',
        'factorio'    => 'factorio',
        'conan'       => 'conanexiles',
        'ark'         => 'arkse',
        'squad'       => 'squad',
        'unturned'    => 'unturned',
    ];
$modFriendlyNames = [
    'cs16'           => 'Counter-Strike 1.6',
    'cs16'           => 'Counter-Strike: Condition Zero',  // 'czero' → 'cs16'
    'css'            => 'Counter-Strike: Source',
    'css'            => 'Counter-Strike: Source',          // 'cs_source' → 'css'
    'csgo'           => 'Counter-Strike: Global Offensive',
    'cod'            => 'Call of Duty',
    'coduo'          => 'Call of Duty: United Offensive',
    'cod2'           => 'Call of Duty 2',
    'cod4'           => 'Call of Duty 4: Modern Warfare',
    'codmw2'         => 'Call of Duty: Modern Warfare 2',
    'codmw3'         => 'Call of Duty: Modern Warfare 3',
    'codwaw'         => 'Call of Duty: World at War',
    'codbo'          => 'Call of Duty: Black Ops',
    'codbo2'         => 'Call of Duty: Black Ops II',
    'bf1942'         => 'Battlefield 1942',
    'bfv'            => 'Battlefield Vietnam',
    'bf2'            => 'Battlefield 2',
    'bf2142'         => 'Battlefield 2142',
    'bf3'            => 'Battlefield 3',
    'bf4'            => 'Battlefield 4',
    'bfbc2'          => 'Battlefield: Bad Company 2',
    'hl'             => 'Half-Life',
    'hl2'            => 'Half-Life 2',
    'hl2mp'          => 'Half-Life 2 Deathmatch',
    'dod'            => 'Day of Defeat',
    'dod_source'     => 'Day of Defeat: Source',
    'tf'             => 'Team Fortress Classic',
    'tf2'            => 'Team Fortress 2',
    'gmod'           => 'Garry\'s Mod',
    'gmod'           => 'Garry\'s Mod',                    // 'garrysmod' → 'gmod'
    'ut'             => 'Unreal Tournament',
    'ut2003'         => 'Unreal Tournament 2003',
    'ut2004'         => 'Unreal Tournament 2004',
    'ut3'            => 'Unreal Tournament 3',
    'q3a'            => 'Quake III Arena',
    'q4'             => 'Quake 4',
    'samp'           => 'San Andreas Multiplayer',
    'mta'            => 'Multi Theft Auto',
    'mohaa'          => 'Medal of Honor: Allied Assault',
    'moh'            => 'Medal of Honor',
    'mohwf'          => 'Medal of Honor: Warfighter',
    'rtcw'           => 'Return to Castle Wolfenstein',
    'et'             => 'Wolfenstein: Enemy Territory',
    'sof2'           => 'Soldier of Fortune II',
    'ravenshield'    => 'Rainbow Six 3: Raven Shield',
    'minecraft'      => 'Minecraft',
    'unturned'       => 'Unturned',
    'rust'           => 'Rust',
    'valheim'        => 'Valheim',
    'conanexiles'    => 'Conan Exiles',
    '7daystodie'     => '7 Days to Die',
    'hurtworld'      => 'Hurtworld',
    'arkse'          => 'ARK: Survival Evolved',
    'dayz'           => 'DayZ',
    'terraria'       => 'Terraria',
    'arma'           => 'ARMA: Cold War Assault',
    'arma2'          => 'ARMA 2',
    'arma3'          => 'ARMA 3',
    'insurgency'     => 'Insurgency',
    'insurgency'     => 'Insurgency',                      // 'ins' → 'insurgency'
    'squad'          => 'Squad',
    'fivem'          => 'FiveM',
    'factorio'       => 'Factorio',

];

// Bearbeiten-Formular anzeigen
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $res = safe_query("SELECT * FROM plugins_gametracker_servers WHERE id = $editId");
    $server = mysqli_fetch_array($res);
    
    echo '<h4>Server bearbeiten</h4>';
    echo '<form method="post">';
    echo '<input type="hidden" name="id" value="' . $server['id'] . '">';
    echo '<div class="mb-3"><label>IP:</label><input class="form-control" name="ip" value="' . htmlspecialchars($server['ip']) . '" required></div>';
    echo '<div class="mb-3"><label>Port:</label><input class="form-control" name="port" value="' . $server['port'] . '" required></div>';
    echo '<div class="mb-3"><label>Query-Port (optional):</label><input class="form-control" name="query_port" value="' . (int)$server['query_port'] . '"></div>'; 

    // Hole das aktuelle Spiel (für Edit-Modus) oder leere Auswahl (Add-Modus)
    $currentGame = $server['game'] ?? '';

    // Berechne das Bildverzeichnis (override oder fallback auf Spielname)
    $gamePic = $imageFolderOverrides[$currentGame] ?? $currentGame;

    // Game-Auswahl-Block
    echo '<div class="mb-3"><label>Game:</label>';
    echo '<select class="form-select" name="game" id="gameSelect" required>';
    echo '<option value="" disabled' . ($currentGame ? '' : ' selected') . '>Bitte wählen</option>';
    foreach ($modFriendlyNames as $value => $label) {
        $selected = ($currentGame === $value) ? ' selected' : '';
        echo "<option value=\"$value\"$selected>$label</option>";
    }
    echo '</select>';

    // Hidden-Feld mit initialem game_pic-Wert
    echo "<input type=\"hidden\" name=\"game_pic\" id=\"gamePic\" value=\"$gamePic\">";
    echo '</div>';

    // JavaScript zum dynamischen Überschreiben bei Auswahländerung
    $imageOverridesJson = json_encode($imageFolderOverrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo <<<EOT
    <script>
        const imageFolderOverrides = $imageOverridesJson;

        document.getElementById('gameSelect').addEventListener('change', function() {
            const selectedGame = this.value;
            const override = imageFolderOverrides[selectedGame] || selectedGame;
            document.getElementById('gamePic').value = override;
        });
    </script>
    EOT;
    
    echo '<div class="mb-3"><label>Sortierung:</label><input class="form-control" name="sort_order" value="' . $server['sort_order'] . '"></div>';
    echo '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="active"' . ($server['active'] ? ' checked' : '') . '> Aktiv</div>';
    echo '<button class="btn btn-primary" type="submit" name="save_server">Speichern</button>';
    echo '</form>';
    return;
}


// Neu-Formular anzeigen
if (isset($_GET['action']) && $_GET['action'] === 'add') {

    $gamePic = ''; // << Initialisierung

    echo '<h4>Neuen Server hinzufügen</h4>';
    echo '<form method="post">';
    echo '<input type="hidden" name="id" value="0">';
    echo '<div class="mb-3"><label>IP:</label><input class="form-control" name="ip" required></div>';
    echo '<div class="mb-3"><label>Port:</label><input class="form-control" name="port" required></div>';
    echo '<div class="mb-3"><label>Query-Port (optional):</label><input class="form-control" name="query_port"></div>';

    echo '<div class="mb-3"><label>Game:</label>';
    echo '<select class="form-select" name="game" id="gameSelect" required>';
    echo '<option value="" disabled selected>Bitte wählen</option>';
    foreach ($modFriendlyNames as $value => $label) {
        echo "<option value=\"$value\">$label</option>";
    }
    echo '</select>';
    // Hidden-Feld mit initialem game_pic-Wert
    echo "<input type=\"hidden\" name=\"game_pic\" id=\"gamePic\" value=\"$gamePic\">";
    echo '</div>';

    // JavaScript zum dynamischen Überschreiben bei Auswahländerung
    $imageOverridesJson = json_encode($imageFolderOverrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo <<<EOT
    <script>
        const imageFolderOverrides = $imageOverridesJson;

        document.getElementById('gameSelect').addEventListener('change', function() {
            const selectedGame = this.value;
            const override = imageFolderOverrides[selectedGame] || selectedGame;
            document.getElementById('gamePic').value = override;
        });
    </script>
    EOT;

    echo '<div class="mb-3"><label>Sortierung:</label><input class="form-control" name="sort_order" value="1"></div>';
    echo '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="active" checked> Aktiv</div>';
    echo '<button class="btn btn-success" type="submit" name="save_server">Hinzufügen</button>';
    echo '</form>';
    return;
}



// Am Ende: Link zum Hinzufügen
echo '<a href="admincenter.php?site=admin_gametracker&action=add" class="btn btn-success mb-4">+ Neuen Server hinzufügen</a>';

// Lade alle aktiven Server aus der DB
$servers = safe_query("SELECT * FROM plugins_gametracker_servers WHERE active = 1 ORDER BY sort_order");

if (mysqli_num_rows($servers)) {
    $queryList = [];
    while ($ds = mysqli_fetch_array($servers)) {
        $queryList[] = [
            'id'         => 'server_' . (int)$ds['id'],
            'type'       => strtolower($ds['game']),
            'host'       => $ds['ip'] . ':' . $ds['port'],
            'game'       => strtolower($ds['game']),
            'game_pic'       => strtolower($ds['game_pic']),
            'ip'         => $ds['ip'],
            'port'       => $ds['port'],
            'query_port' => $ds['query_port']
        ];
    }    

    // GameQ-Objekt initialisieren und Server hinzufügen
    $gq = new GameQ();
    $gq->addServers($queryList);
    $results = $gq->process();

    echo '<div class="row">';

    foreach ($queryList as $server) {
        $id = $server['id'];
        $info = $results[$id] ?? null;

        echo '<div class="col-md-6 col-lg-4">';
        echo '<div class="card h-70 shadow-sm">';
        echo '<div class="card-header">';
        echo '<strong>' . htmlspecialchars(stripColorCodes($info['gq_hostname'] ?? $server['name'] ?? '')) . '</strong>';
        echo '</div><div class="card-body">';

        if (!empty($info['gq_online'])) {
            $gameRaw = $server['game'];
            $modName = strtolower($info['gq_mod'] ?? '');
            $gqMod = $modName ?: $gameRaw;
            
            $detectedGame = $modFriendlyNames[$gqMod] ?? ($modFriendlyNames[$gameRaw] ?? ucfirst($gqMod));

            echo '<div class="row">';
            // Linke Spalte: Text-Infos
            echo '<div class="col-8">';
            echo '<strong>Spiel:</strong> ' . htmlspecialchars($detectedGame) . '<br>';
            echo '<strong>Map:</strong> ' . htmlspecialchars($info['mapname'] ?? $info['map'] ?? '-') . '<br>';
            echo '<strong>Mod:</strong> ' . htmlspecialchars($info['gq_mod'] ?? '-') . '<br>';

            $players = (int)($info['gq_numplayers'] ?? 0);
            $maxPlayers = (int)($info['gq_maxplayers'] ?? 0);
            $badgeClass = ($players > 0) ? 'badge bg-success' : 'badge bg-secondary';
            echo '<strong>Spieler:</strong> <span class="' . $badgeClass . '">' . $players . ' / ' . $maxPlayers . '</span><br>';
            echo '<strong>Version:</strong> ' . htmlspecialchars($info['shortversion'] ?? $info['version'] ?? '-') . '<br>';
            echo '<strong>IP:</strong> ' . htmlspecialchars($server['ip']) . '<br>';
            echo '<strong>Port:</strong> ' . htmlspecialchars($server['port']) . '<br>';
            echo '<p><strong>Query-Port:</strong> ' . htmlspecialchars((string)($server['query_port'] ?? '')) . '<br>';
            echo '</div>';

            // Rechte Spalte: Map-Bild
            echo '<div class="col-4 text-end">';
            $mapname = strtolower($info['mapname'] ?? $info['map'] ?? '');
            $lookupGame = ($server['type'] === 'source' && $modName) ? $modName : $server['type'];
            
            $mapImagePath = "https://image.gametracker.com/images/maps/160x120/" . $server['game_pic'] . "/" . $mapname . ".jpg";
            
            $fallbackImage = '/includes/plugins/gametracker/images/map_no_image.jpg';
            echo '<img src="' . $mapImagePath . '" alt="' . htmlspecialchars($mapname) . '" class="img-fluid rounded shadow-sm" style="max-height:250px;" onerror="this.onerror=null;this.src=\'' . $fallbackImage . '\';">';

            echo '</div>'; // col-4

            echo '</div>'; // row

        } else {
        echo '<div class="alert alert-warning">Server offline oder nicht erreichbar.</div>';
        }
    echo '</div>'; // card-body
    
    echo '<div class="card-footer d-flex justify-content-between">';
echo '<a href="admincenter.php?site=admin_gametracker&action=edit&id=' . (int)str_replace('server_', '', $id) . '" class="btn btn-sm btn-outline-primary">Bearbeiten</a>';
echo '<a href="admincenter.php?site=admin_gametracker&delete=' . (int)str_replace('server_', '', $id) . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Diesen Server wirklich löschen?\')">Löschen</a>';
echo '</div>';

    echo '</div></div>';
    }

echo '</div>';
} else {
echo '<div class="alert alert-info">Es sind keine aktiven GameTracker-Server vorhanden.</div>';
}
?>
