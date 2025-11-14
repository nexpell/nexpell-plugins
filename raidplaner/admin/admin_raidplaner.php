<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funktionen laden
require_once __DIR__ . '/../raidplaner-func.php';
echo '<link rel="stylesheet" href="/includes/plugins/raidplaner/css/raidplaner.css">';

// Benötigte Klassen und Funktionen einbinden
use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\SeoUrlHandler;

// Globale Variablen und Services initialisieren
global $languageService, $_database, $_SESSION;
$languageService->readPluginModule('raidplaner');
AccessControl::checkAdminAccess('raidplaner');

// CSRF-Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function validate_csrf_token($token) {
    global $languageService;
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'error' => $languageService->get('error_csrf')]));
    }
    return true;
}

global $_database, $languageService, $_SESSION;
if (!$_database || $_database->connect_errno) {
    die('<div class="alert alert-danger">❌ Datenbankverbindung fehlgeschlagen.</div>');
}

// URL-Parameter
// Lade alle Einstellungen aus der Datenbank
$settings_raw = $_database->query("SELECT setting_key, setting_value FROM plugins_raidplaner_settings")->fetch_all(MYSQLI_ASSOC);
$settings = array_column($settings_raw, 'setting_value', 'setting_key');

$view = $_REQUEST['view'] ?? 'overview';
$tab = $_REQUEST['tab'] ?? 'raids';
$event_id = intval($_REQUEST['event_id'] ?? 0);
$id = intval($_REQUEST['id'] ?? 0);

// ===================================================================
// AJAX-AKTIONEN
// ===================================================================
if (isset($_REQUEST['ajax'])) {
    validate_csrf_token($_REQUEST['csrf_token'] ?? '');
    $response = ['success' => false, 'error' => $languageService->get('error_unknown_action')];

    switch ($_REQUEST['ajax']) {

        // ----------------------------------------------------------
        // Charaktere eines Users (bestehender Case)
        // ----------------------------------------------------------
        case 'get_user_characters': {
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $stmt = $_database->prepare("SELECT id, character_name FROM plugins_raidplaner_characters WHERE userID = ? ORDER BY is_main DESC, character_name ASC");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $characters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $response = ['success' => true, 'characters' => $characters];
            }
            break;
        }

        // ----------------------------------------------------------
        // Vorlagen-Daten (Titel, Beschreibung, Dauer, Setup, Bosse)
        // ----------------------------------------------------------
        case 'get_template_data': {
            $template_id = intval($_POST['template_id'] ?? 0);
            if ($template_id > 0) {
                // Template-Stammdaten
                $stmt_tpl = $_database->prepare("
                    SELECT 
                        COALESCE(title, '') AS title,
                        COALESCE(description, '') AS description,
                        COALESCE(duration_minutes, 180) AS duration_minutes
                    FROM plugins_raidplaner_templates 
                    WHERE id = ?
                ");
                $stmt_tpl->bind_param("i", $template_id);
                $stmt_tpl->execute();
                $template_data = $stmt_tpl->get_result()->fetch_assoc();
                $stmt_tpl->close();

                if ($template_data) {
                    // Zeilenumbrüche/Dummy-Escapes normalisieren
                    $template_data['description'] = str_replace(
                        ["\\r\\n","\\n","\\r","\\'"],
                        ["\n","\n","\n","'"],
                        $template_data['description']
                    );
                }

                // Setup-Daten (role_id, needed_count)
                $stmt_setup = $_database->prepare("
                    SELECT role_id, needed_count 
                    FROM plugins_raidplaner_template_setup 
                    WHERE template_id = ?
                ");
                $stmt_setup->bind_param("i", $template_id);
                $stmt_setup->execute();
                $setup_data = $stmt_setup->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_setup->close();

                // Boss-IDs der Vorlage
                $stmt_boss = $_database->prepare("
                    SELECT boss_id 
                    FROM plugins_raidplaner_template_bosses 
                    WHERE template_id = ?
                ");
                $stmt_boss->bind_param("i", $template_id);
                $stmt_boss->execute();
                $boss_rows = $stmt_boss->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_boss->close();

                $boss_ids = array_map('intval', array_column($boss_rows ?: [], 'boss_id'));

                $response = [
                    'success' => true,
                    'data' => array_merge(
                        ($template_data ?: ['title' => '', 'description' => '', 'duration_minutes' => 180]),
                        ['boss_ids' => $boss_ids]
                    ),
                    'setup' => $setup_data ?: []
                ];
            }
            break;
        }

        // ----------------------------------------------------------
        // Toggle BiS
        // ----------------------------------------------------------
        case 'toggle_bis': {
            $item_id  = intval($_POST['item_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            $is_bis   = filter_var($_POST['is_bis'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($item_id > 0 && $class_id > 0) {
                if ($is_bis) {
                    $stmt = $_database->prepare("INSERT IGNORE INTO plugins_raidplaner_bis_list (class_id, item_id) VALUES (?, ?)");
                } else {
                    $stmt = $_database->prepare("DELETE FROM plugins_raidplaner_bis_list WHERE class_id = ? AND item_id = ?");
                }
                $stmt->bind_param("ii", $class_id, $item_id);
                if ($stmt->execute()) {
                    $response = ['success' => true];
                } else {
                    $response['error'] = $languageService->get('error_db_operation');
                }
                $stmt->close();
            } else {
                $response['error'] = $languageService->get('error_invalid_ids');
            }
            break;
        }

        // ----------------------------------------------------------
        // Paginierte Boss-Liste für das Modal
        // ----------------------------------------------------------
        case 'list_bosses': {
            $q      = trim((string)($_REQUEST['query'] ?? ''));
            $page   = max(1, (int)($_REQUEST['page'] ?? 1));
            $limit  = min(100, max(10, (int)($_REQUEST['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            // Tabelle vorhanden?
            $tbl = $_database->query("SHOW TABLES LIKE 'plugins_raidplaner_bosses'");
            if (!$tbl || $tbl->num_rows === 0) {
                $response = ['success'=>true,'items'=>[],'total'=>0,'page'=>$page,'has_more'=>false,'note'=>'table_missing'];
                break;
            }
            $nameCol = 'boss_name';

            // COUNT
            $total = 0;
            if ($q !== '') {
                $qEsc = $_database->real_escape_string($q);
                $sqlCnt = "SELECT COUNT(*) AS cnt FROM plugins_raidplaner_bosses WHERE {$nameCol} LIKE '%{$qEsc}%'";
            } else {
                $sqlCnt = "SELECT COUNT(*) AS cnt FROM plugins_raidplaner_bosses";
            }
            if ($resCnt = $_database->query($sqlCnt)) {
                $row = $resCnt->fetch_assoc();
                $total = (int)($row['cnt'] ?? 0);
                $resCnt->free();
            }

            // ITEMS
            $items  = [];
            $limit  = (int)$limit;
            $offset = (int)$offset;

            if ($q !== '') {
                $qEsc = $_database->real_escape_string($q);
                $sql = "
                    SELECT id, {$nameCol} AS boss_name
                    FROM plugins_raidplaner_bosses
                    WHERE {$nameCol} LIKE '%{$qEsc}%'
                    ORDER BY {$nameCol}, id
                    LIMIT {$limit} OFFSET {$offset}
                ";
            } else {
                $sql = "
                    SELECT id, {$nameCol} AS boss_name
                    FROM plugins_raidplaner_bosses
                    ORDER BY {$nameCol}, id
                    LIMIT {$limit} OFFSET {$offset}
                ";
            }

            if ($res = $_database->query($sql)) {
                while ($r = $res->fetch_assoc()) {
                    $cleanName = stripslashes((string)$r['boss_name']);
                    $items[] = ['id' => (int)$r['id'], 'boss_name' => $cleanName];
                }
                $res->free();
            }

            $response = [
                'success'  => true,
                'items'    => $items,
                'total'    => $total,
                'page'     => $page,
                'has_more' => ($offset + $limit < $total),
            ];
            break;
        }

        // ----------------------------------------------------------
        // Namen zu Boss-IDs (für Chips)
        // ----------------------------------------------------------
        case 'bosses_by_ids': {
            $idsRaw = (string)($_REQUEST['ids'] ?? '');
            $ids = [];
            foreach (explode(',', $idsRaw) as $p) {
                $n = (int)trim($p);
                if ($n > 0) $ids[] = $n;
            }
            $ids = array_values(array_unique($ids));

            $items = [];

            $tbl = $_database->query("SHOW TABLES LIKE 'plugins_raidplaner_bosses'");
            if ($tbl && $tbl->num_rows > 0 && !empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $sql = "
                    SELECT id, boss_name
                    FROM plugins_raidplaner_bosses
                    WHERE id IN ({$in})
                    ORDER BY boss_name, id
                ";
                if ($res = $_database->query($sql)) {
                    while ($r = $res->fetch_assoc()) {
                        $cleanName = stripslashes((string)$r['boss_name']);
                        $items[] = ['id'=>(int)$r['id'], 'boss_name'=>$cleanName];
                    }
                    $res->free();
                }
            }
            $response = ['success' => true, 'items' => $items];
            break;
        }
        default: {
            break;
        }
    }

    // JSON-Antwort
    if (ob_get_length()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response);
    exit;
}
// ===================================================================
// POST & LÖSCH-AKTIONEN
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_REQUEST['ajax'])) {
    validate_csrf_token($_POST['csrf_token'] ?? '');

    // --- Manager-Instanzen erstellen ---
    $itemManager = new ItemManager($_database);
    $bossManager = new BossManager($_database);
    $templateManager = new TemplateManager($_database);
    $raidManager = new RaidManager($_database, $settings);

    // --- LÖSCH-AKTIONEN ---
    if (isset($_POST['delete'])) {
        $delete_id = intval($_POST['delete_id'] ?? 0);
        $type = $_POST['delete'];

        if ($type === 'raid') {
            // Löschverhalten der Raids / mit oder ohne Itemvergaben
            $mode = $_POST['delete_mode'] ?? 'purge';

            if ($mode === 'purge') {
                // Raid & Loot-Historie löschen
                $raidManager->deleteWithLootPurge($delete_id);
            } else {
                // Nur Raid löschen (Loot-Historie bleibt bestehen)
                $raidManager->delete($delete_id);
            }

            header("Location: admincenter.php?site=admin_raidplaner&tab=raids");
            exit;
        }
        if ($type === 'template') {
            $templateManager->delete($delete_id);
            header("Location: admincenter.php?site=admin_raidplaner&tab=templates");
            exit;
        }
        if ($type === 'item') {
            $itemManager->deleteItem($delete_id);
            header("Location: admincenter.php?site=admin_raidplaner&tab=items");
            exit;
        }
        if ($type === 'boss') {
            $bossManager->delete($delete_id);
            header("Location: admincenter.php?site=admin_raidplaner&tab=bosses");
            exit;
        }
        if ($type === 'class') {
            $_database->prepare("DELETE FROM plugins_raidplaner_classes WHERE id = ?")->execute([$delete_id]);
            header("Location: admincenter.php?site=admin_raidplaner&tab=settings");
            exit;
        }
        if ($type === 'loot') {
            $lootManager = new LootManager($_database);
            $lootManager->delete((int)$delete_id);
            
            $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
            if ($eventId > 0) {
                header("Location: admincenter.php?site=admin_raidplaner&view=manage_loot&event_id=" . $eventId);
                exit;
            }
            header("Location: admincenter.php?site=admin_raidplaner&tab=wishlists");
            exit;
        }
        if ($type === 'role') {
            $_database->prepare("DELETE FROM plugins_raidplaner_roles WHERE id = ?")->execute([$delete_id]);
            header("Location: admincenter.php?site=admin_raidplaner&tab=settings");
            exit;
        }
    }

// --- SPEICHER-AKTIONEN ---
// =============================================
// RAID SPEICHERN
// =============================================

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['save_raid']) || isset($_POST['save_and_post_raid']))) {

    // CSRF prüfen
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        // Zur passenden Seite zurück mit Meldung
        $backId = (int)($_POST['event_id'] ?? 0);
        if ($backId > 0) {
            header('Location: admincenter.php?site=admin_raidplaner&view=edit_raid&event_id=' . $backId . '&msg=csrf');
        } else {
            header('Location: admincenter.php?site=admin_raidplaner&tab=raids&msg=csrf');
        }
        exit;
    }

    error_log('[raidplaner] POST keys: ' . implode(',', array_keys($_POST)));
    error_log('[raidplaner] post_to_discord=' . ($_POST['post_to_discord'] ?? 'N/A') . '; save_and_post_raid=' . ($_POST['save_and_post_raid'] ?? 'N/A'));

    // Eingaben vorbereiten
    $event_id_raw = $_POST['event_id'] ?? 0;
    $event_id     = (int)$event_id_raw;

    // applied_template_id: '' oder '0' => KEINE Vorlage => NULL
    $tpl_raw     = $_POST['applied_template_id'] ?? '';
    $applied_tpl = (trim((string)$tpl_raw) === '' || (string)$tpl_raw === '0') ? null : (int)$tpl_raw;
    $isUpdate    = ($event_id > 0);

    // Felder
    $title       = trim((string)($_POST['title'] ?? ''));
    $event_time  = (string)($_POST['event_time'] ?? '');
    $duration    = (int)($_POST['duration'] ?? ($_POST['duration_minutes'] ?? 180));
    $desc        = (string)($_POST['description'] ?? '');

    // ---------- Abwärtskompatible Feld-Aliasse für RaidManager::save() ----------
    $_POST['id']               = $event_id;
    $_POST['event_id']         = $event_id;
    $_POST['title']            = $title;
    $_POST['raid_title']       = $title;
    $_POST['raidname']         = $title;
    $_POST['name']             = $title;
    $_POST['description']      = $desc;
    $_POST['event_time']       = $event_time;
    $_POST['duration']         = $duration;
    $_POST['duration_minutes'] = $duration;
    $_POST['template_id']      = ($applied_tpl === null) ? null : (int)$applied_tpl;

    // ---------- Raid speichern ----------
    $raidManager = new RaidManager($_database, $settings);
    $saved_event_id = 0;
    if ($isUpdate) {
        if ($event_id > 0) {
            $saved_event_id = (int)$event_id;
        } else {
            error_log('[raidplaner] Update ohne event_id – Discord-Post abgebrochen.');
        }
    }
    $needsUserId   = false;
    $currentUserId = (int)($_SESSION['userID'] ?? $_POST['user_id'] ?? 0);

    try {
        $rm = new ReflectionMethod($raidManager, 'save');
        $needsUserId = ($rm->getNumberOfParameters() >= 2);
    } catch (Throwable $ignore) {
    }

    try {
        $saveResult = (
            $needsUserId
                ? $raidManager->save($_POST, $currentUserId)
                : $raidManager->save($_POST)
        );
        if (!$isUpdate) {
            $saved_event_id = (int)$saveResult; 
        }
    } catch (Throwable $e) {
        error_log('[raidplaner] save() failed: ' . $e->getMessage());
        $saved_event_id = 0;
    }

    if ($saved_event_id <= 0) {
        $backId = ($event_id > 0) ? $event_id : 0;
        $qs = 'site=admin_raidplaner&view=edit_raid';
        if ($backId > 0) $qs .= '&event_id=' . $backId;
        header('Location: admincenter.php?' . $qs . '&msg=error');
        exit;
    } else {
        if ($applied_tpl === null) {
            // KEINE Vorlage => Titel MUSS gesetzt sein, template_id = NULL
            if ($title !== '') {
                if ($stFix = $_database->prepare("UPDATE plugins_raidplaner_events SET title = ?, template_id = NULL WHERE id = ?")) {
                    $stFix->bind_param("si", $title, $saved_event_id);
                    $stFix->execute();
                    $stFix->close();
                }
            }
        } else {
            // MIT Vorlage => Titel wird bewusst NULL, template_id = ID
            if ($stFix = $_database->prepare("UPDATE plugins_raidplaner_events SET title = NULL, template_id = ? WHERE id = ?")) {
                $stT = (int)$applied_tpl;
                $stFix->bind_param("ii", $stT, $saved_event_id);
                $stFix->execute();
                $stFix->close();
            }
        }

        // ---------- Bosse übernehmen (nur ohne Vorlage) ----------
        if ($applied_tpl === null) {
            $boss_ids = [];

            // Modal-Picker
            if (!empty($_POST['boss_ids_json'])) {
                $decoded = json_decode($_POST['boss_ids_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $bid) { $bid = (int)$bid; if ($bid > 0) $boss_ids[] = $bid; }
                }
            }

            if (!empty($_POST['boss_ids']) && is_array($_POST['boss_ids'])) {
                foreach ($_POST['boss_ids'] as $bid) { $bid = (int)$bid; if ($bid > 0) $boss_ids[] = $bid; }
            }
            $boss_ids = array_values(array_unique($boss_ids));
            $savedBosses = false;
            if (method_exists($raidManager, 'saveRaidBosses')) {
                try {
                    $rm2 = new ReflectionMethod($raidManager, 'saveRaidBosses');
                    $p   = $rm2->getNumberOfParameters();
                    if ($p >= 2) {
                        $raidManager->saveRaidBosses($saved_event_id, $boss_ids);
                    } else {
                        $raidManager->saveRaidBosses(['event_id' => $saved_event_id, 'boss_ids' => $boss_ids]);
                    }
                    $savedBosses = true;
                } catch (Throwable $e) {
                    $savedBosses = false;
                }
            }
            if (!$savedBosses) {
                $mapTables = [
                    'plugins_raidplaner_event_bosses',
                ];
                $eventCols = ['event_id', 'raid_id', 'raidID', 'eventID'];
                $tableFound = null;
                $eventCol   = null;

                foreach ($mapTables as $tbl) {
                    $chk = $_database->query("SHOW TABLES LIKE '".$tbl."'");
                    if (!$chk || $chk->num_rows === 0) continue;

                    $cols = [];
                    if ($cRes = $_database->query("SHOW COLUMNS FROM {$tbl}")) {
                        while ($c = $cRes->fetch_assoc()) {
                            $cols[strtolower($c['Field'])] = true;
                        }
                    }

                    if (!isset($cols['boss_id'])) continue;

                    foreach ($eventCols as $cand) {
                        if (isset($cols[strtolower($cand)])) {
                            $tableFound = $tbl;
                            $eventCol   = $cand;
                            break 2;
                        }
                    }
                }

                if ($tableFound !== null && $eventCol !== null) {
                    if ($stDel = $_database->prepare("DELETE FROM {$tableFound} WHERE {$eventCol} = ?")) {
                        $stDel->bind_param("i", $saved_event_id);
                        $stDel->execute();
                        $stDel->close();
                    }
                    if (!empty($boss_ids)) {
                        if ($stIns = $_database->prepare("INSERT INTO {$tableFound} ({$eventCol}, boss_id) VALUES (?, ?)")) {
                            foreach ($boss_ids as $bid) {
                                $stIns->bind_param("ii", $saved_event_id, $bid);
                                $stIns->execute();
                            }
                            $stIns->close();
                        }
                    }
                }
            }
        }
        error_log('[raidplaner] DEBUG saved_event_id=' . var_export($saved_event_id, true)
    . ' isUpdate=' . ($isUpdate ? '1' : '0')
    . ' form_event_id=' . var_export($_POST['event_id'] ?? null, true));
        if ((isset($_POST['post_to_discord']) && $_POST['post_to_discord'] === '1') || isset($_POST['save_and_post_raid'])) {
        error_log('[raidplaner] DEBUG entering-discord-block');
        // Settings laden
        $settings_raw = $_database->query("SELECT * FROM plugins_raidplaner_settings")->fetch_all(MYSQLI_ASSOC);
        $settings = array_column($settings_raw, 'setting_value', 'setting_key');

        $webhook = $settings['discord_webhook_url'] ?? '';
        if ($webhook === '') {
            error_log('[raidplaner] Kein discord_webhook_url in $settings vorhanden.');
        } else {
            try {
                // Event laden
                $event = $raidManager->getById((int)$saved_event_id);
                if (!$event) {
                    error_log('[raidplaner] DEBUG event-not-found-for-id=' . (int)$saved_event_id);
                } else {
                    error_log('[raidplaner] DEBUG event-found title=' . ($event['title'] ?? 'NULL'));
                }

                if (!empty($event)) {
                    // -------------------- Template-fähiges Embed mit Admin-Optionen --------------------
                    $toLower = static function(string $s): string {
                        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
                    };
                    $safeArrayFilterNulls = static function(array $arr): array {
                        return array_filter($arr, static function($v) { return $v !== null; });
                    };
                    $renderTemplate = static function($node, array $ctx) use (&$renderTemplate): mixed {
                        if (is_array($node)) {
                            $out = [];
                            foreach ($node as $k => $v) { $out[$k] = $renderTemplate($v, $ctx); }
                            return $out;
                        }
                        if (is_string($node)) {
                            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\-\.]+)\s*\}\}/', function($m) use ($ctx) {
                                $key = $m[1];
                                $parts = explode('.', $key);
                                $val = $ctx;
                                foreach ($parts as $p) {
                                    if (is_array($val) && array_key_exists($p, $val)) {
                                        $val = $val[$p];
                                    } else {
                                        $val = '';
                                        break;
                                    }
                                }
                                if (is_scalar($val)) return (string)$val;
                                return json_encode($val, JSON_UNESCAPED_UNICODE);
                            }, $node);
                        }
                        return $node;
                    };

                    // Basisdaten
                    $title       = trim((string)($event['title'] ?? 'Raid'));
                    $title       = trim((string)($event['title'] ?? 'Raid'));
                    $description = (string)($event['description'] ?? '');
                    $description = str_replace(["\r\n", "\r"], "\n", $description);
                    $description = preg_replace('/\\\\r?\\\\n/', "\n", $description);
                    $description = preg_replace('/<br\s*\/?>/i', "\n", $description);
                    $description = strip_tags($description);
                    $description = preg_replace("/\n{3,}/", "\n\n", trim($description));
                    $whenStr     = (string)($event['event_time'] ?? '');
                    $ts_raw      = ($whenStr !== '' ? strtotime($whenStr) : false);
                    $ts          = ($ts_raw !== false ? $ts_raw : time());
                    $unixTs      = (int)$ts;

                    // Anmeldelink
                    if (!empty($event['public_url'])) {
                        $signupUrl = (string)$event['public_url'];
                    } elseif (!empty($settings['raid_public_base_url'])) {
                        $signupUrl = rtrim((string)$settings['raid_public_base_url'], '/') . '/?site=raidplaner&action=show&id=' . (int)$saved_event_id;
                    } else {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $signupUrl = $scheme . '://' . $host . '/index.php?site=raidplaner&action=show&id=' . (int)$saved_event_id;
                    }

                    // Rollen + Icons
                    $roleIconMap = [];
                    $formatRoleLine = function ($rid, $name, $count) use ($roleIconMap) {
                    $name  = (string)$name;
                    $count = (int)$count;

                    if (isset($roleIconMap[$rid]) && trim($roleIconMap[$rid]) !== '') {
                        $icon = trim($roleIconMap[$rid]);
                    } else {
                        $n = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

                        if (strpos($n, 'tank') !== false) {
                            $icon = '🛡️';
                        } elseif (
                            strpos($n, 'heal') !== false   ||
                            strpos($n, 'heiler') !== false
                        ) {
                            $icon = '✨';
                        } elseif (
                            strpos($n, 'dd') !== false     ||
                            strpos($n, 'dps') !== false    ||
                            strpos($n, 'damage') !== false
                        ) {
                            $icon = '🗡️';
                        } else {
                            $icon = '•';
                        }
                    }
                    return trim($icon . ' **' . $name . ':** ' . $count);
                };
                    if (!empty($settings['discord_role_icons'])) {
                        $tmp = is_string($settings['discord_role_icons']) ? json_decode($settings['discord_role_icons'], true) : $settings['discord_role_icons'];
                        if (is_array($tmp)) $roleIconMap = $tmp;
                    }
                    $roleLines  = [];
                    
                    $templateId = (int)($event['template_id'] ?? 0);
                    try {
                        if ($templateId > 0 && isset($_database) && $_database instanceof mysqli) {
                            $hasSortOrder = false;
                            if ($res = $_database->query("SHOW COLUMNS FROM plugins_raidplaner_roles LIKE 'sort_order'")) {
                                $hasSortOrder = ($res->num_rows > 0); $res->free();
                            }
                            $orderBy = $hasSortOrder ? "r.sort_order, r.role_name" : "r.role_name";
                            $sql = "
                                SELECT r.role_name, ts.needed_count
                                FROM plugins_raidplaner_template_setup ts
                                JOIN plugins_raidplaner_roles r ON r.id = ts.role_id
                                WHERE ts.template_id = ?
                                ORDER BY {$orderBy}
                            ";
                            if ($stmt = $_database->prepare($sql)) {
                                $stmt->bind_param("i", $templateId);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($row = $res->fetch_assoc()) {
                                    $name = (string)$row['role_name'];
                                    $cnt  = (int)$row['needed_count'];
                                    $lower = $toLower($name);
                                    $icon = '•';
                                    foreach ($roleIconMap as $key => $ico) {
                                        if ($key !== '' && strpos($lower, $toLower((string)$key)) !== false) { $icon = (string)$ico; break; }
                                    }
                                    if ($icon === '•') {
                                        if (strpos($lower, 'tank') !== false) { $icon = '🛡️'; }
                                        elseif (strpos($lower, 'heal') !== false) { $icon = '✨'; }
                                        elseif (strpos($lower, 'dps') !== false || strpos($lower, 'damage') !== false) { $icon = '⚔️'; }
                                    }
                                    $roleLines[] = $formatRoleLine((int)($row['role_id'] ?? 0), (string)$row['role_name'], (int)$row['needed_count']);
                                }
                                $stmt->close();
                            }
                        } else {
                        try {
                            $postedSetup = [];
                            if (!empty($_POST['setup']) && is_array($_POST['setup'])) {
                                foreach ($_POST['setup'] as $k => $v) {
                                    $postedSetup[(int)$k] = max(0, (int)$v);
                                }
                            }

                            if (isset($_database) && $_database instanceof mysqli) {
                                $rolesRes = $_database->query("SELECT id, role_name FROM plugins_raidplaner_roles ORDER BY role_name");
                                if ($rolesRes && $rolesRes->num_rows > 0) {
                                    while ($r = $rolesRes->fetch_assoc()) {
                                        $rid   = (int)($r['id'] ?? 0);
                                        $rname = (string)($r['role_name'] ?? 'Role');
                                        $needed = isset($postedSetup[$rid]) ? $postedSetup[$rid] : 0;

                                        $icon = isset($roleIconMap[$rid]) ? ($roleIconMap[$rid] . ' ') : '';
                                        $roleLines[] = $formatRoleLine($rid, $rname, $needed);
                                    }
                                }
                            }
                        } catch (\Throwable $_r) {
                            error_log('[raidplaner] Fehler beim Laden der Rollen für manuellen Raid: ' . $_r->getMessage());
                        }
                    }

                    } catch (\Throwable $dbEx) {
                        error_log('[raidplaner] Rollen konnten nicht geladen werden: ' . $dbEx->getMessage());
                    }
                    $rolesBlock = !empty($roleLines) ? implode(' - ', $roleLines) : '—';

                    // Admin-Optionen (Schalter)
                    $optShowDescription = !empty($settings['discord_show_description']) && $settings['discord_show_description'] === '1';
                    $optShowRoles       = !empty($settings['discord_show_roles']) && $settings['discord_show_roles'] === '1';
                    $optShowTime        = !empty($settings['discord_show_time']) && $settings['discord_show_time'] === '1';
                    $optShowDate        = !empty($settings['discord_show_date']) && $settings['discord_show_date'] === '1';
                    $optShowSignup      = !empty($settings['discord_show_signup']) && $settings['discord_show_signup'] === '1';
                    $optPingOnPost      = !empty($settings['discord_ping_on_post']) && $settings['discord_ping_on_post'] === '1';

                    $titlePrefix  = (string)($settings['discord_title_prefix'] ?? '🗡️ ');
                    $embedColor   = (string)($settings['discord_color_hex'] ?? '#58A6FF');
                    $colorInt     = hexdec(ltrim($embedColor, '#'));
                    $thumbUrl = (string)($settings['discord_thumbnail_uploaded_url'] ?? '');
                    if ($thumbUrl === '') { $thumbUrl = (string)($settings['discord_thumbnail_url'] ?? ''); }
                    if ($thumbUrl === '') { $thumbUrl = 'https://cdn.discordapp.com/embed/avatars/0.png'; }
                    $footerText   = (string)($settings['discord_footer_text'] ?? 'Raidplaner');

                    if ($thumbnailUrl !== '' && !preg_match('#^https?://#i', $thumbnailUrl)) {
                        $errors[] = $languageService->get('error_thumbnail_url');
                    }

                    // Template laden oder Default
                    $template = null;
                    if (!empty($settings['discord_embed_template'])) {
                        $decoded = is_string($settings['discord_embed_template']) ? json_decode($settings['discord_embed_template'], true) : $settings['discord_embed_template'];
                        if (is_array($decoded)) { $template = $decoded; }
                        else { error_log('[raidplaner] WARN discord_embed_template ungültig.'); }
                    }
                    if (!$template) {
                        $template = [
                            "embeds" => [[
                                "title"       => "{{title_prefix}}{{title}}",
                                "url"         => "{{signup_url}}",
                                "color"       => "{{color_int}}",
                                "thumbnail"   => ["url" => "{{thumbnail_url}}"],
                                "description" => "{{description}}",
                                "fields"      => [
                                    ["name" => $languageService->get('discord_message_roles'), "value" => "{{roles_block}}", "inline" => false],
                                    ["name" => $languageService->get('discord_message_date'), "value" => "📅 {{date_D}}",    "inline" => true],
                                    ["name" => $languageService->get('discord_message_time'), "value" => "⏰ {{time_T}}",    "inline" => true],
                                    ["name" => $languageService->get('discord_message_signup'), "value" => "🔗 [Hier anmelden]({{signup_url}})", "inline" => false]
                                ],
                                "timestamp"   => "{{timestamp}}",
                                "footer"      => ["text" => "{{footer_text}}"]
                            ]]
                        ];
                    }

                    // Kontext
                    $ctx = [
                        'title'        => $title,
                        'title_prefix' => $titlePrefix,
                        'description'  => $description,
                        'timestamp'    => gmdate('c', $ts),
                        'unix_ts'      => $unixTs,
                        'date_D'       => "<t:{$unixTs}:D>",
                        'time_T'       => "<t:{$unixTs}:T>",
                        'signup_url'   => $signupUrl,
                        'roles_block'  => $rolesBlock,
                        'thumbnail_url'=> $thumbUrl,
                        'footer_text'  => $footerText,
                        'color_int'    => $colorInt,
                        'event'        => is_array($event) ? $event : [],
                    ];

                    // Rendern
                    $payload = $renderTemplate($template, $ctx);

                    // Felder nach Admin-Schaltern ausfiltern
                    if (isset($payload['embeds'][0]) && is_array($payload['embeds'][0])) {
                        // Beschreibung
                        if (!$optShowDescription) {
                            $payload['embeds'][0]['description'] = null;
                        }
                        if (isset($payload['embeds'][0]['fields']) && is_array($payload['embeds'][0]['fields'])) {
                            $payload['embeds'][0]['fields'] = array_values(array_filter($payload['embeds'][0]['fields'], function($f) use ($optShowRoles, $optShowTime, $optShowDate, $optShowSignup) {
                                $name = isset($f['name']) ? (string)$f['name'] : '';
                                if (stripos($name, 'Rollen') !== false) return $optShowRoles;
                                if (stripos($name, 'Uhrzeit') !== false) return $optShowTime;
                                if (stripos($name, 'Datum') !== false)   return $optShowDate;
                                if (stripos($name, 'Anmeldung') !== false) return $optShowSignup;
                                return true;
                            }));
                        }
                        $payload['embeds'][0] = $safeArrayFilterNulls($payload['embeds'][0]);
                    }

                    // Content (Ping)
                    $content = null;
                    if ($optPingOnPost && !empty($settings['discord_mention_role_id'])) {
                        $content = '<@&' . preg_replace('/\D+/', '', (string)$settings['discord_mention_role_id']) . '>';
                    }

                    // Senden
                    error_log('[raidplaner] DEBUG sending-discord-embed (settings/template based)');
                    $embedsForFn = isset($payload['embeds']) && is_array($payload['embeds']) ? $payload['embeds'] : [];
                    $messageId = send_discord_notification($webhook, $embedsForFn, $content);
                    error_log('[raidplaner] DEBUG send_discord_notification returned messageId=' . var_export($messageId, true));

                    if ($messageId) {
                        $raidManager->updateDiscordMessageId((int)$saved_event_id, (string)$messageId);
                    } else {
                        error_log('[raidplaner] Discord-Post fehlgeschlagen: keine Message-ID erhalten.');
                    }
                } else {
                    error_log('[raidplaner] Event für Discord-Post nicht gefunden: ID ' . $saved_event_id);
                }
            } catch (\Throwable $e) {
                error_log('[raidplaner] Discord-Post Exception: ' . $e->getMessage());
            }
        }
    }
        $shouldPost =
        (isset($_POST['post_to_discord']) && $_POST['post_to_discord'] === '1')
        || isset($_POST['save_and_post_raid']);

        $code = $shouldPost ? 'posted' : 'saved';
        header('Location: admincenter.php?site=admin_raidplaner&tab=raids&msg=' . $code);
        exit;
    }
} elseif (isset($_POST['manual_signup'])) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $user_id  = (int)($_POST['user_id'] ?? 0);
    $char_id  = (int)($_POST['character_id'] ?? 0);
    $role_id  = (int)($_POST['role_id'] ?? 0);

    // Status validieren (Whitelist)
    $allowedStatus = ['Angemeldet', 'Ersatzbank', 'Abgemeldet'];
    $status = $_POST['status'] ?? 'Angemeldet';
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'Angemeldet';
    }

    $msgKey = 'error'; // Default, wird bei Erfolg überschrieben

    if ($user_id > 0 && $char_id > 0 && $role_id > 0 && $event_id > 0) {
        $check_stmt = $_database->prepare(
            "SELECT id FROM plugins_raidplaner_signups WHERE event_id = ? AND user_id = ?"
        );
        if ($check_stmt) {
            $check_stmt->bind_param("ii", $event_id, $user_id);
            if ($check_stmt->execute()) {
                $res = $check_stmt->get_result();
                if ($res && $res->num_rows === 0) {
                    $stmt = $_database->prepare(
                        "INSERT INTO plugins_raidplaner_signups (event_id, user_id, character_id, role_id, status)
                         VALUES (?, ?, ?, ?, ?)"
                    );
                    if ($stmt) {
                        $stmt->bind_param("iiiis", $event_id, $user_id, $char_id, $role_id, $status);
                        if ($stmt->execute()) {
                            $msgKey = 'saved';
                        } else {
                            if ((int)$_database->errno === 1062) {
                                $msgKey = 'exists';
                            } else {
                                $msgKey = 'error';
                            }
                        }
                        $stmt->close();
                    } else {
                        $msgKey = 'error';
                    }
                } else {
                    $msgKey = 'exists';
                }
                if ($res) { $res->free(); }
            } else {
                $msgKey = 'error';
            }
            $check_stmt->close();
        } else {
            $msgKey = 'error';
        }
    } else {
        $msgKey = 'error';
    }

    rp_redirect_with_msg(
        'admincenter.php?site=admin_raidplaner&view=manage_attendance&event_id='.$event_id,
        $msgKey
    );

} elseif (isset($_POST['save_template'])) {
    if ($view !== 'edit_template') {
        $templateManager->save($_POST);
        header("Location: admincenter.php?site=admin_raidplaner&tab=templates");
        exit;
    }
} elseif (isset($_POST['save_item'])) {
    $itemManager = new ItemManager($_database);
    $ok = $itemManager->saveItem($_POST);
    if ($ok) {
        header('Location: ?site=admin_raidplaner&tab=items&saved=1');
        exit;
    }
} elseif (isset($_POST['save_boss'])) {
    $ok = $bossManager->save($_POST);
    $code = $ok ? 'saved' : 'error';
    header('Location: admincenter.php?site=admin_raidplaner&tab=bosses&msg=' . $code);
    exit;
} elseif (isset($_POST['add_class'])) {
    $class_name = trim((string)($_POST['class_name'] ?? ''));

    if ($class_name !== '') {
        if ($stmt = $_database->prepare("INSERT INTO plugins_raidplaner_classes (class_name) VALUES (?)")) {
            $stmt->bind_param("s", $class_name);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: admincenter.php?site=admin_raidplaner&tab=settings");
    exit;
} elseif (isset($_POST['add_role'])) {
    $role_name = trim($_POST['role_name'] ?? '');
    if ($role_name !== '') {
        if ($stmt = $_database->prepare("INSERT INTO plugins_raidplaner_roles (role_name) VALUES (?)")) {
            $stmt->bind_param("s", $role_name);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: admincenter.php?site=admin_raidplaner&tab=settings");
    exit;
} elseif (isset($_POST['save_roles'])) {
    $posted_roles = $_POST['roles'] ?? [];
    if (is_array($posted_roles) && !empty($posted_roles)) {
        if ($upd = $_database->prepare("UPDATE plugins_raidplaner_roles SET role_name = ?, icon = ? WHERE id = ?")) {
            foreach ($posted_roles as $rid => $payload) {
                $id   = (int)$rid;
                $name = trim((string)($payload['name'] ?? ''));
                $icon = trim((string)($payload['icon'] ?? ''));

                if ($name === '') { continue; }
                if ($icon !== '' && !preg_match('/^bi-[a-z0-9-]+$/i', $icon)) {
                    $icon = '';
                }

                $upd->bind_param("ssi", $name, $icon, $id);
                $upd->execute();
            }
            $upd->close();
        }
    }
    header("Location: admincenter.php?site=admin_raidplaner&tab=settings&msg=saved");
    exit;
} elseif (isset($_POST['save_attendance'])) {
    $event_id = intval($_POST['event_id'] ?? 0);
    
    $signup_chars = $_POST['signup_char'] ?? [];
    $signup_roles = $_POST['signup_role'] ?? [];

    foreach ($signup_chars as $user_id => $char_id) {
        $user_id = intval($user_id);
        $char_id = intval($char_id);
        $role_id = intval($signup_roles[$user_id] ?? 0);

        if ($event_id > 0 && $user_id > 0 && $char_id > 0 && $role_id > 0) {
            $stmt = $_database->prepare("UPDATE plugins_raidplaner_signups SET character_id = ?, role_id = ? WHERE event_id = ? AND user_id = ?");
            $stmt->bind_param("iiii", $char_id, $role_id, $event_id, $user_id);
            $stmt->execute();
        }
    }

    if (!empty($_POST['attendance']) && $event_id > 0) {
        foreach ($_POST['attendance'] as $user_id => $status) {
            $user_id = intval($user_id);
            $char_id = intval($signup_chars[$user_id] ?? 0);

            if ($user_id > 0 && $char_id > 0) {
                $check = $_database->prepare("SELECT 1 FROM plugins_raidplaner_attendance WHERE event_id = ? AND user_id = ?");
                $check->bind_param("ii", $event_id, $user_id);
                $check->execute();
                $exists = $check->get_result()->fetch_row();

                if ($exists) {
                    $stmt = $_database->prepare("UPDATE plugins_raidplaner_attendance SET status = ?, character_id = ? WHERE event_id = ? AND user_id = ?");
                    $stmt->bind_param("siii", $status, $char_id, $event_id, $user_id);
                    $stmt->execute();
                } else {
                    $stmt = $_database->prepare("INSERT INTO plugins_raidplaner_attendance (event_id, character_id, user_id, status) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiis", $event_id, $char_id, $user_id, $status);
                    $stmt->execute();
                }
            }
        }
    }

    header("Location: admincenter.php?site=admin_raidplaner&view=manage_attendance&event_id=" . $event_id . "&msg=updated");
    exit;
    
    } elseif (isset($_POST['save_discord_settings']) || isset($_POST['send_discord_test']) || isset($_POST['send_discord_preview'])) {

    // Eingaben lesen
    $webhookUrl    = trim((string)($_POST['discord_webhook_url'] ?? ''));
    $mentionRoleId = trim((string)($_POST['discord_mention_role_id'] ?? ''));

    // Schalter/Felder (Toggles)
    $showDescription = isset($_POST['discord_show_description']) ? '1' : '0';
    $showRoles       = isset($_POST['discord_show_roles'])       ? '1' : '0';
    $showTime        = isset($_POST['discord_show_time'])        ? '1' : '0';
    $showDate        = isset($_POST['discord_show_date'])        ? '1' : '0';
    $showSignup      = isset($_POST['discord_show_signup'])      ? '1' : '0';
    $pingOnPost      = isset($_POST['discord_ping_on_post'])     ? '1' : '0';

    $titlePrefix   = (string)($_POST['discord_title_prefix']   ?? '');
    $colorHex      = trim((string)($_POST['discord_color_hex'] ?? ''));
    $thumbnailUrl  = trim((string)($_POST['discord_thumbnail_url'] ?? ''));
    $footerText    = (string)($_POST['discord_footer_text'] ?? '');

    // Validierung
    $errors = [];

    // ------------------------------------------------------------
    // Thumbnail: Entfernen + Upload + URL-Fallback  (Plugin/img)
    // ------------------------------------------------------------

    // Delete-Flag aus Formular
    $deleteRequested = isset($_POST['discord_thumbnail_delete']) && $_POST['discord_thumbnail_delete'] === '1';
    $uploadedThumbUrl = (string)($settings['discord_thumbnail_uploaded_url'] ?? '');

    // Basis-URL
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host;

    // Upload-Ziele (Dateisystem)
    $uploadDirRel = '/../includes/plugins/raidplaner/img/';
    $uploadDirAbs = __DIR__ . $uploadDirRel;

    if ($deleteRequested) {
        $existingUrl = $settings['discord_thumbnail_uploaded_url'] ?? '';
        if ($existingUrl !== '') {
            $parsed = parse_url($existingUrl);
            if (!empty($parsed['path'])) {
                $docroot    = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
                $absFromUrl = $docroot ? realpath($docroot . $parsed['path']) : false;
                $uploadsAbs = realpath($uploadDirAbs);
                if ($docroot && $absFromUrl && $uploadsAbs && str_starts_with($absFromUrl, $uploadsAbs) && is_file($absFromUrl)) {
                    @unlink($absFromUrl);
                }
            }
        }
        $uploadedThumbUrl = '';
    }

    if (
        !$deleteRequested &&
        !empty($_FILES['discord_thumbnail_file']['tmp_name']) &&
        is_uploaded_file($_FILES['discord_thumbnail_file']['tmp_name'])
    ) {
        if (!empty($_FILES['discord_thumbnail_file']['name']) && ($_FILES['discord_thumbnail_file']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $err = (int)$_FILES['discord_thumbnail_file']['error'];
            $map = [
                UPLOAD_ERR_INI_SIZE   => 'Die Datei ist größer als upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE  => 'Die Datei überschreitet MAX_FILE_SIZE.',
                UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise hochgeladen.',
                UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei hochgeladen.',
                UPLOAD_ERR_NO_TMP_DIR => 'Kein temporärer Ordner auf dem Server.',
                UPLOAD_ERR_CANT_WRITE => 'Konnte Datei nicht speichern (Rechte?).',
                UPLOAD_ERR_EXTENSION  => 'Upload durch Erweiterung gestoppt.',
            ];
            $_SESSION['flash_error'] = ($languageService->get('error_upload_failed') ?? 'Upload fehlgeschlagen.') . ' [' . ($map[$err] ?? ('Code '.$err)) . ']';
        }

        if (!is_dir($uploadDirAbs)) {
            @mkdir($uploadDirAbs, 0775, true);
        }

        $fileTmp  = $_FILES['discord_thumbnail_file']['tmp_name'];
        $fileName = basename($_FILES['discord_thumbnail_file']['name']);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed  = ['png', 'jpg', 'jpeg', 'gif'];

        if (in_array($ext, $allowed, true)) {
            $salt    = bin2hex(random_bytes(4));
            $newName = 'thumb_' . date('Ymd_His') . '_' . substr(md5($fileName . $salt), 0, 8) . '.' . $ext;
            $destAbs = $uploadDirAbs . $newName;

            if (move_uploaded_file($fileTmp, $destAbs)) {

                $docroot  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
                $destReal = realpath($destAbs);
                $webPath  = '';
                if ($docroot && $destReal && str_starts_with($destReal, $docroot)) {
                    $webPath = str_replace('\\', '/', substr($destReal, strlen($docroot)));
                    if ($webPath === '' || $webPath[0] !== '/') { $webPath = '/' . ltrim($webPath, '/'); }
                } else {
                    $basePath = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
                    $webPath  = $basePath . '/../includes/plugins/raidplaner/img/' . $newName;
                }
                $uploadedThumbUrl = $baseUrl . $webPath;
            } else {
                $_SESSION['flash_error'] = ($languageService->get('error_upload_failed') ?? 'Der Upload des Thumbnails ist fehlgeschlagen.');
            }
        } else {
            $_SESSION['flash_error'] = ($languageService->get('error_invalid_thumbnail_ext') ?? 'Ungültiges Dateiformat für Thumbnail (nur PNG, JPG, GIF erlaubt).');
        }
    }

    if ($thumbnailUrl !== '' && !preg_match('#^https?://#i', $thumbnailUrl)) {
        $errors[] = $languageService->get('error_thumbnail_url') ?? 'Ungültige Thumbnail-URL.';
    }

    if (isset($_POST['save_discord_settings']) || isset($_POST['send_discord_test']) || isset($_POST['send_discord_preview'])) {
        if ($webhookUrl !== '' && !preg_match('#^https?://discord\.com/api/webhooks/#i', $webhookUrl)) {
            $errors[] = $languageService->get('error_not_a_discord_webhook') ?? 'Bitte eine gültige Discord Webhook-URL angeben.';
        }
    }

    $cleanRoleId = preg_replace('/\D+/', '', $mentionRoleId);
    if ($mentionRoleId !== '' && $cleanRoleId === '') {
        $errors[] = $languageService->get('error_invalid_role_id') ?? 'Ungültige Rollen-ID.';
    }

    if ($colorHex !== '') {
        $hex = ltrim($colorHex, '#');
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            $colorHex = '#' . strtoupper($hex);
        } else {
            $errors[] = $languageService->get('error_color_hex') ?? 'Ungültiger Farbwert. Erlaubt ist #RRGGBB';
        }
    }

    if ($thumbnailUrl !== '' && !preg_match('#^https?://#i', $thumbnailUrl)) {
        $errors[] = $languageService->get('error_thumbnail_url') ?? 'Ungültige Thumbnail-URL.';
    }

    // 1) Speichern
    if (isset($_POST['save_discord_settings']) && empty($errors)) {
        $settings_to_save = [
            'discord_webhook_url'      => $webhookUrl,
            'discord_mention_role_id'  => $mentionRoleId,
            'discord_show_description' => $showDescription,
            'discord_show_roles'       => $showRoles,
            'discord_show_time'        => $showTime,
            'discord_show_date'        => $showDate,
            'discord_show_signup'      => $showSignup,
            'discord_ping_on_post'     => $pingOnPost,
            'discord_title_prefix'     => $titlePrefix,
            'discord_color_hex'        => $colorHex,
            'discord_thumbnail_url'    => $thumbnailUrl,
            'discord_thumbnail_uploaded_url' => $uploadedThumbUrl,  
            'discord_footer_text'      => $footerText,
        ];

        $stmt = $_database->prepare(
            "INSERT INTO plugins_raidplaner_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($settings_to_save as $key => $value) {
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
        $_SESSION['flash_success'] = $languageService->get('settings_saved_success') ?? 'Einstellungen gespeichert.';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'example.com';
    $base   = $scheme . '://' . $host;
    $signupUrlDemo = $base . '/index.php?site=raidplaner';

    if (isset($_POST['send_discord_test'])) {
        if (empty($errors)) {
            $content = $languageService->get('discord_testmessage_content') ?? '✅ Testnachricht vom Raidplaner erfolgreich gesendet!';
            if ($cleanRoleId !== '' && $pingOnPost === '1') {
                $content = "<@&{$cleanRoleId}> " . $content;
            }

            $payload = [
                'content' => $content,
                'username' => 'Raidplaner',
                'allowed_mentions' => [
                    'parse' => [],
                    'roles' => ($cleanRoleId !== '') ? [$cleanRoleId] : [],
                    'users' => [],
                    'replied_user' => false,
                ],
            ];

            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $_SESSION['flash_error'] = $languageService->get('discord_testmessage_error_curl') . ' ' . htmlspecialchars($curlErr);
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                $_SESSION['flash_error'] = $languageService->get('discord_testmessage_error_http') . " (HTTP {$httpCode})";
            } else {
                $_SESSION['flash_success'] = $languageService->get('discord_testmessage_success');
            }
        } else {
            $_SESSION['flash_error'] = implode('<br>', array_map('htmlspecialchars', $errors));
        }
    }

if (isset($_POST['send_discord_preview'])) {
    if (empty($errors)) {

        // Demo-Daten für Vorschau (nur Darstellung)
        $now   = new DateTime('now', new DateTimeZone('UTC'));
        $start = clone $now;
        $start->modify('+2 days')->setTime(20, 0);
        $unix  = $start->getTimestamp();
        $dateDiscord = "<t:{$unix}:D>";
        $timeDiscord = "<t:{$unix}:T>";

        $title = trim(($titlePrefix !== '' ? $titlePrefix : '') . ($languageService->get('discord_preview_title')));
        if ($showDescription === '1') {
            $desc = trim($languageService->get('discord_preview_desc') ?? '');
            $desc = str_replace(['\\r\\n', '\\n', "\r\n"], "\n", $desc);
            if ($desc === '' || $desc === '[discord_preview_desc]') {
                $desc = '';
            }
        } else {
            $desc = '';
        }

        $previewThumb = (string)($settings['discord_thumbnail_uploaded_url'] ?? '');
        if ($previewThumb === '') {
            $previewThumb = $thumbnailUrl; 
        }

        // === Embed-Grundstruktur ===
        $embed = [
            'title'       => $title,
            'type'        => 'rich',
            'url'         => $signupUrlDemo,
            'description' => $desc,
            'fields'      => [],
        ];

        // Farbe (aus Settings, sonst Default)
        $embedColor = 0x58A6FF;
        if ($colorHex !== '') {
            $embedColor = hexdec(ltrim($colorHex, '#'));
        }
        $embed['color'] = $embedColor;

        // Thumbnail
        if ($previewThumb !== '') {
            $embed['thumbnail'] = ['url' => $previewThumb];
        }

        // Footer
        $embed['footer'] = ['text' => ($footerText !== '' ? $footerText : 'Raidplaner')];

        // 1) Rollen
        if ($showRoles === '1') {
            // Icons aus Einstellungen (falls vorhanden)
            $roleIconMap = [];
            if (!empty($settings['discord_role_icons'])) {
                $tmp = is_string($settings['discord_role_icons']) ? json_decode($settings['discord_role_icons'], true) : $settings['discord_role_icons'];
                if (is_array($tmp)) $roleIconMap = $tmp;
            }

            $formatRoleLine = function ($rid, $name, $count) use ($roleIconMap) {
                $icon = isset($roleIconMap[$rid]) && trim($roleIconMap[$rid]) !== '' ? trim($roleIconMap[$rid]) : (
                    stripos($name, 'tank') !== false ? '🛡️' :
                    (stripos($name, 'heal') !== false || stripos($name, 'heiler') !== false ? '✨' :
                    (stripos($name, 'dd') !== false || stripos($name, 'dps') !== false ? '🗡️' : '•'))
                );
                return trim("{$icon} **{$name}:** " . (int)$count);
            };

            // Vorschauwerte (nur Darstellung)
            $rolesBlock = implode(' - ', [
                $formatRoleLine(1, 'DD',     8),
                $formatRoleLine(2, 'Healer', 8),
                $formatRoleLine(3, 'Tank',   8),
            ]);

            $embed['fields'][] = [
                'name'   => $languageService->get('label_roles'),
                'value'  => $rolesBlock,
                'inline' => false
            ];
        }

        // 2) Datum – ICON im VALUE
        if ($showDate === '1') {
            $embed['fields'][] = [
                'name'   => $languageService->get('label_date'),
                'value'  => '📅 ' . $dateDiscord,
                'inline' => true
            ];
        }

        // 3) Uhrzeit – ICON im VALUE
        if ($showTime === '1') {
            $embed['fields'][] = [
                'name'   => $languageService->get('label_time'),
                'value'  => '⏰ ' . $timeDiscord,
                'inline' => true
            ];
        }

        // 4) Anmeldung – ICON im VALUE + Linktext „Hier anmelden“
        if ($showSignup === '1') {
            $embed['fields'][] = [
                'name'   => $languageService->get('label_signup'),
                'value'  => '🔗 [' . $languageService->get('label_signup_link') . '](' . $signupUrlDemo . ')',
                'inline' => false
            ];
        }

        // === Payload & Versand ===
        $content = '';
        if ($cleanRoleId !== '' && $pingOnPost === '1') {
            $content = "<@&{$cleanRoleId}>";
        }

        $payload = [
            'username' => 'Raidplaner',
            'content'  => $content,
            'embeds'   => [$embed],
            'allowed_mentions' => [
                'parse'        => [],
                'roles'        => ($cleanRoleId !== '') ? [$cleanRoleId] : [],
                'users'        => [],
                'replied_user' => false,
            ],
        ];

        // Senden
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $result   = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $_SESSION['flash_error'] = $languageService->get('discord_preview_error_curl') . ' ' . htmlspecialchars($curlErr);
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            $_SESSION['flash_error'] = $languageService->get('discord_preview_error_http') . " (HTTP {$httpCode})";
        } else {
            $_SESSION['flash_success'] = $languageService->get('discord_preview_success');
        }

    } else {
        $_SESSION['flash_error'] = implode('<br>', array_map('htmlspecialchars', $errors));
    }
}

    header("Location: admincenter.php?site=admin_raidplaner&view=discord");
    exit;

    } elseif (isset($_POST['save_general_settings'])) {      
        $manage_roles_to_save = isset($_POST['manage_default_roles']) ? '1' : '0';
        $stmt = $_database->prepare("
            INSERT INTO plugins_raidplaner_settings (setting_key, setting_value) 
            VALUES ('manage_default_roles', ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param("s", $manage_roles_to_save);
        $stmt->execute();

        // Standardrollen anlegen, nur wenn Checkbox aktiviert
        if ($manage_roles_to_save === '1') {
            $default_roles = [
                1 => 'Healer',
                2 => 'DD',
                3 => 'Tank'
            ];

            foreach ($default_roles as $id => $role_name) {
                // Prüfen, ob die Rolle schon existiert (ID oder Name)
                $stmt_check = $_database->prepare("SELECT id FROM plugins_raidplaner_roles WHERE id = ? OR role_name = ?");
                $stmt_check->bind_param("is", $id, $role_name);
                $stmt_check->execute();
                $stmt_check->store_result();
                // Standard-Icons für die drei Default-Rollen setzen (falls leer)
                $_database->query("UPDATE plugins_raidplaner_roles SET icon='bi-heart-fill'  WHERE role_name='Healer'  AND (icon IS NULL OR icon='')");
                $_database->query("UPDATE plugins_raidplaner_roles SET icon='bi-crosshair'   WHERE role_name='DD'      AND (icon IS NULL OR icon='')");
                $_database->query("UPDATE plugins_raidplaner_roles SET icon='bi-shield-fill' WHERE role_name='Tank'    AND (icon IS NULL OR icon='')");
                if ($stmt_check->num_rows === 0) {
                    // Rolle anlegen mit fester ID
                    $stmt_insert = $_database->prepare("INSERT INTO plugins_raidplaner_roles (id, role_name) VALUES (?, ?)");
                    $stmt_insert->bind_param("is", $id, $role_name);
                    $stmt_insert->execute();
                } else {
                    $stmt_update = $_database->prepare("UPDATE plugins_raidplaner_roles SET id = ? WHERE role_name = ?");
                    $stmt_update->bind_param("is", $id, $role_name);
                    $stmt_update->execute();
                }
            }
        }

            header("Location: admincenter.php?site=admin_raidplaner&tab=settings");
            exit;
        }
    }

// =============================================================================
// SEITEN-LAYOUT
// =============================================================================
?>
<div class="container-fluid">
    <!-- TOP BAR -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><i class="bi bi-shield-shaded text-primary" style="font-size: 1.2em;"></i> <?= $languageService->get('admin_title') ?></h3>
            <div class="d-flex align-items-center">
                <a href="?site=admin_raidplaner&view=discord" class="btn btn-discord">
                    <i class="bi bi-discord"></i> <?= $languageService->get('discord_integration_title') ?>
                </a>
            </div>
        </div>
    </div>

    <?php rp_qs_alert() ?>

    <!-- MAIN CONTENT AREA -->
    <div class="modal fade" id="unifiedBossModal" tabindex="-1" aria-hidden="true" aria-labelledby="unifiedBossLabel">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
            <div class="modal-header">
                <h5 id="unifiedBossLabel" class="modal-title"><?= $languageService->get('modal_boss_select_title') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label"><?= $languageService->get('form_search') ?></label>
                    <input type="search" class="form-control" id="ubpSearch" placeholder="Bossname…">
                </div>
                </div>
                <hr class="my-3">
                <div id="ubpList" class="list-group" style="max-height:50vh; overflow:auto" role="listbox" aria-label="Bossliste"></div>
                <div id="ubpLoading" class="text-center py-3 d-none"><div class="spinner-border" role="status"></div></div>
                <div id="ubpEmpty" class="text-muted py-3 d-none"><?= $languageService->get('msg_no_results') ?></div>
            </div>
            <div class="modal-footer">
                <div class="me-auto text-muted"><strong id="ubpCount">0</strong> <?= $languageService->get('selected_suffix') ?></div>
                <button type="button" class="btn btn-danger" id="ubpClear"><?= $languageService->get('btn_clear_all') ?></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $languageService->get('btn_cancel') ?></button>
                <button type="button" class="btn btn-primary" id="ubpApply"><?= $languageService->get('btn_apply') ?></button>
            </div>
            </div>
        </div>
    </div>

    <script>
        // ---- 6) Einheitlicher Boss-Picker (ein Modal, viele Aufrufer)
        (function initUnifiedBossPicker(){
            const ubp = {
            modal: document.querySelector('#unifiedBossModal'),
            search: document.querySelector('#ubpSearch'),
            list: document.querySelector('#ubpList'),
            loading: document.querySelector('#ubpLoading'),
            empty: document.querySelector('#ubpEmpty'),
            btnApply: document.querySelector('#ubpApply'),
            btnClear: document.querySelector('#ubpClear'),
            count: document.querySelector('#ubpCount'),
            targetHidden: null,
            targetChips: null,
            selected: new Set(),
            page: 1, hasMore: true, isLoading:false, query:'', reqSeq:0, io:null
        };
        if (!ubp.modal || !ubp.list) { window._ubp = null; return; }

        function setCount(){ if (ubp.count) ubp.count.textContent = ubp.selected.size; }
        function push(){
            if (!ubp.targetHidden) return;
            ubp.targetHidden.value = JSON.stringify(Array.from(ubp.selected));
            setCount();
        }
            function renderChips(byId){
            if (!ubp.targetChips) return;
            ubp.targetChips.innerHTML = '';
            Array.from(ubp.selected).forEach(id=>{
                const name = (byId && byId[id]) ? byId[id] : ('#'+id);
                const span = document.createElement('span');
                span.className = 'badge rounded-pill bg-secondary d-inline-flex align-items-center';
                span.setAttribute('data-id', id);
                span.innerHTML =
                '<span class="me-1">'+(window.esc?window.esc(name):String(name))+'</span>' +
                '<button type="button" class="btn btn-sm btn-light ms-1 chip-remove" aria-label="Entfernen">&times;</button>';
                ubp.targetChips.appendChild(span);
            });
        }
        function reflectChecks(){
            ubp.list.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
            const id = parseInt(cb.value,10);
            cb.checked = ubp.selected.has(id);
            });
        }
        function row(item){
            const el = document.createElement('label');
            el.className = 'list-group-item d-flex justify-content-between align-items-center';
            el.innerHTML = '<div><input class="form-check-input me-2" type="checkbox" value="'+ String(item.id) +'"><strong>' + (window.esc?window.esc(item.boss_name):String(item.boss_name)) +'</strong></div>';
            const cb = el.querySelector('input');
            cb.checked = ubp.selected.has(parseInt(item.id,10));
            cb.onchange = function(){
            const id = parseInt(item.id,10);
            if (cb.checked) ubp.selected.add(id); else ubp.selected.delete(id);
            setCount();
            };
            return el;
        }
        function load(reset){
            if (ubp.isLoading && !reset) return;
            const my = ++ubp.reqSeq;
            if (reset){ ubp.page=1; ubp.hasMore=true; ubp.list.innerHTML=''; }
            if (!ubp.hasMore) return;

            ubp.isLoading = true;
            ubp.loading && ubp.loading.classList.remove('d-none');
            ubp.empty && ubp.empty.classList.add('d-none');

            const fd = new FormData();
            fd.append('ajax','list_bosses');
            fd.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>');
            fd.append('page', ubp.page);
            fd.append('limit', 50);
            if (ubp.query) fd.append('query', ubp.query);

            fetch('?site=admin_raidplaner', { method:'POST', credentials:'same-origin', body: fd })
            .then(r => r.json())
            .then(data => {
                const arr = (data && Array.isArray(data.items)) ? data.items : [];
                arr.forEach(it => ubp.list.appendChild(row(it)));
                if (arr.length===0 && ubp.page===1 && ubp.empty) ubp.empty.classList.remove('d-none');
                ubp.hasMore = !!(data && data.has_more);
                ubp.page++;
                reflectChecks();
            })
            .catch(()=>{})
            .finally(()=>{
                if (my === ubp.reqSeq){
                ubp.isLoading=false;
                ubp.loading && ubp.loading.classList.add('d-none');
                }
            });
        }
        function bindInfiniteOnce(){
            if (ubp.io) return;
            const sentinel = document.createElement('div');
            sentinel.className='py-3';
            ubp.list.parentNode.insertBefore(sentinel, ubp.list.nextSibling);
            ubp.io = new IntersectionObserver(entries => {
            if (entries.some(e=>e.isIntersecting)) load(false);
            });
            ubp.io.observe(sentinel);
        }

        // Öffnen-Buttons – data-Targets setzen
        document.body.addEventListener('click', function(e){
            const btn = e.target.closest('.boss-picker-open');
            if (!btn) return;

            ubp.targetHidden = document.querySelector(btn.dataset.hidden || '');
            ubp.targetChips  = document.querySelector(btn.dataset.chips  || '');
            if (!ubp.targetHidden || !ubp.targetChips) return;

            ubp.selected = new Set();
            try {
            const init = JSON.parse(ubp.targetHidden.value || '[]');
            if (Array.isArray(init)) init.forEach(id=>{ id=parseInt(id,10); if(id>0) ubp.selected.add(id); });
            } catch(e){}

            setCount();
            ubp.list.innerHTML = '';
            ubp.query=''; ubp.page=1; ubp.hasMore=true; ubp.reqSeq=0;

            if (ubp.search){
            ubp.search.value = '';
            ubp.search.oninput = (function(fn,ms){ let t; return (ev)=>{ clearTimeout(t); t=setTimeout(()=>fn(ev),ms); }; })(function(ev){
                ubp.query = (ev.target.value || '').trim();
                load(true);
            }, 300);
            }

            if (ubp.btnClear){
            ubp.btnClear.onclick = function(){
                ubp.selected.clear();
                push(); renderChips({}); reflectChecks();
            };
            }
            if (ubp.btnApply){
            ubp.btnApply.onclick = function(){
                push();
                const ids = Array.from(ubp.selected);
                if (ids.length){
                const fd = new FormData();
                fd.append('ajax','bosses_by_ids');
                fd.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>');
                fd.append('ids', ids.join(','));
                fetch('?site=admin_raidplaner', { method:'POST', credentials:'same-origin', body: fd })
                    .then(r=>r.json())
                    .then(d=>{
                    const byId = {};
                    (d.items || []).forEach(it => { byId[it.id] = it.boss_name; });
                    renderChips(byId);
                    push();
                    })
                    .finally(()=> bootstrap.Modal.getOrCreateInstance(ubp.modal).hide());
                } else {
                renderChips({});
                push();
                bootstrap.Modal.getOrCreateInstance(ubp.modal).hide();
                }
            };
            }

            bindInfiniteOnce();
            bootstrap.Modal.getOrCreateInstance(ubp.modal).show();
            load(true);
        });
        window._ubp = ubp;
        })();

        /* Globales Chip-Entfernen (funktioniert ohne geöffnetes Modal)
        Unterstützt beide Bereiche:
        - Vorlage:  #bossChips        + Hidden #boss_ids_json
        - Raid:     #raidBossChips    + Hidden #raid_boss_ids_json
        */
        (function bindGlobalChipRemove(){
        function updateHiddenFromContainer(containerEl, hiddenEl){
            if (!containerEl || !hiddenEl) return;
            const ids = Array.from(containerEl.querySelectorAll('[data-id]'))
            .map(el => parseInt(el.getAttribute('data-id'),10))
            .filter(n => n>0);
            hiddenEl.value = JSON.stringify(ids);

            if (window._ubp && window._ubp.targetHidden === hiddenEl) {
            window._ubp.selected = new Set(ids);
            if (window._ubp.count) window._ubp.count.textContent = window._ubp.selected.size;
            }
        }

        document.addEventListener('click', function(e){
            const rmBtn = e.target.closest('.chip-remove');
            if (!rmBtn) return;

            const container = rmBtn.closest('#bossChips, #raidBossChips');
            if (!container) return;

            const chip   = rmBtn.closest('[data-id]');
            const hidden = (container.id === 'bossChips')
            ? document.getElementById('boss_ids_json')
            : document.getElementById('raid_boss_ids_json');

            if (chip) chip.remove();
            updateHiddenFromContainer(container, hidden);
        });
        })();
        </script>

    <div class="content-area">
        <?php
            if ($view === 'discord') {
                $settings_raw = $_database->query("SELECT * FROM plugins_raidplaner_settings")->fetch_all(MYSQLI_ASSOC);
                $settings = array_column($settings_raw, 'setting_value', 'setting_key');
                ?>
                    <h3><?= $languageService->get('discord_integration_title') ?></h3>
                        <div class="row mt-3">
                            <!-- Linke Box -->
                            <div class="col-lg-7 mb-3">
                                <div class="card shadow-sm" style="min-height: 400px;">
                                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-discord"></i> <?= $languageService->get('discord_integration_title') ?></h5></div>
                                    <div class="card-body">
                                        <form method="post" action="?site=admin_raidplaner" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <div class="alert alert-info mt-3" role="alert">
                                                <i class="bi bi-info-circle-fill"></i> <?= $languageService->get('discord_info_text') ?>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label"><?= $languageService->get('form_webhook_url') ?></label>
                                                <input type="text" class="form-control" name="discord_webhook_url" value="<?= htmlspecialchars($settings['discord_webhook_url'] ?? '') ?>" placeholder="https://discord.com/api/webhooks/...">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="mention_role"><?= $languageService->get('form_mention_role_id') ?></label>
                                                <input type="text" id="mention_role" class="form-control" name="discord_mention_role_id" value="<?= htmlspecialchars($settings['discord_mention_role_id'] ?? '') ?>">
                                            </div>
                                            <!-- Discord: Nachricht-Optionen (toggles) START -->
                                            <hr class="my-4">

                                            <h5 class="mb-3"><?= $languageService->get('discord_message_settings_title') ?></h5>

                                            <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_show_description" name="discord_show_description" value="1"
                                                        <?= (!empty($settings['discord_show_description']) && $settings['discord_show_description'] === '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="opt_show_description">
                                                    <?= $languageService->get('discord_opt_show_description') ?>
                                                </label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_show_roles" name="discord_show_roles" value="1"
                                                        <?= (!empty($settings['discord_show_roles']) && $settings['discord_show_roles'] === '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="opt_show_roles">
                                                    <?= $languageService->get('discord_opt_show_roles') ?>
                                                </label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_show_time" name="discord_show_time" value="1"
                                                        <?= (!empty($settings['discord_show_time']) && $settings['discord_show_time'] === '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="opt_show_time">
                                                    <?= $languageService->get('discord_opt_show_time') ?>
                                                </label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_show_date" name="discord_show_date" value="1"
                                                        <?= (!empty($settings['discord_show_date']) && $settings['discord_show_date'] === '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="opt_show_date">
                                                    <?= $languageService->get('discord_opt_show_date') ?>
                                                </label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_show_signup" name="discord_show_signup" value="1"
                                                        <?= (!empty($settings['discord_show_signup']) && $settings['discord_show_signup'] === '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="opt_show_signup">
                                                    <?= $languageService->get('discord_opt_show_signup') ?>
                                                </label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt_ping_on_post" name="discord_ping_on_post" value="1"
                                                        <?= (!empty($settings['discord_ping_on_post']) && $settings['discord_ping_on_post'] === '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="opt_ping_on_post">
                                                    <?= $languageService->get('discord_opt_ping_on_post') ?>
                                                </label>
                                                </div>
                                            </div>
                                            </div>

                                            <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label" for="inp_title_prefix"><?= $languageService->get('discord_title_prefix') ?? 'Titel-Präfix' ?></label>
                                                <input type="text" class="form-control" id="inp_title_prefix" name="discord_title_prefix"
                                                    value="<?= htmlspecialchars($settings['discord_title_prefix'] ?? '🗡️ ') ?>">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label" for="inp_color_hex"><?= $languageService->get('discord_color_hex') ?? 'Farbe (Hex)' ?></label>
                                                <input type="color" class="form-control" id="inp_color_hex" name="discord_color_hex" placeholder="#58A6FF"
                                                    value="<?= htmlspecialchars($settings['discord_color_hex'] ?? '#58A6FF') ?>">
                                                <div class="form-text"><?= $languageService->get('discord_color_help') ?? 'Format: #RRGGBB' ?></div>
                                            </div>

                                            <?php
                                            $currentThumbUpload   = (string)($settings['discord_thumbnail_uploaded_url'] ?? '');
                                            $currentThumbExternal = (string)($settings['discord_thumbnail_url'] ?? '');

                                            // Input-Wert: Wenn Upload da ist ⇒ Feld leer (Placeholder). Sonst externe URL anzeigen (falls vorhanden).
                                            $hasUpload = ($currentThumbUpload !== '');
                                            $valueUrl  = $hasUpload ? '' : $currentThumbExternal;

                                            // Vorschau: zuerst Upload, dann externe URL
                                            $currentThumb = $hasUpload ? $currentThumbUpload : $currentThumbExternal;
                                            ?>
                                            <!-- Thumbnail: URL ODER Upload + Vorschau + Entfernen -->
                                            <div class="mb-3">
                                                <label class="form-label" for="inp_thumb">
                                                    <?= $languageService->get('discord_thumbnail_url') ?>
                                                </label>

                                                <div class="row align-items-start g-3">
                                                    <!-- Linke Spalte: Datei-Upload + URL -->
                                                    <div class="col-lg-8 col-md-7 col-12">
                                                        <div class="mb-2">
                                                            <input type="file"
                                                                class="form-control"
                                                                id="inp_thumb_file"
                                                                name="discord_thumbnail_file"
                                                                accept="image/png,image/jpeg,image/gif">
                                                            <div class="form-text">
                                                                <?= $languageService->get('discord_thumbnail_upload_hint') ?>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <input type="url"
                                                                class="form-control"
                                                                id="inp_thumb"
                                                                name="discord_thumbnail_url"
                                                                placeholder="https://example.com/image.png"
                                                                value="<?= !empty($valueUrl) ? htmlspecialchars($valueUrl) : '' ?>">
                                                            <div class="form-text">
                                                                <?= $languageService->get('discord_thumbnail_hint') ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Rechte Spalte: Vorschau (und Entfernen-Button nur wenn Bild existiert) -->
                                                    <div class="col-lg-4 col-md-5 col-12">
                                                        <div class="form-text mb-1">
                                                            <?= $languageService->get('discord_thumbnail_preview') ?>
                                                        </div>

                                                        <div id="thumb_preview_wrap"
                                                            style="width: 140px; height: 140px; border: 1px solid rgba(0,0,0,.1); display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8f9fa;">
                                                            <?php if (!empty($currentThumb)): ?>
                                                                <img id="thumb_preview_img"
                                                                    src="<?= htmlspecialchars($currentThumb) ?>"
                                                                    alt="thumbnail"
                                                                    style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                            <?php else: ?>
                                                                <img id="thumb_preview_img"
                                                                    src=""
                                                                    alt="thumbnail"
                                                                    style="max-width: 100%; max-height: 100%; object-fit: contain; display:none;">
                                                                <span id="thumb_preview_placeholder" class="text-muted" style="font-size: 12px;">
                                                                    <?= $languageService->get('discord_thumbnail_preview_empty') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if (!empty($currentThumb)): ?>
                                                            <div class="mt-2">
                                                                <input type="hidden" name="discord_thumbnail_delete" id="inp_thumb_delete" value="0">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-danger"
                                                                    id="btn_thumb_remove"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deleteModal"
                                                                    data-type="thumb"
                                                                    data-id="thumb">
                                                                    <?= $languageService->get('discord_thumbnail_remove_btn') ?>
                                                                </button>
                                                                <div class="form-text">
                                                                    <?= $languageService->get('discord_thumbnail_remove_hint') ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- /Thumbnail -->

                                            <div class="col-md-12">
                                                <label class="form-label" for="inp_footer"><?= $languageService->get('discord_footer_text') ?></label>
                                                <input type="text" class="form-control" id="inp_footer" name="discord_footer_text"
                                                    value="<?= htmlspecialchars($settings['discord_footer_text'] ?? 'Raidplaner') ?>">
                                            </div>
                                        </div>
                                            <!-- Discord: Nachricht-Optionen (toggles) END -->
                                             <div class="mt-3">
                                                <button type="submit" name="save_discord_settings" class="btn btn-primary"><?= $languageService->get('btn_save_settings') ?></button> 
                                                <span class="d-inline-block"
                                                    tabindex="0"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    data-bs-title="<?= htmlspecialchars($languageService->get('btn_send_test_title')) ?>">
                                                <button type="submit"
                                                        name="send_discord_test"
                                                        value="1"
                                                        class="btn btn-discord"
                                                        id="btn-discord-test"
                                                        <?= empty($settings['discord_webhook_url']) ? 'disabled' : '' ?>>
                                                    <i class="bi bi-discord"></i> <?= $languageService->get('btn_send_test') ?>
                                                </button>
                                                </span>

                                                <span class="d-inline-block"
                                                    tabindex="0"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    data-bs-title="<?= htmlspecialchars($languageService->get('btn_send_preview_title')) ?>">
                                                <button type="submit"
                                                        name="send_discord_preview"
                                                        value="1"
                                                        class="btn btn-discord"
                                                        id="btn-discord-preview"
                                                        <?= empty($settings['discord_webhook_url']) ? 'disabled' : '' ?>>
                                                    <i class="bi bi-discord"></i> <?= $languageService->get('btn_send_preview') ?>
                                                </button>
                                                </span>
                                            </div>

                                            <script>
                                            document.addEventListener('DOMContentLoaded', () => {
                                            const urlInput = document.getElementById('discord_webhook_url');
                                            const testBtn  = document.getElementById('btn-discord-test');
                                            const toggle = () => {
                                                const v = urlInput.value.trim();
                                                testBtn.disabled = !/^https?:\/\/discord\.com\/api\/webhooks\//.test(v);
                                            };
                                            urlInput.addEventListener('input', toggle);
                                            toggle();
                                            });
                                            </script>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Rechte Box (Accordion) -->
                            <div class="col-lg-5 mb-3">
                                <div class="accordion" style="margin: 30px 0 30px 0;" id="discordHelpAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                                <i class="bi bi-question-circle-fill me-2"></i> <?= $languageService->get('discord_guide_title') ?>
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#discordHelpAccordion">
                                            <div class="accordion-body">
                                                <h5><?= $languageService->get('guide_webhook_title') ?></h5>
                                                <ol>
                                                    <li><strong><?= $languageService->get('guide_desktop') ?>:</strong>
                                                        <ol>
                                                            <li><?= $languageService->get('guide_webhook_step1') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step2') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step3') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step4') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step5') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step6') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step7') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step8') ?></li>
                                                            <li><?= $languageService->get('guide_webhook_step9') ?></li>
                                                        </ol>
                                                    </li>
                                                </ol>
                                                
                                                <h5><?= $languageService->get('guide_role_id_title') ?></h5>
                                                <ol>
                                                    <li><strong><?= $languageService->get('guide_desktop') ?>:</strong>
                                                        <ol>
                                                            <li><?= $languageService->get('guide_role_step1') ?></li>
                                                            <li><?= $languageService->get('guide_role_step2') ?></li>
                                                            <li><?= $languageService->get('guide_role_step3') ?></li>
                                                            <li><?= $languageService->get('guide_role_step4') ?></li>
                                                            <li><?= $languageService->get('guide_role_step5') ?></li>
                                                            <li><?= $languageService->get('guide_role_step6') ?></li>
                                                            <li><?= $languageService->get('guide_role_step7') ?></li>
                                                        </ol>
                                                    </li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
    </div>
    <script>
        (function () {
        const urlInp   = document.getElementById('inp_thumb');
        const fileInp  = document.getElementById('inp_thumb_file');
        const prevImg  = document.getElementById('thumb_preview_img');
        const prevPH   = document.getElementById('thumb_preview_placeholder');
        const delInp   = document.getElementById('inp_thumb_delete');
        const delBtn   = document.getElementById('btn_thumb_remove');

        function showPreview(src) {
            if (!prevImg) return;
            if (src) {
            prevImg.src = src;
            prevImg.style.display = '';
            if (prevPH) prevPH.style.display = 'none';
            } else {
            prevImg.src = '';
            prevImg.style.display = 'none';
            if (prevPH) prevPH.style.display = '';
            }
        }

        // URL-Feld -> Vorschau
        if (urlInp) {
            urlInp.addEventListener('input', function () {
            const v = (urlInp.value || '').trim();
            if (v.match(/^https?:\/\/.+/i)) {
                showPreview(v);
                if (delInp) delInp.value = '0';
            } else if (v === '') {
                if (!fileInp || !fileInp.files || !fileInp.files[0]) {
                showPreview('');
                }
            }
            });
        }
        if (fileInp) {
            fileInp.addEventListener('change', function () {
            if (fileInp.files && fileInp.files[0]) {
                const file = fileInp.files[0];
                const ok = /image\/(png|jpeg|gif)/i.test(file.type);
                if (!ok) {
                alert('<?= addslashes($languageService->get('error_thumbnail_upload_type') ?? 'Ungültiges Dateiformat für Thumbnail (nur PNG, JPG, GIF erlaubt).') ?>');
                fileInp.value = '';
                return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                showPreview(e.target.result);
                if (urlInp && !urlInp.value) {
                }
                if (delInp) delInp.value = '0';
                };
                reader.readAsDataURL(file);
            } else {
                if (!urlInp || !urlInp.value) showPreview('');
            }
            });
        }
        if (delBtn) {
            delBtn.setAttribute('data-bs-toggle', 'modal');
            delBtn.setAttribute('data-bs-target', '#deleteModal');
            delBtn.setAttribute('data-type', 'thumb');
            delBtn.setAttribute('data-id', 'thumb');
        }
        })();
        </script>
    <?php
    }
elseif ($view === 'edit_raid') {
    $raidManager = new RaidManager($_database, $settings);

    $event_id = 0;
    if (isset($_GET['event_id'])) { $event_id = (int)$_GET['event_id']; }
    elseif (isset($_POST['event_id'])) { $event_id = (int)$_POST['event_id']; }
    elseif (isset($_GET['id'])) { $event_id = (int)$_GET['id']; }
    elseif (isset($_POST['id'])) { $event_id = (int)$_POST['id']; }

    $raid = [
        'title'            => '',
        'description'      => '',
        'event_time'       => date('Y-m-d\TH:i'),
        'duration_minutes' => 180,
        'template_id'      => 0
    ];
    $setup_map        = [];
    $raid_description = '';

    // 3) Bestehenden Raid laden (falls vorhanden)
    if ($event_id > 0) {
        $raid_data = $raidManager->getById($event_id);
        if ($raid_data) {
            $raid = $raid_data;
            if (!empty($raid['event_time'])) {
                $raid['event_time'] = date('Y-m-d\TH:i', strtotime($raid['event_time']));
            } else {
                $raid['event_time'] = date('Y-m-d\TH:i');
            }
        }
        // Rollen-Setup für Raid
        if (method_exists($raidManager, 'getSetupForRaid')) {
            $setup_map = $raidManager->getSetupForRaid($event_id);
        } else {
            $setup_map = [];
        }
    }

    $raid_description = $raid['description'] ?? '';

    // 4) Settings (Discord)
    $settings_raw = $_database->query("SELECT setting_key, setting_value FROM plugins_raidplaner_settings")->fetch_all(MYSQLI_ASSOC);
    $discord_settings = array_column($settings_raw, 'setting_value', 'setting_key');
    $webhook_url_exists = !empty($discord_settings['discord_webhook_url']);

    // 5) Rollen & Vorlagen
    $roles     = $_database->query("SELECT id, role_name FROM plugins_raidplaner_roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
    $templates = $_database->query("SELECT id, template_name FROM plugins_raidplaner_templates ORDER BY template_name")->fetch_all(MYSQLI_ASSOC);

    // 6) Bosse (gesamt) + Bosse aus Vorlage
    $allBosses = $_database->query("SELECT id, boss_name FROM plugins_raidplaner_bosses ORDER BY boss_name")->fetch_all(MYSQLI_ASSOC);

    $templateBossIds = [];
    if (!empty($raid['template_id'])) {
        $tm = new TemplateManager($_database);
        $templateBossIds = $tm->getBossesForTemplate((int)$raid['template_id']); // Array<int>
    }
    $hasTpl = ((int)($raid['template_id'] ?? 0) > 0);

    // 6.1) Bosse eines bestehenden Raids (ohne Vorlage) für die Anzeige ermitteln
    $raidBossIds = [];
    if (!$hasTpl) {
        if (!empty($_POST['boss_ids_json'])) {
            $tmp = json_decode($_POST['boss_ids_json'], true);
            if (is_array($tmp)) {
                foreach ($tmp as $bid) { $bid = (int)$bid; if ($bid > 0) $raidBossIds[] = $bid; }
            }
        } elseif (!empty($_POST['boss_ids']) && is_array($_POST['boss_ids'])) {
            foreach ($_POST['boss_ids'] as $bid) { $bid = (int)$bid; if ($bid > 0) $raidBossIds[] = $bid; }
        }
        if (empty($raidBossIds) && $event_id > 0) {
            if (method_exists($raidManager, 'getBossesForRaid')) {
                $raidBossIds = $raidManager->getBossesForRaid($event_id);
                if (!is_array($raidBossIds)) $raidBossIds = [];
            }

            if (empty($raidBossIds)) {
                $mapTables = [
                    'plugins_raidplaner_event_bosses',
                ];
                $eventCols = ['event_id', 'raid_id', 'raidID', 'eventID'];

                foreach ($mapTables as $tbl) {
                    $chk = $_database->query("SHOW TABLES LIKE '".$tbl."'");
                    if (!$chk || $chk->num_rows === 0) continue;

                    $cols = [];
                    if ($cRes = $_database->query("SHOW COLUMNS FROM {$tbl}")) {
                        while ($c = $cRes->fetch_assoc()) {
                            $cols[strtolower($c['Field'])] = true;
                        }
                    }
                    if (!isset($cols['boss_id'])) continue;

                    $useEventCol = null;
                    foreach ($eventCols as $cand) { if (isset($cols[strtolower($cand)])) { $useEventCol = $cand; break; } }
                    if ($useEventCol === null) continue;

                    $stmtRB = $_database->prepare("SELECT boss_id FROM {$tbl} WHERE {$useEventCol} = ?");
                    if ($stmtRB) {
                        $stmtRB->bind_param("i", $event_id);
                        if ($stmtRB->execute()) {
                            $resRB = $stmtRB->get_result();
                            while ($row = $resRB->fetch_assoc()) {
                                $raidBossIds[] = (int)$row['boss_id'];
                            }
                        }
                        $stmtRB->close();
                    }

                    if (!empty($raidBossIds)) break;
                }
            }
        }

        $raidBossIds = array_values(array_unique(array_map('intval', $raidBossIds)));
    }

    // 7) Rendering
    echo '<h3><a href="?site=admin_raidplaner&tab=raids" class="text-decoration-none">' . $languageService->get('breadcrumb_raids') . '</a> &raquo; ' . ($event_id > 0 ? $languageService->get('title_edit_raid') : $languageService->get('title_new_raid')) . '</h3>';

    echo '<div class="card mt-3"><div class="card-body">';
    $hasTemplate = !empty($raid['template_id']);
    $isEdit      = ($event_id > 0);
    $showTplBox  = !($isEdit && !$hasTemplate);

    if ($showTplBox) {
        echo '<div class="mb-3">
                <label for="load-template" class="form-label">' . $languageService->get('form_load_from_template') . '</label>
                <select id="load-template" class="form-select">
                    <option value="0">' . $languageService->get('form_select_template') . '</option>';
                    foreach ($templates as $template) {
                        $sel = ((int)$template['id'] === (int)($raid['template_id'] ?? 0)) ? ' selected' : '';
                        echo '<option value="'.$template['id'].'"' . $sel . '>' . htmlspecialchars($template['template_name'] ?? '', ENT_QUOTES, 'UTF-8').'</option>';
                    }
        echo   '</select>
            </div>
            <hr>';
    }

    $appliedTplValue = ((int)($raid['template_id'] ?? 0) > 0) ? (int)$raid['template_id'] : '';
    echo '<form method="post" action="?site=admin_raidplaner&view=edit_raid&event_id='.$event_id.'">
            <input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8').'">
            <input type="hidden" name="event_id" value="'.$event_id.'">
            <input type="hidden" name="applied_template_id" id="applied_template_id" value="'.htmlspecialchars($appliedTplValue, ENT_QUOTES, 'UTF-8').'">
            <input type="hidden" name="post_to_discord" value="0">';

    echo '<div class="mb-3">
        <label class="form-label">' . $languageService->get('form_title') . '</label>';
    echo '  <input type="text" class="form-control" id="raid-title" name="title"'
        . ' value="' . htmlspecialchars($raid['title'] ?? '', ENT_QUOTES, 'UTF-8') . '"'
        . ($hasTpl ? ' disabled' : ' required') . '>';

    if ($hasTpl) {
        echo '  <div class="form-text">' . $languageService->get('hint_title_from_template') . '</div>';
        echo '  <input type="hidden" id="raid-title-hidden" name="title"'
            . ' value="' . htmlspecialchars($raid['title'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    }

    echo '</div>';

    if ($hasTpl) {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function(){
            var vis = document.getElementById("raid-title");
            var hid = document.getElementById("raid-title-hidden");
            if (vis && hid) {
                var sync = function(){ hid.value = vis.value || ""; };
                vis.addEventListener("input", sync);
                sync();
            }
        });
        </script>';
    }

    // Zeit/Dauer
    echo    '<div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">' . $languageService->get('form_datetime') . '</label>
                    <input type="datetime-local" class="form-control" name="event_time"
                           value="'.htmlspecialchars($raid['event_time'] ?? date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8').'" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">' . $languageService->get('form_duration_minutes') . '</label>
                    <input type="number" class="form-control" name="duration"
                           value="'.(int)($raid['duration_minutes'] ?? 180).'" required>
                </div>
            </div>';

    // Beschreibung
    echo    '<div class="mb-3">
                <label class="form-label">' . $languageService->get('form_description') . '</label>
                <textarea class="form-control" name="description" rows="14">'.
                    htmlspecialchars(
                        str_replace(["\\r\\n","\\n","\\r","\\'"], ["\n","\n","\n","'"], $raid_description),
                        ENT_QUOTES,
                        'UTF-8'
                    )
                .'</textarea>
            </div>';

    // Rollen
    echo    '<h5>' . $languageService->get('title_raid_setup') . '</h5>
            <div class="row">';
                foreach ($roles as $role) {
                    echo '<div class="col-md-3 mb-3">
                            <label class="form-label">'.htmlspecialchars($role['role_name'] ?? '', ENT_QUOTES, 'UTF-8').'</label>
                            <input type="number" class="form-control" name="setup['.(int)$role['id'].']"
                                   value="'.(int)($setup_map[$role['id']] ?? 0).'" min="0">
                          </div>';
                }
    echo    '</div>';

// -----------------------------
// Bosse (RAID) – ohne Vorlage: Modal-Picker / mit Vorlage: readonly
// -----------------------------
$bossMap = [];
foreach ($allBosses as $b) {
    $bossMap[(int)$b['id']] = stripslashes((string)($b['boss_name'] ?? ''));
}

echo '<hr>';
echo '<h5>'
   . $languageService->get('section_bosses')
   . ' '
   . ($hasTpl
        ? $languageService->get('suffix_from_template')
        : $languageService->get('suffix_manual_selection'))
   . '</h5>';

if ($hasTpl) {
    echo '<div class="mb-3">';
    echo '  <label class="form-label d-block">'. $languageService->get('label_bosses') . '</label>';
    echo '  <div class="d-flex flex-wrap gap-2 p-3 border rounded bg-white">';
    if (!empty($templateBossIds)) {
        foreach ($templateBossIds as $bid) {
            $name = htmlspecialchars($bossMap[(int)$bid] ?? ('#'.$bid), ENT_QUOTES, 'UTF-8');
            echo '    <span class="badge rounded-pill bg-light text-dark border px-3 py-2">'.$name.'</span>';
        }
    } else {
        echo '    <span class="text-muted">('.$languageService->get('msg_no_template_bosses').')</span>';
    }
    echo '  </div>';
    echo '  <div class="form-text mt-2">'. $languageService->get('msg_bosses_from_template') .'</div>';
    echo '</div>';

} else {
    // Modal-Picker (gemeinsames Modal)
    $raidBossIds = array_values(array_unique(array_map('intval', $raidBossIds ?? [])));

    echo '<div class="mb-3">
            <div class="d-flex gap-2 align-items-start mb-4">
                <button type="button"
                        class="btn btn-secondary boss-picker-open"
                        id="openRaidBossPicker"
                        data-hidden="#raid_boss_ids_json"
                        data-chips="#raidBossChips"
                        data-count="#ubpCount">
                    Bosse auswählen
                </button>
                <div id="raidBossChips" class="d-flex flex-wrap gap-2">';

                // vorhandene Chips serverseitig anzeigen
                if (!empty($raidBossIds)) {
                    foreach ($raidBossIds as $bid) {
                        $nm = htmlspecialchars($bossMap[(int)$bid] ?? ('#'.$bid), ENT_QUOTES, 'UTF-8');
                        echo '  <span class="badge rounded-pill bg-secondary d-inline-flex align-items-center" data-id="'.(int)$bid.'">
                                    <span class="me-1">'.$nm.'</span>
                                    <button type="button" class="btn btn-sm btn-light ms-1 chip-remove" aria-label="Entfernen">&times;</button>
                                </span>';
                    }
                }
    echo '      </div>
            </div>

            <input type="hidden"
                   name="boss_ids_json"
                   id="raid_boss_ids_json"
                   value="' . htmlspecialchars(json_encode($raidBossIds, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '">

            <script>
            // Lokaler Listener nur für edit_raid: Chips per X entfernen + Hidden synchronisieren
            document.addEventListener("DOMContentLoaded", function(){
                var box = document.getElementById("raidBossChips");
                var hid = document.getElementById("raid_boss_ids_json");
                if (!box || !hid) return;

                box.addEventListener("click", function(e){
                    var btn = e.target.closest(".chip-remove");
                    if (!btn) return;
                    var chip = btn.closest("[data-id]");
                    if (!chip) return;

                    // Chip entfernen
                    chip.remove();

                    // IDs aus Container neu einsammeln und Hidden setzen
                    var ids = Array.prototype.slice.call(box.querySelectorAll("[data-id]"))
                        .map(function(el){ return parseInt(el.getAttribute("data-id"), 10); })
                        .filter(function(n){ return n > 0; });

                    hid.value = JSON.stringify(ids);

                    // Falls Unified Boss Picker offen und auf dasselbe Hidden zeigt -> sync
                    if (window._ubp && window._ubp.targetHidden === hid) {
                        window._ubp.selected = new Set(ids);
                        if (window._ubp.count) window._ubp.count.textContent = ids.length;
                    }
                });
            });
            </script>';
}

// Buttons
echo '<button type="submit" name="save_raid" class="btn btn-primary">' . $languageService->get('btn_save') . '</button>';
if ($webhook_url_exists) {
    echo ' <button type="submit" name="save_and_post_raid" value="1" class="btn btn-discord" onclick="this.form.post_to_discord.value=1; return true;">' . $languageService->get('btn_save_and_post_discord') .'</button>';
}
echo ' <a href="?site=admin_raidplaner&tab=raids" class="btn btn-secondary">' . $languageService->get('btn_cancel') . '</a>';

echo '</form>';

echo '</div></div>';

}

// ======================================================================
// VIEW: edit_template  (Anlegen + Bearbeiten von Raid-Vorlagen)
// ======================================================================
if ($view === 'edit_template') {
    $templateManager = new TemplateManager($_database);

    // -----------------------------
    // 1) Variablen & Defaults
    // -----------------------------
    $template_id = 0;
    if (isset($_GET['template_id'])) {
        $template_id = (int)$_GET['template_id'];
    } elseif (isset($_GET['id'])) {
        $template_id = (int)$_GET['id'];
    } elseif (isset($_POST['template_id'])) {
        $template_id = (int)$_POST['template_id'];
    }
    if ($template_id < 0) { $template_id = 0; }

    $template_id = isset($id) ? (int)$id : 0;
    if ($template_id <= 0) {
        $template_id = (int)($_REQUEST['template_id'] ?? 0);
    }

    $template = [
        'id'               => $template_id,
        'template_name'    => '',
        'description'      => '',
        'duration_minutes' => 180,
    ];
    $setup_map      = [];
    $templateBosses = []; 

    // -----------------------------
    // 2) Speichern (POST)
    // -----------------------------
    if (isset($_POST['save_template'])) {
        $wasNew = ($template_id <= 0);

        // CSRF prüfen
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }

        // 1) Vorlage speichern
        $saved_template_id = (int)$templateManager->save($_POST);
        if ($saved_template_id <= 0) {
            echo '<div class="alert alert-danger">Vorlage konnte nicht gespeichert werden.</div>';
        } else {
            $template_id = $saved_template_id;

            // 2) BOSSE speichern (Modal-Auswahl)
            $boss_ids = [];
            if (!empty($_POST['boss_ids_json'])) {
                $decoded = json_decode($_POST['boss_ids_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $bid) {
                        $bid = (int)$bid;
                        if ($bid > 0) $boss_ids[] = $bid;
                    }
                }
            } elseif (!empty($_POST['boss_ids']) && is_array($_POST['boss_ids'])) {
                foreach ($_POST['boss_ids'] as $bid) {
                    $bid = (int)$bid;
                    if ($bid > 0) $boss_ids[] = $bid;
                }
            }
            $boss_ids = array_values(array_unique($boss_ids));
            if (method_exists($templateManager, 'saveTemplateBosses')) {
                $templateManager->saveTemplateBosses($template_id, $boss_ids);
            }

            // 3) ROLLEN-SETUP speichern
            $setupPairs = [];
            if (!empty($_POST['setup']) && is_array($_POST['setup'])) {
                foreach ($_POST['setup'] as $rid => $amt) {
                    $rid = (int)$rid;
                    $amt = (int)$amt;
                    if ($rid > 0 && $amt >= 0) {
                        $setupPairs[$rid] = $amt;
                    }
                }
            }
            $tblChk = $_database->query("SHOW TABLES LIKE 'plugins_raidplaner_template_setup'");
            if ($tblChk && $tblChk->num_rows > 0) {
                if ($del = $_database->prepare("DELETE FROM plugins_raidplaner_template_setup WHERE template_id=?")) {
                    $del->bind_param("i", $template_id);
                    $del->execute();
                    $del->close();
                }

                if (!empty($setupPairs)) {
                    $values = [];
                    $params = [];
                    $types  = '';
                    foreach ($setupPairs as $rid => $cnt) {
                        $values[] = "(?, ?, ?)";
                        $types   .= "iii";
                        $params[] = $template_id;
                        $params[] = $rid;
                        $params[] = $cnt;
                    }
                    $sqlIns = "INSERT INTO plugins_raidplaner_template_setup (template_id, role_id, needed_count) VALUES " . implode(',', $values);
                    if ($ins = $_database->prepare($sqlIns)) {
                        $ins->bind_param($types, ...$params);
                        $ins->execute();
                        $ins->close();
                    }
                }
            }

            // 4) Redirect:
            //     - Neu angelegt  -> zurück zur Übersicht (Tab Templates)
            //     - Bearbeitet    -> auf der Edit-Seite bleiben
            if ($wasNew) {
                header('Location: admincenter.php?site=admin_raidplaner&tab=templates&msg=saved');
            } else {
                header('Location: admincenter.php?site=admin_raidplaner&view=edit_template&template_id=' . $template_id . '&msg=saved');
            }
            exit;
        }
    }
    // -----------------------------
    // 3) Daten für das Formular laden (Edit/Neu)
    // -----------------------------
    $template       = isset($template) && is_array($template) ? $template : [];
    $setup_map      = [];   // role_id => amount/needed_count
    $templateBosses = [];   // [boss_id, ...]

    // Template-ID
    if (!isset($template_id)) {
        $template_id = 0;
        if (isset($_GET['template_id']))      $template_id = (int)$_GET['template_id'];
        elseif (isset($_GET['id']))           $template_id = (int)$_GET['id'];
        elseif (isset($_POST['template_id'])) $template_id = (int)$_POST['template_id'];
    }

    if ($template_id > 0) {
        // 3.1 Stammdaten
        $loaded = $templateManager->getById($template_id);
        if (is_array($loaded) && !empty($loaded)) {
            $template = array_merge($template, $loaded);
        }

        // 3.2 Rollen-Setup
        $setup_map = $templateManager->getSetupForTemplate($template_id);

        // 3.3 Bosse der Vorlage
        if (method_exists($templateManager, 'getBossesForTemplate')) {
            $templateBosses = $templateManager->getBossesForTemplate($template_id);
        }
    }

    // 3.4 Falls POST eine Bossauswahl trägt (z. B. nach Validierungsfehler), anzeigen
    if (!empty($_POST['boss_ids_json'])) {
        $tmp = json_decode($_POST['boss_ids_json'], true);
        if (is_array($tmp)) $templateBosses = array_map('intval', $tmp);
    } elseif (!empty($_POST['boss_ids']) && is_array($_POST['boss_ids'])) {
        $templateBosses = array_map('intval', $_POST['boss_ids']);
    }

    // 3.5 Falls POST bereits Setup-Werte enthält (z. B. nach Validierungsfehler), diese bevorzugen
    if (!empty($_POST['setup']) && is_array($_POST['setup'])) {
        foreach ($_POST['setup'] as $rid => $amt) {
            $rid = (int)$rid; $amt = (int)$amt;
            if ($rid > 0) $setup_map[$rid] = $amt;
        }
    }

    // 3.6 Rollenliste
    $roles = [];
    $hasSortOrder = $_database->query("SHOW COLUMNS FROM plugins_raidplaner_roles LIKE 'sort_order'");
    $orderBy = ($hasSortOrder && $hasSortOrder->num_rows > 0) ? "sort_order, role_name" : "role_name";
    if ($resRoles = $_database->query("SELECT id, role_name FROM plugins_raidplaner_roles ORDER BY {$orderBy}")) {
        while ($r = $resRoles->fetch_assoc()) {
            $roles[] = ['id' => (int)$r['id'], 'role_name' => (string)$r['role_name']];
        }
    }
    // -----------------------------
    // 4) Rendering
    // -----------------------------
    echo '<h3><a href="?site=admin_raidplaner&tab=templates" class="text-decoration-none">' . $languageService->get('breadcrumb_templates') . '</a> &raquo; ' . ($template_id > 0 ? $languageService->get('title_edit_template') : $languageService->get('title_new_template')) . '</h3>';
    echo '<div class="card mt-3"><div class="card-body">
          <form method="post" action="?site=admin_raidplaner&view=edit_template&template_id='.$template_id.'">
                <input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8').'">
                <input type="hidden" name="view" value="edit_template">
                <input type="hidden" name="template_id" value="'.$template_id.'">

                <!-- Kein separates Titel-Feld mehr: Titel = Vorlagenname (Hidden für evtl. Legacy-Code) -->
                <input type="hidden" name="title" id="template-title-hidden" value="'.htmlspecialchars($template['template_name'] ?? '', ENT_QUOTES, 'UTF-8').'">

                <div class="mb-3">
                    <label class="form-label">'.$languageService->get('form_template_name').'</label>
                    <input type="text" class="form-control" id="template-name" name="template_name" value="'.htmlspecialchars($template['template_name'] ?? '', ENT_QUOTES, 'UTF-8').'" required>
                    <div class="form-text">'.$languageService->get('hint_title_from_template').'</div>
                </div>
                <hr>

                <div class="mb-3">
                    <label class="form-label">'.$languageService->get('form_duration_minutes').'</label>
                    <input type="number" class="form-control" name="duration" value="'.(int)($template['duration_minutes'] ?? 180).'" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">'.$languageService->get('form_description').'</label>
                    <textarea class="form-control" name="description" rows="14">'.
                        htmlspecialchars(str_replace(["\\r\\n","\\n","\\r","\\'"], ["\n","\n","\n","'"], $template['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    .'</textarea>
                </div>

                <h5>'.$languageService->get('title_raid_setup').'</h5>
                <div class="row">';
                foreach ($roles as $role) {
                    $rid   = (int)($role['id'] ?? 0);
                    $rname = htmlspecialchars($role['role_name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $val   = (int)($setup_map[$rid] ?? 0);

                    echo '<div class="col-md-3 mb-3">
                            <label class="form-label">' . $rname . '</label>
                            <input type="number"
                                class="form-control"
                                name="setup[' . $rid . ']"
                                value="' . $val . '"
                                min="0">
                        </div>';
                }
    $hasTpl = !empty($templateBosses);

// -----------------------------
// Bosse-Heading + Container (TEMPLATE)
// -----------------------------
echo   '</div>
        <hr>
        <h5>'
        . ($languageService->get('section_bosses') ?? 'Bosse')
        . ' '
        . ($hasTpl
                ? ($languageService->get('suffix_from_template') ?? '(aus Vorlage)')
                : ($languageService->get('suffix_manual_selection') ?? '(manuelle Auswahl)'))
        . '</h5>
        <div class="mb-3">
          <label class="form-label">' . $languageService->get('label_bosses') . '</label>';

// Serverseitige Vorbelegung (sichtbare Chips)
$selectedBossIds = array_values(array_unique(array_map('intval', $templateBosses)));
$selectedBossMap = [];

if (!empty($selectedBossIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedBossIds), '?'));
    $types = str_repeat('i', count($selectedBossIds));
    $sql = "SELECT id, boss_name 
            FROM plugins_raidplaner_bosses 
            WHERE id IN ($placeholders) 
            ORDER BY boss_name, id";
    if ($stmt = $_database->prepare($sql)) {
        $stmt->bind_param($types, ...$selectedBossIds);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $selectedBossMap[(int)$row['id']] = stripslashes((string)$row['boss_name']);
            }
        }
        $stmt->close();
    }
}

echo '
  <div class="d-flex gap-2 align-items-start mb-2">
    <button type="button"
            class="btn btn-secondary boss-picker-open"
            data-modal="#unifiedBossModal"
            data-hidden="#boss_ids_json"
            data-chips="#bossChips"
            data-count="#ubpCount">' . $languageService->get('modal_boss_select_title') . '</button>
    <div id="bossChips" class="d-flex flex-wrap gap-2">';

// Serverseitig Chips zeigen (falls vorhanden)
if (!empty($selectedBossIds)) {
    foreach ($selectedBossIds as $bid) {
        $rawName = $selectedBossMap[$bid] ?? ('#'.$bid);
        $name    = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        echo '<span class="badge rounded-pill bg-secondary d-inline-flex align-items-center" data-id="'.$bid.'">
                <span class="me-1">'.$name.'</span>
                <button type="button" class="btn btn-sm btn-light ms-1 chip-remove" aria-label="Entfernen">&times;</button>
              </span>';
    }
}

echo   '</div>
  </div>';

$hiddenJson = htmlspecialchars(json_encode($selectedBossIds, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

echo '
  <input type="hidden" name="boss_ids_json" id="boss_ids_json" value="' . $hiddenJson . '">
  <div class="form-text">Aus vorhandenen Bossen auswählen. Die aktuelle Auswahl ist oben als Chips sichtbar.</div>

  <!-- Modal -->
  <div class="modal fade" id="bossPickerModal" tabindex="-1" aria-hidden="true" aria-labelledby="bossPickerLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 id="bossPickerLabel" class="modal-title">' . $languageService->get('modal_boss_select_title') . '</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-6">
              <label class="form-label">' . $languageService->get('form_search') . '</label>
              <input type="search" class="form-control" id="bossSearch" placeholder="Bossname…">
            </div>
          </div>
          <hr class="my-3">
          <div id="bossList" class="list-group" style="max-height:50vh; overflow:auto" role="listbox" aria-label="Bossliste"></div>
          <div id="bossLoading" class="text-center py-3 d-none"><div class="spinner-border" role="status"></div></div>
          <div id="bossEmpty" class="text-muted py-3 d-none">' . $languageService->get('msg_no_results') .'</div>
        </div>
        <div class="modal-footer">
          <div class="me-auto text-muted">
            <strong id="selectedCount">0</strong> ' . $languageService->get('selected_suffix') . '
          </div>
          <button type="button" class="btn btn-link" id="clearSelection">' . $languageService->get('btn_clear_all') . '</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('btn_cancel') . '</button>
          <button type="button" class="btn btn-primary" id="applyBossSelection">' . $languageService->get('btn_apply') . '</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Aktions-/Buttonbereich (immer sichtbar) -->
<div class="mt-4 pt-3 gap-2">
    <button type="submit" name="save_template" value="1" class="btn btn-primary">' . $languageService->get('btn_save') . '</button>
    <a href="admincenter.php?site=admin_raidplaner&tab=templates" class="btn btn-secondary">' . $languageService->get('btn_cancel') . '</a>
</div>

</form>
</div></div>
';

    // JS: spiegelt den Vorlagennamen in das versteckte "title"-Feld (Titel = Vorlagenname)
    echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
        var nameInput   = document.getElementById("template-name");
        var titleHidden = document.getElementById("template-title-hidden");
        if (nameInput && titleHidden) {
            var sync = function(){ titleHidden.value = nameInput.value || ""; };
            nameInput.addEventListener("input", sync);
            sync();
        }
    });
    </script>';

}
elseif ($view === 'edit_item') {
    $item = ['item_name' => '', 'slot' => '', 'source' => '', 'boss_id' => 0];
    if ($id > 0) {
        $item_stmt = $_database->prepare("SELECT * FROM plugins_raidplaner_items WHERE id = ?");
        $item_stmt->bind_param("i", $id);
        $item_stmt->execute();
        $item = $item_stmt->get_result()->fetch_assoc() ?: $item;
        $item_stmt->close();
    }

    $bosses        = $_database->query("SELECT id, boss_name FROM plugins_raidplaner_bosses ORDER BY boss_name")->fetch_all(MYSQLI_ASSOC);
    $classes       = $_database->query("SELECT id, class_name FROM plugins_raidplaner_classes ORDER BY class_name")->fetch_all(MYSQLI_ASSOC);
    $bis_list      = [];
    if ($id > 0) {
        $bis_list_raw = $_database->query("SELECT class_id FROM plugins_raidplaner_bis_list WHERE item_id = ".(int)$id)->fetch_all(MYSQLI_ASSOC);
        $bis_list     = array_map('intval', array_column($bis_list_raw, 'class_id'));
    }
    $bisToggleAllId = 'bis-all-' . uniqid();

    echo '<h3><a href="?site=admin_raidplaner&tab=items" class="text-decoration-none">' . $languageService->get('breadcrumb_items') . '</a> &raquo; '
       . ($id > 0 ? $languageService->get('title_edit_item') : $languageService->get('title_new_item')) . '</h3>';

    echo '<div class="card mt-3"><div class="card-body">
            <form method="post" action="?site=admin_raidplaner">
                <input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token']).'">
                <input type="hidden" name="view" value="edit_item">
                <input type="hidden" name="id" value="'.(int)$id.'">

                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('form_item_name') . '</label>
                    <input type="text" class="form-control" name="item_name" value="'.htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES).'" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('form_slot') . '</label>
                    <input type="text" class="form-control" name="slot" value="'.htmlspecialchars($item['slot'] ?? '', ENT_QUOTES).'">
                </div>

                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('form_boss_source') . '</label>
                    <select name="boss_id" class="form-select">
                        <option value="0">' . $languageService->get('form_no_boss') . '</option>';

                        foreach ($bosses as $boss) {
                            $bossName = htmlspecialchars(stripslashes($boss['boss_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            echo '<option value="'.(int)$boss['id'].'" '.(((int)($item['boss_id'] ?? 0) === (int)$boss['id']) ? 'selected' : '').'>'
                                . $bossName
                                . '</option>';
                        }

    echo       '</select>
                </div>

                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('form_alt_source') . '</label>
                    <input type="text" class="form-control" name="source" value="'.htmlspecialchars($item['source'] ?? '', ENT_QUOTES).'"
                           placeholder="' . $languageService->get('placeholder_alt_source') . '">
                </div>'; 
                
    echo    '<div class="mb-3">
                <label class="form-label">' . $languageService->get('form_bis_classes') . '</label>';

    if (!empty($classes)) {
        echo    '<div class="form-check form-switch mb-2">
                    <input class="form-check-input bis-toggle-all" type="checkbox" id="'.$bisToggleAllId.'">
                    <label class="form-check-label" for="'.$bisToggleAllId.'"><strong>' . $languageService->get('form_activate_all') . '</strong></label>
                </div>';

        echo    '<div class="row">';
        foreach ($classes as $class) {
            $cid = (int)$class['id'];
            $checked = in_array($cid, $bis_list, true) ? 'checked' : '';
            echo    '<div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input bis-toggle" type="checkbox" role="switch"
                                   id="bis-'.$cid.'" value="'.$cid.'" name="bis_classes[]" '.$checked.'>
                            <label class="form-check-label" for="bis-'.$cid.'">'
                                . htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES) .
                            '</label>
                        </div>
                    </div>';
        }
        echo    '</div>';
    } else {
        echo    '<div class="text-muted small">' . $languageService->get('msg_no_classes_found') . '</div>';
    }

    echo    '</div>
             <button type="submit" name="save_item" class="btn btn-primary">' . $languageService->get('btn_save') . '</button>
             <a href="?site=admin_raidplaner&tab=items" class="btn btn-secondary">' . $languageService->get('btn_cancel') . '</a>
            </form>
        </div></div>';
    }
 elseif ($view === 'edit_boss') {
    $boss = ['boss_name' => '', 'tactics' => ''];

    if ($id > 0) {
        $stmt = $_database->prepare("SELECT * FROM plugins_raidplaner_bosses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $boss = $result->fetch_assoc();
        }
        $stmt->close();
    }

    echo '<h3>
            <a href="?site=admin_raidplaner&tab=bosses" class="text-decoration-none">' . $languageService->get('breadcrumb_bosses') . '</a> 
            &raquo; ' . ($id > 0 ? $languageService->get('title_edit_boss') : $languageService->get('title_new_boss')) . '
        </h3>';

    echo '<div class="card mt-3"><div class="card-body">

    <form method="post" action="?site=admin_raidplaner&view=edit_boss">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">
        <input type="hidden" name="boss_id" value="' . (int)$id . '">

        <div class="mb-3">
            <label class="form-label">' . $languageService->get('form_boss_name') . '</label>
            <input type="text" class="form-control" name="boss_name" 
                value="' . htmlspecialchars(stripslashes($boss['boss_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required>
        </div>

        <div class="mb-3">
            <label class="form-label">' . $languageService->get('form_tactics') . '</label>
            <textarea class="form-control" name="tactics" rows="16">' . htmlspecialchars(
                str_replace(
                    ["\\r\\n","\\n","\\r","\\'"],
                    ["\n","\n","\n","'"],
                    $boss['tactics'] ?? ''
                ),
                ENT_QUOTES,
                'UTF-8'
            ) . '</textarea>
        </div>

        <div class="mt-4 pt-3 gap-2">
            <button type="submit" name="save_boss" class="btn btn-primary">' . $languageService->get('btn_save') . '</button>
            <a href="?site=admin_raidplaner&tab=bosses" class="btn btn-secondary">' . $languageService->get('btn_cancel') . '</a>
        </div>
    </form>
    </div></div>';
    } elseif ($view === 'manage_attendance') {

    $raid_query = $_database->prepare("
        SELECT COALESCE(t.title, e.title) AS title
        FROM plugins_raidplaner_events e
        LEFT JOIN plugins_raidplaner_templates t ON e.template_id = t.id
        WHERE e.id = ?
    ");
    $raid_query->bind_param("i", $event_id);
    $raid_query->execute();
    $raid_res = $raid_query->get_result();
    $raid     = $raid_res->fetch_assoc();

    echo '<h3>' . $languageService->get('title_attendance_for') . ' '.htmlspecialchars($raid['title'] ?? $languageService->get('unknown_raid')).'</h3>';

    $signups_stmt = $_database->prepare("
        SELECT c.id, c.character_name, u.username, c.is_main, u.userID, s.role_id 
        FROM plugins_raidplaner_signups s 
        JOIN plugins_raidplaner_characters c ON s.character_id = c.id 
        JOIN users u ON c.userID = u.userID 
        WHERE s.event_id = ? AND s.status IN ('Angemeldet', 'Ersatzbank') 
        ORDER BY c.is_main DESC, u.username
    ");
    $signups_stmt->bind_param("i", $event_id);
    $signups_stmt->execute();
    $signups = $signups_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $user_ids = array_unique(array_column($signups, 'userID'));
    $all_characters = [];
    if (!empty($user_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $types = str_repeat('i', count($user_ids));
        
        $chars_stmt = $_database->prepare("SELECT id, userID, character_name FROM plugins_raidplaner_characters WHERE userID IN ($placeholders)");
        $chars_stmt->bind_param($types, ...$user_ids);
        $chars_stmt->execute();
        $all_characters_raw = $chars_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($all_characters_raw as $char) {
            $all_characters[$char['userID']][] = $char;
        }
    }

    $all_roles = $_database->query("SELECT id, role_name FROM plugins_raidplaner_roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);

    $attendance = [];
    $att_stmt = $_database->prepare("SELECT user_id, status FROM plugins_raidplaner_attendance WHERE event_id = ?");
    $att_stmt->bind_param("i", $event_id);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();
    while ($row = $att_result->fetch_assoc()) {
        $attendance[$row['user_id']] = $row['status'];
    }

    echo '<div class="card mt-3"><div class="card-body">
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
        <input type="hidden" name="event_id" value="'.$event_id.'">

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>' . $languageService->get('tbl_header_player') . '</th>
                    <th>' . $languageService->get('tbl_header_character') . ' <i class="bi bi-info-circle-fill text-info" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_char_editable') . '"></i></th>
                    <th>' . $languageService->get('tbl_header_role') . ' <i class="bi bi-info-circle-fill text-info" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_role_editable') . '"></i></th>
                    <th>' . $languageService->get('tbl_header_saved_status') . '</th>
                    <th>' . $languageService->get('tbl_header_action_attendance') . '</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($signups as $signup) {
        $saved_status = $attendance[$signup['userID']] ?? null;
        $status_badge = '';
        switch ($saved_status) {
            case 'Anwesend': $status_badge = '<span class="badge bg-success">' . $languageService->get('status_present') . '</span>'; break;
            case 'Ersatzbank': $status_badge = '<span class="badge bg-warning text-dark">' . $languageService->get('status_benched') . '</span>'; break;
            case 'Abwesend': $status_badge = '<span class="badge bg-danger">' . $languageService->get('status_absent') . '</span>'; break;
            case 'Verspätet': $status_badge = '<span class="badge bg-info text-dark">' . $languageService->get('status_late') . '</span>'; break;
            default: $status_badge = '<span class="badge bg-secondary">' . $languageService->get('status_not_recorded') . '</span>'; break;
        }

        echo '<tr>
                <td>'.htmlspecialchars($signup['username']).'</td>';

        $current_role_name = htmlspecialchars(array_values(array_filter($all_roles, fn($r) => $r['id'] == $signup['role_id']))[0]['role_name'] ?? 'N/A');
        echo '<td>
            <span class="editable-text" data-target="char-select-'.$signup['userID'].'">'.htmlspecialchars($signup['character_name']).'</span>
            <select name="signup_char['.$signup['userID'].']" id="char-select-'.$signup['userID'].'" class="form-select form-select-sm d-none">';
        if (isset($all_characters[$signup['userID']])) {
            foreach ($all_characters[$signup['userID']] as $char) {
                $selected = ($char['id'] == $signup['id']) ? 'selected' : '';
                echo '<option value="'.$char['id'].'" '.$selected.'>'.htmlspecialchars($char['character_name']).'</option>';
            }
        }
        echo '</select></td>';
        
        echo '<td>
            <span class="editable-text" data-target="role-select-'.$signup['userID'].'">'.$current_role_name.'</span>
            <select name="signup_role['.$signup['userID'].']" id="role-select-'.$signup['userID'].'" class="form-select form-select-sm d-none">';
        foreach ($all_roles as $role) {
            $selected = ($role['id'] == $signup['role_id']) ? 'selected' : '';
            echo '<option value="'.$role['id'].'" '.$selected.'>'.htmlspecialchars($role['role_name']).'</option>';
        }
        echo '</select></td>';

        echo '<td>'.$status_badge.'</td>';

        echo '<td>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="att-'.$signup['userID'].'-p" name="attendance['.$signup['userID'].']" value="Anwesend" '.($saved_status === 'Anwesend' ? 'checked' : '').'>
                    <label class="form-check-label" for="att-'.$signup['userID'].'-p">' . $languageService->get('status_present') . '</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="att-'.$signup['userID'].'-a" name="attendance['.$signup['userID'].']" value="Abwesend" '.($saved_status === 'Abwesend' ? 'checked' : '').'>
                    <label class="form-check-label" for="att-'.$signup['userID'].'-a">' . $languageService->get('status_absent') . '</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="att-'.$signup['userID'].'-l" name="attendance['.$signup['userID'].']" value="Ersatzbank" '.($saved_status === 'Ersatzbank' ? 'checked' : '').'>
                    <label class="form-check-label" for="att-'.$signup['userID'].'-l">' . $languageService->get('status_benched') . '</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="att-'.$signup['userID'].'-late" name="attendance['.$signup['userID'].']" value="Verspätet" '.($saved_status === 'Verspätet' ? 'checked' : '').'>
                    <label class="form-check-label" for="att-'.$signup['userID'].'-late">' . $languageService->get('status_late') . '</label>
                </div>
            </td>
        </tr>';
    }

    echo '</tbody></table>
          <input type="submit" name="save_attendance" value="' . $languageService->get('btn_save_changes') . '" class="btn btn-primary mt-3">
    </form></div></div>';

    $all_users = $_database->query("SELECT userID, username FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

    echo '<div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">' . $languageService->get('title_manual_signup') . '</h5></div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                <input type="hidden" name="event_id" value="'.$event_id.'">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">' . $languageService->get('tbl_header_player') . '</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">' . $languageService->get('form_select_player') . '</option>';
                            foreach($all_users as $user) {
                                echo '<option value="'.$user['userID'].'">'.htmlspecialchars($user['username']).'</option>';
                            }
    echo '              </select>
                    </div>
                    <div class="col-md-4 mb-3">
                         <label class="form-label">' . $languageService->get('tbl_header_character') . '</label>
                         <select name="character_id" class="form-select" required>
                            <option value="">' . $languageService->get('form_select_player_first') . '</option>
                         </select>
                    </div>
                    <div class="col-md-4 mb-3">
                         <label class="form-label">' . $languageService->get('tbl_header_role') . '</label>
                         <select name="role_id" class="form-select" required>
                            <option value="">' . $languageService->get('form_select_role') . '</option>';
                            foreach($all_roles as $role) {
                                echo '<option value="'.$role['id'].'">'.htmlspecialchars($role['role_name']).'</option>';
                            }
    echo '              </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('form_status') . '</label>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="status" id="status_angemeldet" value="Angemeldet" checked>
                      <label class="form-check-label" for="status_angemeldet">' . $languageService->get('status_signed_up') . '</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="status" id="status_ersatz" value="Ersatzbank">
                      <label class="form-check-label" for="status_ersatz">' . $languageService->get('status_benched_signup') . '</label>
                    </div>
                </div>
                <button type="submit" name="manual_signup" class="btn btn-success">' . $languageService->get('btn_signup_player') . '</button>
            </form>
        </div>
    </div>';
}
    elseif ($view === 'manage_loot') {
    $lootManager = new LootManager($_database);
    if (!isset($raidManager)) $raidManager = new RaidManager($_database, $settings);

    if (isset($_POST['save_loot'])) {
        validate_csrf_token($_POST['csrf_token'] ?? '');
        $lootManager->addLoot(
            $event_id,
            intval($_POST['item_id'] ?? 0),
            intval($_POST['character_id'] ?? 0),
            intval($_POST['boss_id'] ?? 0) > 0 ? intval($_POST['boss_id']) : null
        );
        header("Location: admincenter.php?site=admin_raidplaner&view=manage_loot&event_id=" . $event_id);
        exit;
    }

    $raid = $raidManager->getById($event_id);
    $raid_title    = $raid['title'] ?? $languageService->get('unknown_raid');

    $bossLookup = new BossLookup($_database);
    $bosses = $bossLookup->getBossesForEvent((int)$event_id);
    if (!is_array($bosses)) { $bosses = []; }

    $items   = $_database->query("SELECT id, item_name, boss_id, slot FROM plugins_raidplaner_items ORDER BY item_name ASC")->fetch_all(MYSQLI_ASSOC);
    $signups = $_database->query("
        SELECT c.id, c.character_name, u.username
        FROM plugins_raidplaner_signups s
        JOIN plugins_raidplaner_characters c ON s.character_id = c.id
        JOIN users u ON c.userID = u.userID
        WHERE s.event_id = $event_id AND s.status IN ('Angemeldet', 'Ersatzbank')
        ORDER BY u.username
    ")->fetch_all(MYSQLI_ASSOC);

    $loot_history_raw = $lootManager->getLootForRaid($event_id);

    $selBossId      = (int)($_POST['boss_id'] ?? $_GET['boss_id'] ?? 0);
    $selItemId      = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
    $selCharacterId = (int)($_POST['character_id'] ?? $_GET['character_id'] ?? 0);

    $filtered_items = [];
    foreach ($items as $it) {
        $itBoss = (int)($it['boss_id'] ?? 0);
        if ($selBossId > 0) {
            if ($itBoss === $selBossId) $filtered_items[] = $it;
        } else {
            if ($itBoss === 0) $filtered_items[] = $it;
        }
    }

    echo '<h3>' . $languageService->get('title_loot_management_for') . ' ' . htmlspecialchars($raid_title) . '</h3>';

    echo '<div class="row g-3">';

    echo '<div class="col-md-5">';
    echo '<div class="card">
            <div class="card-header"><h5 class="mb-0">' . $languageService->get('title_new_loot_entry') . '</h5></div>
            <div class="card-body">';

    echo '<form method="POST" action="?site=admin_raidplaner&view=manage_loot&event_id='.$event_id.'">
            <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';

    echo    '<div class="mb-3">
                <label class="form-label">' . $languageService->get('form_boss') . '</label>
                <select name="boss_id" class="form-select" onchange="this.form.submit()">';
                    echo '<option value="0" ' . ($selBossId === 0 ? 'selected' : '') . '>'
                        . $languageService->get('form_no_boss') . '</option>';
                    foreach($bosses as $boss) {
                        $bid = (int)$boss['id'];
                        echo '<option value="'.$bid.'" '.($selBossId === $bid ? 'selected' : '').'>'
                            . htmlspecialchars(stripslashes($boss['boss_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                    }
    echo        '</select>
            </div>';

    echo    '<div class="mb-3">
                <label class="form-label">' . $languageService->get('form_item') . '</label>
                <select name="item_id" class="form-select" required>';
                    if (empty($filtered_items)) {
                        $msg = $selBossId
                            ? ($languageService->get('msg_no_items_found') ?: 'Keine Items zu diesem Boss.')
                            : ($languageService->get('form_select_boss_first') ?: 'Bitte zuerst einen Boss wählen.');
                        echo '<option value="">' . htmlspecialchars($msg) . '</option>';
                    } else {
                        echo '<option value="">' . $languageService->get('form_select_item') . '</option>';
                        foreach($filtered_items as $item) {
                            $iid   = (int)$item['id'];
                            $label = '[' . htmlspecialchars($item['slot'] ?? '') . '] ' . htmlspecialchars($item['item_name'] ?? '');
                            $dataBoss = (int)($item['boss_id'] ?? 0);
                            echo '<option value="'.$iid.'" data-boss-id="'.$dataBoss.'" '.($selItemId === $iid ? 'selected' : '').'>'.$label.'</option>';
                        }
                    }
    echo        '</select>
            </div>';

    echo    '<div class="mb-3">
                <label class="form-label">' . $languageService->get('form_character') . '</label>
                <select name="character_id" class="form-select" required>';
                    echo '<option value="">' . $languageService->get('form_select_character') . '</option>';
                    foreach ($signups as $signup) {
                        $cid = (int)$signup['id'];
                        echo '<option value="'.$cid.'" '.($selCharacterId === $cid ? 'selected' : '').'>'
                            . htmlspecialchars($signup['username']) . ' (' . htmlspecialchars($signup['character_name']) . ')</option>';
                    }
    echo        '</select>
            </div>';

    echo    '<button type="submit" name="save_loot" class="btn btn-primary">' . $languageService->get('btn_save_loot') . '</button>';
    echo '</form>';

    echo    '</div>
        </div>';
    echo '</div>';

    echo '<div class="col-md-7">';
    echo '<div class="card">
            <div class="card-header"><h5 class="mb-0">'.$languageService->get('loot_history').'</h5></div>
            <div class="card-body">';
            echo '<div class="table-responsive">';
                echo ' <table class="table table-sm align-middle mb-0">';
                echo ' <thead class="table-light">';
                echo ' <tr>'
                . ' <th style="width:40%">' . htmlspecialchars($languageService->get('col_item')) . '</th>'
                . ' <th style="width:20%">' . htmlspecialchars($languageService->get('col_source')) . '</th>'
                . ' <th style="width:14%">' . htmlspecialchars($languageService->get('col_assigned_by')) . '</th>'
                . ' <th style="width:15%">' . htmlspecialchars($languageService->get('col_time')) . '</th>'
                . ' <th style="width:15%">' . htmlspecialchars($languageService->get('col_recipient')) . '</th>'
                . ' <th class="text-end" style="width:10%">' . htmlspecialchars($languageService->get('col_action')) . '</th>'
                . ' </tr>';
                echo ' </thead>';
                echo ' <tbody>';

                $cnt = 0;
                foreach ($loot_history_raw as $row) {
                if (++$cnt > 200) break;

                $itemName = htmlspecialchars(stripslashes($row['item_name'] ?? ''), ENT_QUOTES);
                $slot = htmlspecialchars($row['slot'] ?? '', ENT_QUOTES);
                $bossName = htmlspecialchars(stripslashes($row['boss_name'] ?? ''), ENT_QUOTES);
                $charName = htmlspecialchars(stripslashes($row['character_name'] ?? ''), ENT_QUOTES);
                $userName = htmlspecialchars(stripslashes($row['username'] ?? ''), ENT_QUOTES);
                $adminName = htmlspecialchars(stripslashes($row['assigned_by_username'] ?? ''), ENT_QUOTES);

                $tsRaw = $row['looted_at']
                ?? $row['created_at']
                ?? $row['assigned_at']
                ?? $row['date']
                ?? $row['ts']
                ?? $row['timestamp']
                ?? null;
                $timeStr = '–';
                if ($tsRaw !== null && $tsRaw !== '') {
                $ts = is_numeric($tsRaw) ? (int)$tsRaw : strtotime((string)$tsRaw);
                if ($ts && $ts > 0) {
                $timeStr = rp_format_dt($ts, 'd.m.Y H:i');
                }
                }

                $recipient = $charName !== '' && $userName !== ''
                ? $userName . ' (' . $charName . ')'
                : ($charName !== '' ? $charName : $userName);

                $historyId = (int)($row['id'] ?? 0);

                echo ' <tr>';
                echo ' <td><span class="fw-semibold">' . '['.$slot.'] ' . $itemName . '</span></td>';
                $bossBadge = $bossName !== ''
                ? '<span class="badge bg-secondary">'. $bossName .'</span>'
                : '<span class="badge bg-secondary">'. htmlspecialchars($languageService->get('form_no_boss') ?: 'Kein Boss / Worlddrop', ENT_QUOTES) .'</span>';
                echo ' <td>'.$bossBadge.'</td>';
                echo '<td>' . ($adminName !== '' ? $adminName : '–') . '</td>';
                echo ' <td><span class="text-muted">' . htmlspecialchars($timeStr) . '</span></td>';
                echo ' <td>' . $recipient . '</td>';
                echo ' <td class="text-end">'
                . ' <button type="button" class="btn btn-danger btn-delete"'
                . ' data-bs-toggle="modal" data-bs-target="#deleteModal"'
                . ' data-type="loot" data-id="' . $historyId . '"'
                . ' aria-label="' . htmlspecialchars($languageService->get('btn_delete')) . '">'
                . ' <i class="bi bi-trash"></i>'
                . ' </button>'
                . ' </td>';
                echo ' </tr>';
                }

                echo ' </tbody>';
                echo ' </table></div></div></div></div></div>';
} if ($view === 'overview') {
    ?>         
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $tab == 'raids' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=raids"><?= $languageService->get('tab_raids') ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'templates' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=templates"><?= $languageService->get('tab_templates') ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'items' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=items"><?= $languageService->get('tab_items') ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'bosses' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=bosses"><?= $languageService->get('tab_bosses') ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'wishlists' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=wishlists"><?= $languageService->get('tab_wishlists') ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'stats' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=stats"><?= $languageService->get('tab_stats') ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'settings' ? 'active' : '' ?>" href="?site=admin_raidplaner&tab=settings"><?= $languageService->get('tab_settings') ?></a></li>
    </ul>

    <div class="tab-content">
                <div class="tab-pane fade <?= $tab == 'raids' ? 'show active' : '' ?>" id="raids">
            <?php
                $stats_total_raids = $_database->query("SELECT COUNT(*) as count FROM plugins_raidplaner_events")->fetch_assoc()['count'];
                $stats_total_signups = $_database->query("
                    SELECT COUNT(*) as count 
                    FROM plugins_raidplaner_signups s 
                    JOIN plugins_raidplaner_events e ON s.event_id = e.id
                    WHERE s.status IN ('Angemeldet', 'Ersatzbank') AND (e.event_time >= NOW() OR e.event_time + INTERVAL e.duration_minutes MINUTE >= NOW())
                ")->fetch_assoc()['count'];
            ?>
            <div class="row pb-4">
                <div class="col-md-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-calendar-check-fill text-success"></i> <?= $languageService->get('stats_total_raids') ?></h5>
                            <p class="card-text fs-2 fw-bold"><?= $stats_total_raids ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-people-fill text-info"></i> <?= $languageService->get('stats_signups') ?></h5>
                            <p class="card-text text-secondary small"><?= $languageService->get('stats_signups_info') ?></p>
                            <p class="card-text fs-2 fw-bold"><?= $stats_total_signups ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-trophy-fill text-warning"></i> <?= $languageService->get('stats_top_raiders') ?></h5>
                            <?php
                            $att_stmt = $_database->prepare("
                                SELECT u.username,
                                    (SUM(CASE WHEN ra.status = 'Anwesend' THEN 1 ELSE 0 END) + 0.5 * SUM(CASE WHEN ra.status = 'Verspätet' THEN 1 ELSE 0 END)) /
                                    NULLIF(SUM(CASE WHEN ra.status IN ('Anwesend','Abwesend','Verspätet') THEN 1 ELSE 0 END), 0) AS ratio
                                FROM plugins_raidplaner_attendance ra
                                JOIN users u ON ra.user_id = u.userID
                                JOIN plugins_raidplaner_events re ON ra.event_id = re.id
                                GROUP BY u.userID
                                HAVING COUNT(ra.id) > 0
                                ORDER BY ratio DESC
                                LIMIT 3
                            ");
                            $att_stmt->execute();
                            $top_raiders = $att_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                            if (empty($top_raiders)) {
                                echo '<p class="card-text text-muted">' . $languageService->get('msg_no_attendance_data') . '</p>';
                            } else {
                                echo '<ol class="list-group list-group-numbered text-start">';
                                foreach ($top_raiders as $raider) {
                                    $percentage = round(($raider['ratio'] ?? 0) * 100);
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">'
                                            .htmlspecialchars($raider['username']).
                                            '<span class="badge bg-success rounded-pill">'.$percentage.'%</span>'.
                                        '</li>';
                                }
                                echo '</ol>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- Geplante Raids -->
                <div class="col-lg-6 mb-4">
                    <div class="card raids h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> <?= $languageService->get('title_planned_raids') ?></h5>
                        <a href="?site=admin_raidplaner&view=edit_raid" class="btn btn-primary">
                        <i class="bi bi-plus"></i> <?= $languageService->get('btn_create_new_raid') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php
                        $raidManager = new RaidManager($_database, $settings);
                        $raids = $raidManager->getAll();

                        $attendance_counts_raw = $_database->query("
                            SELECT event_id, COUNT(id) as count 
                            FROM plugins_raidplaner_attendance 
                            GROUP BY event_id
                        ")->fetch_all(MYSQLI_ASSOC);
                        $attendance_counts = array_column($attendance_counts_raw, 'count', 'event_id');

                        $cutoff = strtotime('today');
                        $planned = [];
                        $past    = [];
                        foreach ($raids as $raid) {
                            $ts = strtotime($raid['event_time'] ?? '');
                            if ($ts !== false && $ts < $cutoff) {
                                $past[] = $raid;
                            } else {
                                $planned[] = $raid;
                            }
                        }

                        if (empty($planned)) {
                            echo "<div class='alert alert-info mb-0'>" . $languageService->get('msg_no_raids_found') . "</div>";
                        } else {
                            echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>'
                            . '<th>' . $languageService->get('tbl_header_title') . '</th>'
                            . '<th>' . $languageService->get('tbl_header_date') . '</th>'
                            . '<th class="text-center">' . $languageService->get('tbl_header_signups') . '</th>'
                            . '<th class="text-end">' . $languageService->get('tbl_header_actions') . '</th>'
                            . '</tr></thead><tbody>';

                            foreach($planned as $raid) {
                            $attendance_count = $attendance_counts[$raid['id']] ?? 0;
                            $attendance_badge_html = '';
                            $row_class = '';

                            $eventTs = strtotime($raid['event_time']);
                            if ($eventTs < time() && $attendance_count < ($raid['signup_count'] ?? 0)) {
                                $tooltip_text = $languageService->get('tooltip_attendance_not_confirmed');
                                $attendance_badge_html = ' <span class="badge bg-danger rounded-pill" data-bs-toggle="tooltip" title="' . $tooltip_text . '">!</span>';
                                $row_class = 'border-start border-warning border-4';
                            }

                            echo '<tr class="' . $row_class . '">
                                    <td>' . htmlspecialchars($raid['title'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                                    <td>' . rp_format_dt($eventTs, 'd.m.Y H:i') . '</td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill">' . (int)($raid['signup_count'] ?? 0) . '</span>' . $attendance_badge_html . '
                                    </td>
                                    <td class="text-end">
                                        <a href="?site=admin_raidplaner&view=manage_attendance&event_id=' . (int)$raid['id'] . '" class="btn btn-info" title="' . $languageService->get('btn_title_attendance') . '"><i class="bi bi-person-check"></i></a>
                                        <a href="?site=admin_raidplaner&view=manage_loot&event_id=' . (int)$raid['id'] . '" class="btn btn-success" title="' . $languageService->get('btn_title_loot') . '"><i class="bi bi-gem"></i></a>
                                        <a href="?site=admin_raidplaner&view=edit_raid&event_id=' . (int)$raid['id'] . '" class="btn btn-warning" title="' . $languageService->get('btn_title_edit') . '"><i class="bi bi-pencil"></i></a>
                                        <button class="btn btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-type="raid" data-id="' . (int)$raid['id'] . '" title="' . $languageService->get('btn_title_delete') . '"><i class="bi bi-trash"></i></button>
                                    </td>
                                    </tr>';
                            }
                            echo '</tbody></table></div>';
                        }
                        ?>
                    </div>
                    </div>
                </div>

                <!-- Vergangene Raids -->
                <div class="col-lg-6 mb-4">
                    <div class="card raids past-raids h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-archive"></i> <?= $languageService->get('title_past_raids') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        if (empty($past)) {
                            echo "<div class='alert alert-light mb-0 text-muted'>" . $languageService->get('msg_no_past_raids') . "</div>";
                        } else {
                            echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>'
                            . '<th>' . $languageService->get('tbl_header_title') . '</th>'
                            . '<th>' . $languageService->get('tbl_header_date') . '</th>'
                            . '<th class="text-center">' . $languageService->get('tbl_header_signups') . '</th>'
                            . '<th class="text-end">' . $languageService->get('tbl_header_actions') . '</th>'
                            . '</tr></thead><tbody>';

                            foreach($past as $raid) {
                            $attendance_count = $attendance_counts[$raid['id']] ?? 0;
                            $attendance_badge_html = '';
                            $row_class = '';

                            $eventTs = strtotime($raid['event_time']);
                            if ($eventTs < time() && $attendance_count < ($raid['signup_count'] ?? 0)) {
                                $tooltip_text = $languageService->get('tooltip_attendance_not_confirmed');
                                $attendance_badge_html = ' <span class="badge bg-danger rounded-pill" data-bs-toggle="tooltip" title="' . $tooltip_text . '">!</span>';
                                $row_class = 'border-start border-warning border-4';
                            }

                            echo '<tr class="' . $row_class . '">
                                    <td>' . htmlspecialchars($raid['title'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                                    <td>' . rp_format_dt($eventTs, 'd.m.Y H:i') . '</td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill">' . (int)($raid['signup_count'] ?? 0) . '</span>' . $attendance_badge_html . '
                                    </td>
                                    <td class="text-end">
                                        <a href="?site=admin_raidplaner&view=manage_attendance&event_id=' . (int)$raid['id'] . '" class="btn btn-info" title="' . $languageService->get('btn_title_attendance') . '"><i class="bi bi-person-check"></i></a>
                                        <a href="?site=admin_raidplaner&view=manage_loot&event_id=' . (int)$raid['id'] . '" class="btn btn-success" title="' . $languageService->get('btn_title_loot') . '"><i class="bi bi-gem"></i></a>
                                        <a href="?site=admin_raidplaner&view=edit_raid&event_id=' . (int)$raid['id'] . '" class="btn btn-warning" title="' . $languageService->get('btn_title_edit') . '"><i class="bi bi-pencil"></i></a>
                                        <button class="btn btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-type="raid" data-id="' . (int)$raid['id'] . '" title="' . $languageService->get('btn_title_delete') . '"><i class="bi bi-trash"></i></button>
                                    </td>
                                    </tr>';
                            }
                            echo '</tbody></table></div>';
                        }
                        ?>
                    </div>
                    </div>
                </div>
                </div>
        </div>
        <div class="tab-pane fade <?= $tab == 'templates' ? 'show active' : '' ?>" id="templates">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clipboard2-plus"></i> <?= $languageService->get('title_raid_templates') ?></h5>
                    <a href="?site=admin_raidplaner&view=edit_template" class="btn btn-primary">
                        <i class="bi bi-plus"></i> <?= $languageService->get('title_new_template') ?>
                    </a>
                </div>
                <div class="card-body">
                <?php
                    $templates = [];
                    $tblChk = $_database->query("SHOW TABLES LIKE 'plugins_raidplaner_templates'");
                    if ($tblChk && $tblChk->num_rows > 0) {
                        $cols = [];
                        if ($resCols = $_database->query("SHOW COLUMNS FROM plugins_raidplaner_templates")) {
                            while ($c = $resCols->fetch_assoc()) {
                                $cols[strtolower($c['Field'])] = true;
                            }
                        }
                        $nameCol = null;
                        if (isset($cols['template_name'])) {
                            $nameCol = 'template_name';
                        } elseif (isset($cols['name'])) {
                            $nameCol = 'name';
                        } elseif (isset($cols['title'])) {
                            $nameCol = 'title';
                        } else {
                            $nameCol = null;
                        }

                        $hasTitle = isset($cols['title']);

                        $selectParts = ["id"];
                        if ($nameCol) {
                            $selectParts[] = "{$nameCol} AS template_name";
                        } else {
                            $selectParts[] = "'' AS template_name";
                        }
                        if ($hasTitle) {
                            $selectParts[] = "title";
                        } else {
                            $selectParts[] = ($nameCol ? "{$nameCol}" : "''") . " AS title";
                        }

                        $orderBy = $nameCol ? "{$nameCol}, id" : "id";
                        $sql = "SELECT " . implode(", ", $selectParts) . " FROM plugins_raidplaner_templates ORDER BY {$orderBy}";
                        if ($res = $_database->query($sql)) {
                            while ($r = $res->fetch_assoc()) {
                                $templates[] = [
                                    'id'            => (int)$r['id'],
                                    'template_name' => (string)($r['template_name'] ?? ''),
                                    'title'         => (string)($r['title'] ?? ($r['template_name'] ?? '')),
                                ];
                            }
                        }
                    }

                    if (empty($templates)) {
                        echo "<div class='alert alert-info'>" . $languageService->get('msg_no_templates_found') . "</div>";
                    } else {
                        echo '<table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>' . $languageService->get('tbl_header_name') . '</th>
                                        <th>' . $languageService->get('form_raid_title') . '</th>
                                        <th class="text-end">' . $languageService->get('tbl_header_actions') . '</th>
                                    </tr>
                                </thead>
                                <tbody>';
                        foreach ($templates as $template) {
                            $name  = htmlspecialchars($template['template_name'] ?? '', ENT_QUOTES, 'UTF-8');
                            $title = htmlspecialchars($template['title'] ?? ($template['template_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $id    = (int)$template['id'];

                            echo '<tr>
                                    <td>' . $name . '</td>
                                    <td>' . $title . '</td>
                                    <td class="text-end">
                                        <a href="?site=admin_raidplaner&view=edit_template&template_id=' . $id . '" class="btn btn btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-type="template" data-id="' . $id . '">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>';
                        }
                        echo '  </tbody>
                            </table>';
                    }
                ?>
                </div>
            </div>
        </div>
        <div class="tab-pane fade <?= $tab == 'items' ? 'show active' : '' ?>" id="items">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-gem"></i> <?= $languageService->get('title_item_database') ?></h5>
                    <a href="?site=admin_raidplaner&view=edit_item" class="btn btn-primary"><i class="bi bi-plus"></i> <?= $languageService->get('title_new_item') ?></a>
                </div>
                <div class="card-body">
                    <?php
                    $itemManager = new ItemManager($_database);
                    $items_grouped = $itemManager->getAllItemsGroupedByClass();

                    if(empty($items_grouped)) { 
                        echo "<div class='alert alert-info'>" . $languageService->get('msg_no_items_found') . "</div>"; 
                    } else {
                        echo '<table class="table table-hover align-middle mb-0"><thead><tr><th>' . $languageService->get('tbl_header_name') . '</th><th>' . $languageService->get('form_slot') . '</th><th>' . $languageService->get('tbl_header_source') . '</th><th>' . $languageService->get('tbl_header_bis_classes') . '</th><th class="text-end">' . $languageService->get('tbl_header_actions') . '</th></tr></thead><tbody>';
                        foreach($items_grouped as $id => $item) {
                            echo '<tr><td>' . htmlspecialchars($item['item_name']) . '</td><td>' . htmlspecialchars($item['slot']) . '</td><td>' . htmlspecialchars(($item['boss_name'] !== null && $item['boss_name'] !== '')? stripslashes($item['boss_name']): $languageService->get('text_worlddrop_quest')) . '</td><td>';
                            '</td><td>';
                            if(!empty($item['classes'])) { echo implode(', ', array_map('htmlspecialchars', $item['classes'])); } else { echo '-'; }
                            echo '</td><td class="text-end"><a href="?site=admin_raidplaner&view=edit_item&id='.$id.'" class="btn btn-warning"><i class="bi bi-pencil"></i></a> <button class="btn btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-type="item" data-id="'.$id.'"><i class="bi bi-trash"></i></button></td></tr>';
                        }
                        echo '</tbody></table>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="tab-pane fade <?= $tab == 'bosses' ? 'show active' : '' ?>" id="bosses">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bug-fill"></i> <?= $languageService->get('tab_bosses') ?? 'Bosse' ?></h5>
                    <a href="?site=admin_raidplaner&view=edit_boss" class="btn btn-primary"><i class="bi bi-plus"></i> <?= $languageService->get('title_new_boss') ?></a>
                </div>

                <div class="card-body">
                    <div class="collapse <?= isset($_GET['edit_boss']) ? 'show' : '' ?>" id="bossFormWrap">
                        <div class="card card-body mb-4">
                            <?php
                                $editBossId = (int)($_GET['edit_boss'] ?? 0);
                                    $bossForm = ['id' => 0, 'boss_name' => '', 'tactics' => ''];

                                    if ($editBossId > 0) {
                                        $hasTactics = false;
                                        $hasDesc    = false;
                                        if ($cols = $_database->query("SHOW COLUMNS FROM plugins_raidplaner_bosses")) {
                                            while ($c = $cols->fetch_assoc()) {
                                                $f = strtolower($c['Field']);
                                                if ($f === 'tactics')      { $hasTactics = true; }
                                                if ($f === 'description')  { $hasDesc    = true; }
                                            }
                                        }

                                        if ($hasTactics) {
                                            $st = $_database->prepare("SELECT id, boss_name, tactics FROM plugins_raidplaner_bosses WHERE id=?");
                                            $st->bind_param("i", $editBossId);
                                        } elseif ($hasDesc) {
                                            $st = $_database->prepare("SELECT id, boss_name, description AS tactics FROM plugins_raidplaner_bosses WHERE id=?");
                                            $st->bind_param("i", $editBossId);
                                        } else {
                                            $st = $_database->prepare("SELECT id, boss_name FROM plugins_raidplaner_bosses WHERE id=?");
                                            $st->bind_param("i", $editBossId);
                                        }

                                        if ($st && $st->execute()) {
                                            $res = $st->get_result();
                                            if ($res) {
                                                $row = $res->fetch_assoc();
                                                if ($row) {
                                                    $bossForm['id']        = (int)$row['id'];
                                                    $bossForm['boss_name'] = stripslashes((string)$row['boss_name']);
                                                    if (array_key_exists('tactics', $row)) {
                                                        $bossForm['tactics'] = (string)($row['tactics'] ?? '');
                                                    }
                                                }
                                            }
                                        }
                                        if ($st) $st->close();
                                    }
                            ?>
                            <form method="post" action="?site=admin_raidplaner&tab=bosses">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="boss_id" value="<?= (int)$bossForm['id'] ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label"><?= $languageService->get('form_boss_name') ?? 'Bossname' ?></label>
                                        <input type="text" class="form-control" name="boss_name"
                                            value="<?= htmlspecialchars(stripslashes($bossForm['boss_name']), ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>

                                    <?php
                                        $hasTactics = false;
                                        $hasDesc    = false;
                                        if ($cols = $_database->query("SHOW COLUMNS FROM plugins_raidplaner_bosses")) {
                                            while ($c = $cols->fetch_assoc()) {
                                                $f = strtolower($c['Field']);
                                                if ($f === 'tactics')     { $hasTactics = true; }
                                                if ($f === 'description') { $hasDesc    = true; }
                                            }
                                        }
                                        if ($hasTactics || $hasDesc):
                                    ?>
                                    <div class="col-12">
                                        <label class="form-label"><?= $languageService->get('form_tactics') ?? 'Taktik' ?></label>
                                        <textarea class="form-control" name="tactics" rows="14"><?= htmlspecialchars(
                                            ltrim(
                                                str_replace(["\\r\\n","\\n","\\r","\\'"], ["\n","\n","\n","'"], $bossForm['tactics'] ?? ''),
                                                "\r\n\t "
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?></textarea>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3 gap-2">
                                    <button class="btn btn-primary" type="submit" name="save_boss" value="1">
                                        <?= $languageService->get('btn_save') ?? 'Speichern' ?>
                                    </button>
                                    <a class="btn btn-secondary" href="admincenter.php?site=admin_raidplaner&tab=bosses">
                                        <?= $languageService->get('btn_cancel') ?? 'Abbrechen' ?>
                                    </a>
                                </div>
                            </form>

                        </div>
                    </div>

                    <?php
                        $items = [];
                        if ($res = $_database->query("SELECT id, boss_name FROM plugins_raidplaner_bosses ORDER BY boss_name, id")) {
                            while ($row = $res->fetch_assoc()) {
                                $items[] = ['id' => (int)$row['id'], 'boss_name' => (string)$row['boss_name']];
                            }
                        }

                        if (empty($items)) {
                            echo "<div class='alert alert-info'>" . ($languageService->get('msg_no_bosses_found') ?? 'Keine Bosse vorhanden.') . "</div>";
                        } else {
                            echo '<table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>' . ($languageService->get('tbl_header_name') ?? 'Name') . '</th>
                                            <th class="text-end">' . ($languageService->get('tbl_header_actions') ?? 'Aktionen') . '</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                            foreach ($items as $b) {
                                $name = htmlspecialchars(stripslashes($b['boss_name']), ENT_QUOTES, 'UTF-8');
                                echo '<tr>
                                        <td>' . $name . '</td>
                                        <td class="text-end">
                                            <a class="btn btn-warning" href="?site=admin_raidplaner&tab=bosses&edit_boss=' . $b['id'] . '"><i class="bi bi-pencil"></i></a>
                                            <button class="btn btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-type="boss" data-id="' . $b['id'] . '"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>';
                            }
                            echo '    </tbody>
                                </table>';
                        }
                    ?>
                </div>
            </div>
        </div>
        <div class="tab-pane fade <?= $tab == 'wishlists' ? 'show active' : '' ?>" id="wishlists">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-card-checklist"></i> <?= $languageService->get('title_open_wishes') ?></h5>
                        </div>
                        <div class="card-body" style="max-height: 800px; overflow-y: auto;">
                            <?php
                            echo '<div class="d-flex justify-content-end align-items-center mb-2">
                                    <span class="me-2">' . $languageService->get('legend') . '</span>
                                    <span class="badge bg-success" style="cursor: pointer;" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_char_is_main') . '">' . $languageService->get('badge_main') . '</span>
                                    <span class="badge bg-secondary ms-1" style="cursor: pointer;" data-bs-toggle="tooltip" data-bs-title="' . $languageService->get('tooltip_char_is_alt') . '">' . $languageService->get('badge_alt') . '</span>
                                </div>';

                            $wishes_stmt = $_database->prepare("
                                SELECT
                                    i.item_name, c.character_name, u.username, c.is_main,
                                    cg.is_obtained AS status, cg.status_changed_at
                                FROM plugins_raidplaner_character_gear cg
                                JOIN (
                                    SELECT character_id, item_id, MAX(status_changed_at) AS max_changed
                                    FROM plugins_raidplaner_character_gear
                                    GROUP BY character_id, item_id
                                ) t ON t.character_id = cg.character_id
                                AND t.item_id = cg.item_id
                                AND (cg.status_changed_at = t.max_changed OR t.max_changed IS NULL)
                                JOIN plugins_raidplaner_characters c ON cg.character_id = c.id
                                JOIN users u ON c.userID = u.userID
                                JOIN plugins_raidplaner_items i ON cg.item_id = i.id
                                WHERE cg.is_obtained IN (0, 2)
                                ORDER BY i.item_name, cg.is_obtained DESC, u.username
                            ");
                            $wishes_stmt->execute();
                            $wishes_result = $wishes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                            $wishes_by_item = [];
                            foreach ($wishes_result as $wish) {
                                $wishes_by_item[$wish['item_name']][] = $wish;
                            }

                            if (empty($wishes_by_item)) {
                                echo '<p class="text-muted">' . $languageService->get('msg_no_wishes_found') . '</p>'; 
                            } else {
                                echo '<table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>' . $languageService->get('form_item') . '</th>
                                                <th>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span>' . $languageService->get('tbl_header_wishes_from_players') . '</span>
                                                        <span class="text-muted">' . $languageService->get('tbl_header_wishes_from_players_since') . '</span>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                                foreach ($wishes_by_item as $item_name => $wishes) {
                                    echo '<tr>
                                            <td class="fw-bold">' . htmlspecialchars($item_name) . '</td>
                                            <td>
                                                <ul class="list-unstyled mb-0">';
                                    
                                    foreach ($wishes as $wish) {
                                        $wishlist_text = $languageService->get('badge_wishlist');
                                        $needed_text   = $languageService->get('badge_needed');

                                        $status_badge = ((int)$wish['status'] === 2)
                                            ? '<span class="badge bg-warning text-dark" title="' . $wishlist_text . '"><i class="bi bi-star-fill"></i> ' . $wishlist_text . '</span>'
                                            : '<span class="badge bg-secondary" title="' . $needed_text . '">' . $needed_text . '</span>';

                                        $main_badge = ((int)$wish['is_main'] === 1)
                                            ? ' <span class="badge bg-success">' . $languageService->get('badge_main') . '</span>'
                                            : ' <span class="badge bg-secondary">' . $languageService->get('badge_alt') . '</span>';

                                        $since_html = '';
                                        if ((int)$wish['status'] === 2 && !empty($wish['status_changed_at'])) {
                                            if (function_exists('rp_format_since')) {
                                                $since_label = rp_format_since($languageService, $wish['status_changed_at']);
                                            } else {
                                                $ts = strtotime((string)$wish['status_changed_at']);
                                                $since_label = '';
                                                if ($ts) {
                                                    $diff = max(0, time() - $ts);
                                                    $d = (int)floor($diff / 86400);
                                                    if ($d >= 1) {
                                                        $since_label = ($d === 1)
                                                            ? $languageService->get('since_one_day')
                                                            : sprintf($languageService->get('since_days'), $d);
                                                    } else {
                                                        $h = (int)floor($diff / 3600);
                                                        if ($h >= 1) {
                                                            $since_label = ($h === 1)
                                                                ? $languageService->get('since_one_hour')
                                                                : sprintf($languageService->get('since_hours'), $h);
                                                        } else {
                                                            $m = (int)floor($diff / 60);
                                                            $since_label = ($m <= 1)
                                                                ? $languageService->get('since_today')
                                                                : sprintf($languageService->get('since_minutes'), $m);
                                                        }
                                                    }
                                                }
                                            }
                                            if (!empty($since_label)) {
                                                $since_html = '<span class="text-muted small">' . htmlspecialchars($since_label) . '</span>';
                                            }
                                        }

                                        echo '<li class="py-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>' . htmlspecialchars($wish['username']) . ' (' . htmlspecialchars($wish['character_name']) . $main_badge . ')</div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        ' . $status_badge . '
                                                        ' . $since_html . '
                                                    </div>
                                                </div>
                                            </li>';
                                    }
                                    
                                    echo '          </ul>
                                            </td>
                                        </tr>';
                                }
                                
                                echo '      </tbody>
                                    </table>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-trophy-fill"></i> <?= $languageService->get('title_recently_awarded_loot') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $awarded_stmt = $_database->prepare("
                                SELECT 
                                    lh.id              AS lh_id,
                                    lh.looted_at       AS looted_at,
                                    lh.original_wish_status,
                                    lh.character_id    AS character_id,
                                    lh.item_id         AS item_id,
                                    i.item_name,
                                    c.character_name,
                                    u.username,
                                    a.username         AS assigned_by_username
                                FROM plugins_raidplaner_loot_history lh
                                JOIN plugins_raidplaner_items       i ON lh.item_id     = i.id
                                JOIN plugins_raidplaner_characters  c ON lh.character_id = c.id
                                JOIN users                    u ON lh.user_id     = u.userID
                                LEFT JOIN users               a ON lh.assigned_by = a.userID
                                WHERE lh.original_wish_status IS NOT NULL
                                ORDER BY lh.looted_at DESC
                                LIMIT 30
                            ");
                            $awarded_stmt->execute();
                            $awarded_items = $awarded_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                            if (empty($awarded_items)) {
                                echo '<p class="text-muted">' . $languageService->get('msg_no_items_awarded_to_wishers') . '</p>';
                                } else {
                                echo '<div class="table-responsive">';
                                echo ' <table class="table table-sm align-middle mb-0">';
                                echo ' <thead class="table-light">';
                                echo ' <tr>'
                                . ' <th style="width:38%">' . htmlspecialchars($languageService->get('col_item') ?? 'Item') . '</th>'
                                . ' <th style="width:16%">' . htmlspecialchars($languageService->get('col_assigned_by')) . '</th>'
                                . ' <th style="width:24%">' . htmlspecialchars($languageService->get('col_awarded_to')) . '</th>'
                                . ' <th style="width:18%">' . htmlspecialchars($languageService->get('col_time') ?? 'Zeit') . '</th>'
                                . ' <th style="width:10%">' . htmlspecialchars($languageService->get('col_status') ?? 'Status') . '</th>'
                                . ' <th class="text-end" style="width:10%">' . htmlspecialchars($languageService->get('col_action') ?? 'Aktion') . '</th>'
                                . ' </tr>';
                                echo ' </thead>';
                                echo ' <tbody>';

                                foreach ($awarded_items as $item) {
                                $historyId = isset($item['lh_id']) ? (int)$item['lh_id'] : (int)($item['id'] ?? 0);

                                // Status-Badge
                                $status_badge = '';
                                if ((int)$item['original_wish_status'] === 2) {
                                $status_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> ' . htmlspecialchars($languageService->get('badge_wishlist')) . '</span>';
                                } elseif ((int)$item['original_wish_status'] === 0) {
                                $status_badge = '<span class="badge bg-secondary">' . htmlspecialchars($languageService->get('badge_needed')) . '</span>';
                                }

                                // Zeitformat
                                $ts_str = '';
                                if (!empty($item['looted_at'])) {
                                $t = strtotime((string)$item['looted_at']);
                                if ($t) $ts_str = rp_format_dt($t, 'd.m.Y H:i');
                                }

                                echo ' <tr>';
                                // Item
                                echo ' <td><span class="fw-semibold">' . htmlspecialchars($item['item_name']) . '</span></td>';
                                // Vergeben von
                                $assignedBy = !empty($item['assigned_by_username'])
                                    ? htmlspecialchars($item['assigned_by_username'])
                                    : '<span class="text-muted">–</span>';
                                echo ' <td>' . $assignedBy . '</td>';
                                // Empfänger
                                echo ' <td>' . htmlspecialchars($item['username']) . ' (' . htmlspecialchars($item['character_name']) . ')</td>';
                                // Zeit
                                echo ' <td><span class="text-muted">' . htmlspecialchars($ts_str) . '</span></td>';
                                // Status
                                echo ' <td>' . $status_badge . '</td>';
                                // Aktion
                                echo ' <td class="text-end">'
                                . ' <button type="button" class="btn btn-danger btn-delete"'
                                . ' data-bs-toggle="modal" data-bs-target="#deleteModal"'
                                . ' data-type="loot" data-id="' . $historyId . '"'
                                . ' title="' . htmlspecialchars($languageService->get('btn_delete')) . '">'
                                . ' <i class="bi bi-trash"></i>'
                                . ' </button>'
                                . ' </td>';
                                echo ' </tr>';
                                }
                                echo ' </tbody>';
                                echo ' </table>';
                                echo '</div>';
                                }
                                ?>
                </div>
            </div>
        </div>
</div>
        </div>
        <div class="tab-pane fade <?= $tab == 'stats' ? 'show active' : '' ?>" id="stats">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header"><h5 class="mb-0"><i class="bi bi-clipboard-data"></i> <?= $languageService->get('title_attendance_per_player') ?></h5></div>
                        <div class="card-body">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?= $languageService->get('tbl_header_player') ?></th>
                                        <th class="text-center"><?= $languageService->get('tbl_header_attendance_rate') ?>
                                            <i class="bi bi-info-circle ms-1"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="<?= htmlspecialchars($languageService->get('tooltip_attendance_rate_calc')) ?>">
                                            </i>
                                        </th>
                                        <th class="text-center bg-success bg-opacity-50"><?= $languageService->get('tbl_header_present') ?></th>
                                        <th class="text-center bg-warning bg-opacity-50"><?= $languageService->get('tbl_header_late') ?></th>
                                        <th class="text-center bg-danger bg-opacity-50"><?= $languageService->get('tbl_header_absent') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $att_stmt = $_database->prepare("
                                        SELECT u.username,
                                            SUM(CASE WHEN ra.status = 'Anwesend' THEN 1 ELSE 0 END) AS present_count,
                                            SUM(CASE WHEN ra.status = 'Abwesend' THEN 1 ELSE 0 END) AS absent_count,
                                            SUM(CASE WHEN ra.status = 'Verspätet' THEN 1 ELSE 0 END) AS late_count
                                        FROM plugins_raidplaner_attendance ra
                                        JOIN users u ON ra.user_id = u.userID
                                        JOIN plugins_raidplaner_events re ON ra.event_id = re.id
                                        GROUP BY u.userID
                                        ORDER BY u.username ASC
                                    ");
                                    $att_stmt->execute();
                                    $attendance_stats = $att_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                                    if (empty($attendance_stats)) { 
                                        echo '<tr><td colspan="5" class="text-center text-muted">' . $languageService->get('msg_no_attendance_data') . '</td></tr>'; 
                                    } 
                                    else {
                                        foreach ($attendance_stats as $stat) {
                                            $total_recorded = $stat['present_count'] + $stat['absent_count'] + $stat['late_count'];
                                            $percentage = ($total_recorded > 0) ? round((($stat['present_count'] + 0.5 * $stat['late_count']) / $total_recorded) * 100) : 0;
                                            echo '<tr><td><strong>'.htmlspecialchars($stat['username']).'</strong></td><td class="text-center"><div class="progress" style="height: 20px;"><div class="progress-bar bg-success" role="progressbar" style="width: '.$percentage.'%;" aria-valuenow="'.$percentage.'" aria-valuemin="0" aria-valuemax="100">'.$percentage.'%</div></div></td><td class="text-center bg-success bg-opacity-25"><strong>'.$stat['present_count'].'</strong></td><td class="text-center bg-warning bg-opacity-25"><strong>'.$stat['late_count'].'</strong></td><td class="text-center bg-danger bg-opacity-25"><strong>'.$stat['absent_count'].'</strong></td></tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header"><h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> <?= $languageService->get('title_class_distribution') ?></h5></div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php
                                $class_stmt = $_database->prepare("
                                    SELECT cl.class_name, COUNT(DISTINCT rc.userID) as player_count
                                    FROM plugins_raidplaner_characters rc
                                    JOIN plugins_raidplaner_classes cl ON rc.class_id = cl.id
                                    GROUP BY rc.class_id
                                    ORDER BY player_count DESC, cl.class_name ASC
                                ");
                                $class_stmt->execute();
                                $class_stats = $class_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                                if (empty($class_stats)) { 
                                    echo '<li class="list-group-item text-muted">' . $languageService->get('msg_no_chars_or_classes') . '</li>'; 
                                } 
                                else {
                                    foreach ($class_stats as $stat) { 
                                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">' . htmlspecialchars($stat['class_name']) . '<span class="badge bg-primary rounded-pill">' . $stat['player_count'] . ' ' . $languageService->get('text_players') . '</span></li>'; 
                                    }
                                }
                                ?>
                            </ul>
                        </div> 
                    </div> 
                </div> 
            </div> 
        </div>
                <div class="tab-pane fade <?= $tab == 'settings' ? 'show active' : '' ?>" id="settings">
                    <? $settings = [];
                        $result = $_database->query("SELECT setting_key, setting_value FROM plugins_raidplaner_settings");
                        while ($row = $result->fetch_assoc()) {
                            $settings[$row['setting_key']] = $row['setting_value'];
                        }

                        $manage_default_roles_checked = isset($settings['manage_default_roles']) && $settings['manage_default_roles'] == '1';
                        ?>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear-fill"></i> <?= $languageService->get('title_general_settings') ?></h5></div>
                        <div class="card-body">
                            <form method="post" action="?site=admin_raidplaner&tab=settings">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="manage_default_roles" 
                                        name="manage_default_roles" value="1" 
                                        <?= $manage_default_roles_checked ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="manage_default_roles">
                                        <?= $languageService->get('form_manage_default_roles') ?>
                                    </label>
                                    <div class="form-text"><?= $languageService->get('form_manage_default_roles_help') ?></div>
                                </div>
                                <button type="submit" name="save_general_settings" class="btn btn-primary mt-3"><?= $languageService->get('btn_save') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0"><i class="bi bi-tags-fill"></i> <?= $languageService->get('title_classes_and_roles') ?></h5></div>
                        <div class="card-body">
                            <?php
                            $classes = $_database->query("SELECT id, class_name FROM plugins_raidplaner_classes ORDER BY class_name")->fetch_all(MYSQLI_ASSOC);
                            $roles = $_database->query("SELECT id, role_name FROM plugins_raidplaner_roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
                            
                            echo '<div class="row">
                                    <div class="col-md-6">
                                        <h6>' . $languageService->get('subtitle_classes') . '</h6>
                                        <ul class="list-group mb-3">';
                            foreach($classes as $class) { 
                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.htmlspecialchars($class['class_name']).'<button class="btn btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-type="class" data-id="'.$class['id'].'">&times;</button></li>'; 
                            }
                            echo '      </ul>
                                        <form method="post" action="?site=admin_raidplaner&tab=settings">
                                            <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                                            <div class="input-group">
                                                <input type="text" name="class_name" class="form-control" placeholder="' . $languageService->get('placeholder_new_class') . '" required>
                                                <button class="btn btn-success" type="submit" name="add_class">+</button>
                                            </div>
                                        </form>
                                    </div>
                                <div class="col-md-6">
                                    <h6>' . $languageService->get('subtitle_roles') . '</h6>
                                    <ul class="list-group mb-3">';

                            $roles = $_database->query("
                                SELECT id, role_name, COALESCE(icon, '') AS icon
                                FROM plugins_raidplaner_roles
                                ORDER BY role_name
                            ")->fetch_all(MYSQLI_ASSOC);

                            foreach ($roles as $role) {
                                $rid   = (int)$role["id"];
                                $rname = htmlspecialchars($role["role_name"] ?? "", ENT_QUOTES, "UTF-8");
                                $ricon = htmlspecialchars($role["icon"] ?? "", ENT_QUOTES, "UTF-8");

                                echo '
                                <li class="list-group-item">
                                    <!-- Mini-Form NUR für diese eine Rolle: auto-submit bei Änderung -->
                                    <form method="post" action="?site=admin_raidplaner&tab=settings" class="m-0 role-form" data-role-id="'.$rid.'">
                                        <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                                        <input type="hidden" name="save_roles" value="1">
                                        <div class="input-group rolecombo">
                                            <!-- Icon-Preview -->
                                            <span class="input-group-text role-icon-preview" title="Icon-Vorschau">
                                                <i class="bi '.($ricon !== "" ? $ricon : "bi-question-circle").'"></i>
                                            </span>

                                            <input type="text"
                                                class="form-control role-name"
                                                name="roles['.$rid.'][name]"
                                                value="'.$rname.'"
                                                placeholder="z. B. Healer">

                                            <input type="hidden"
                                                class="role-icon-input"
                                                name="roles['.$rid.'][icon]"
                                                value="'.$ricon.'">

                                            <button class="btn btn-secondary dropdown-toggle role-icon-picker"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Icon
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end p-1"
                                                style="width:22rem;max-height:260px;overflow:auto">
                                                <li class="mb-2">
                                                    <input type="text" class="form-control form-control-sm icon-filter"
                                                        placeholder="Icon suchen (z. B. shield)">
                                                </li>
                                                <li>
                                                    <div class="icon-grid d-flex flex-wrap gap-1"></div>
                                                </li>
                                            </ul>

                                            <button type="button"
                                                class="btn btn-danger btn-delete ms-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal"
                                                data-type="role"
                                                data-id="' . $rid . '"
                                                title="' . $languageService->get('btn_delete') . '">
                                                &times;
                                            </button>
                                        </div>
                                    </form>
                                </li>';
                            }

                            echo '
                                    </ul>
                                    <form method="post" action="?site=admin_raidplaner&tab=settings">
                                        <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'] .'">
                                        <div class="input-group">
                                            <input type="text" name="role_name" class="form-control" placeholder="' . $languageService->get('placeholder_new_role') . '" required>
                                            <button class="btn btn-success" type="submit" name="add_role">+</button>
                                        </div>
                                    </form>
                                </div>';
                            ?>
                        </div>
                    </div> 
                </div> 
            </div> 
        </div>
</div> <?php }
?>
<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= $languageService->get('modal_delete_title') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="deleteModalBody">
        <?= $languageService->get('modal_delete_body') ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $languageService->get('btn_cancel') ?></button>
        <form method="post" id="deleteForm" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="delete" id="deleteType" value="">
          <input type="hidden" name="delete_id" id="deleteId" value="">
          <button type="submit" class="btn btn-danger"><?= $languageService->get('btn_confirm_delete') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  // Sprachstrings aus PHP
  const raidplanerLang = {
    modal_delete_body: '<?= addslashes($languageService->get("modal_delete_body")) ?>',
    loading_chars: '<?= addslashes($languageService->get("js_loading_chars")) ?>',
    select_player_first: '<?= addslashes($languageService->get("form_select_player_first")) ?>',
    select_character: '<?= addslashes($languageService->get("form_select_character")) ?>',
    no_chars_found: '<?= addslashes($languageService->get("js_no_chars_found")) ?>',
    error_prefix: '<?= addslashes($languageService->get("js_error_prefix")) ?>',
    unknown_error: '<?= addslashes($languageService->get("js_unknown_error")) ?>',
    error_saving_all: '<?= addslashes($languageService->get("js_error_saving_all")) ?>'
  };
  const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';
  document.addEventListener('DOMContentLoaded', function () {
    // ---- Konstanten / Utilities
    const esc = (s) => String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    window.esc = esc;
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

    // ---- 1) Tooltips (falls Bootstrap vorhanden)
    if (window.bootstrap && bootstrap.Tooltip) {
      Array.prototype.slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(function (el) {
        new bootstrap.Tooltip(el);
      });
    }

    // ---- 2) Delete-Modal (zentral, wiederverwendbar)
(function initDeleteModal() {
  const deleteModal = document.getElementById('deleteModal');
  if (!deleteModal) return;

  const deleteForm   = deleteModal.querySelector('#deleteForm');
  const deleteTypeEl = deleteModal.querySelector('#deleteType');
  const deleteIdEl   = deleteModal.querySelector('#deleteId');
  const bodyEl       = deleteModal.querySelector('#deleteModalBody');

  // Werte setzen, wenn das Modal geöffnet wird
  deleteModal.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    if (!trigger) return;

    const type = trigger.getAttribute('data-type') || '';
    const id   = trigger.getAttribute('data-id')   || '';

    // Hidden-Felder setzen
    if (deleteTypeEl) deleteTypeEl.value = type;
    if (deleteIdEl)   deleteIdEl.value   = id;

    // Basistext im Body wiederherstellen
    if (bodyEl) {
      bodyEl.innerHTML = raidplanerLang.modal_delete_body || 'Soll das Element wirklich gelöscht werden? Dieser Vorgang kann nicht rückgängig gemacht werden.';
    }

    // Vorherige Options-Gruppe überall entfernen
    if (deleteForm) {
      const oldInForm = deleteForm.querySelector('#deleteModeGroup');
      if (oldInForm) oldInForm.remove();
    }
    const oldInBody = deleteModal.querySelector('#deleteModeGroup');
    if (oldInBody) oldInBody.remove();

    // --- Zusätzliche Optionen nur für "Raid" ---
    if (type === 'raid' && bodyEl && deleteForm) {
      // Sicherstellen, dass das Formular eine ID hat (für form-Attribut)
      if (!deleteForm.id) deleteForm.id = 'deleteForm';
      const formId = deleteForm.id;

      // Zentrierter Wrapper im Modal-Body
      const grpWrap = document.createElement('div');
      grpWrap.id = 'deleteModeGroup';
      grpWrap.className = 'd-flex justify-content-center my-3';

       grpWrap.innerHTML = `
        <div class="alert alert-warning mb-0 shadow-sm" style="max-width:520px;width:100%;">
          <div class="fw-semibold mb-2">Optionen für das Löschen:</div>
          <div class="small text-muted mb-3">
            Wenn dieser Raid gelöscht wird, bestehen keine Möglichkeiten mehr,
            Loot-Vergaben oder Änderungen für diesen Raid vorzunehmen.
            Bitte prüfe alle Loot-Zuweisungen, bevor du fortfährst.
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="delete_mode" id="del_soft" value="soft" form="${formId}">
            <label class="form-check-label" for="del_soft">Nur Raid löschen (Loot behalten)</label>
          </div>
          <div class="form-check mt-1">
            <input class="form-check-input" type="radio" name="delete_mode" id="del_purge" value="purge" checked form="${formId}">
            <label class="form-check-label text-danger" for="del_purge">Raid &amp; alle Itemvergaben löschen (endgültig)</label>
          </div>
        </div>
      `;

      // In den BODY einsetzen
      bodyEl.appendChild(grpWrap);
    }

    // Optional: für Debug sichtbar machen
    deleteModal.dataset.debug = JSON.stringify({ type, id });
  });

  // Guard: Ohne Typ/ID kein Submit
  deleteForm.addEventListener('submit', function (e) {
    const type = (deleteTypeEl.value || '').trim();
    const id   = (deleteIdEl.value || '').trim();

    if (!type || !id) {
      e.preventDefault();
      alert('Konnte die Löschparameter nicht bestimmen. Bitte erneut versuchen.');
      return;
    }

    // Spezialfall: Thumbnail löschen → Einstellungsformular absenden
    if (type === 'thumb') {
        e.preventDefault();

    // Felder holen
    const urlInp = document.getElementById('inp_thumb');
    const fileInp = document.getElementById('inp_thumb_file');
    const delInp = document.getElementById('inp_thumb_delete');

    if (urlInp)  urlInp.value  = '';
    if (fileInp) fileInp.value = '';
    if (delInp)  delInp.value  = '1';

    // Vorschau leeren
    if (typeof showPreview === 'function') {
      showPreview('');
    } else {
      const img = document.getElementById('thumb_preview_img');
      const ph  = document.getElementById('thumb_preview_placeholder');
      if (img) { img.src = ''; img.style.display = 'none'; }
      if (ph)  { ph.style.display = ''; }
    }

    // Modal schließen
    if (window.bootstrap && bootstrap.Modal) {
      const inst = bootstrap.Modal.getInstance(deleteModal) || new bootstrap.Modal(deleteModal);
      inst.hide();
    }

    const settingsForm = delInp ? delInp.closest('form') : document.querySelector('form[action*="admin_raidplaner"][method="post"]');
    if (settingsForm) {
      const saveFlag = document.createElement('input');
      saveFlag.type  = 'hidden';
      saveFlag.name  = 'save_discord_settings';
      saveFlag.value = '1';
      settingsForm.appendChild(saveFlag);

      // Modal-Felder zurücksetzen
      deleteTypeEl.value = '';
      deleteIdEl.value   = '';

      settingsForm.submit();
    }
    return;
  }
});
    deleteModal.addEventListener('hidden.bs.modal', function () {
    deleteTypeEl.value = '';
    deleteIdEl.value   = '';
    });
        })();
    (function(){
    const ICONS = window.RP_ICONS_V11113 || [
        'bi-heart-fill','bi-crosshair','bi-shield-fill','bi-people-fill','bi-person-fill',
        'bi-bullseye','bi-shield-lock-fill','bi-lightning-charge-fill','bi-clipboard2-pulse',
        'bi-fire','bi-snow','bi-droplet-fill','bi-activity','bi-gem','bi-hammer','bi-stars',
        'bi-rocket-fill','bi-flag-fill','bi-question-circle'
    ];
    const RE_BI = /^bi-[a-z0-9-]+$/i;
    const sanitizeIcon = v => (v || '').trim().replace(/["'<>\s]+/g,'');

    function setPreview(iEl, cls){
        if (!iEl) return;
        iEl.className = (cls && RE_BI.test(cls)) ? ('bi ' + cls) : 'bi bi-question-circle';
    }
    function iconBtn(cls){
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-light border p-0 d-inline-flex align-items-center justify-content-center';
        b.style.width = '2.4rem';
        b.style.height = '2.4rem';
        b.style.borderRadius = '.5rem';
        b.setAttribute('data-icon', cls);
        b.setAttribute('title', cls);
        b.innerHTML = `<i class="bi ${cls}" style="font-size:1.3rem;"></i>`;
        return b;
    }

    document.querySelectorAll(".role-form").forEach(form=>{
        const group     = form.querySelector(".rolecombo");
        const previewI  = group?.querySelector(".role-icon-preview i");
        const nameInput = group?.querySelector(".role-name");
        const iconInput = group?.querySelector(".role-icon-input");
        const grid      = group?.querySelector(".icon-grid");
        const filter    = group?.querySelector(".icon-filter");
        const clearBtn  = group?.querySelector(".role-clear");

        setPreview(previewI, sanitizeIcon(iconInput?.value || ""));
        nameInput?.addEventListener("change", ()=> form.submit());

        if (grid && !grid.dataset.ready) {
        ICONS.forEach(cls => grid.appendChild(iconBtn(cls)));
        grid.dataset.ready = "1";

        grid.addEventListener("click", ev=>{
            const btn = ev.target.closest("[data-icon]");
            if (!btn) return;
            const cls = btn.getAttribute("data-icon");
            if (iconInput) iconInput.value = cls;
            setPreview(previewI, cls);
            form.submit();
        });

        filter?.addEventListener("input", ()=>{
            const q = (filter.value || "").trim().toLowerCase();
            grid.innerHTML = "";
            ICONS.filter(c => c.includes(q)).forEach(cls => grid.appendChild(iconBtn(cls)));
        });
        }

        clearBtn?.addEventListener("click", ()=>{
        if (nameInput) nameInput.value = "";
        if (iconInput) iconInput.value = "";
        setPreview(previewI, "");
        form.submit();
        });
    });
    })();

    // ---- 3) Spieler → Charaktere Nachladen
    (function initUserCharacters() {
      const userSelect = document.querySelector('select[name="user_id"]');
      if (!userSelect) return;

      userSelect.addEventListener('change', function () {
        const userId = this.value;
        const charSelect = document.querySelector('select[name="character_id"]');
        if (!charSelect) return;

        if (!userId) {
          charSelect.innerHTML = `<option value="">${raidplanerLang.select_player_first}</option>`;
          return;
        }

        charSelect.innerHTML = `<option value="">${raidplanerLang.loading_chars}</option>`;

        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);
        formData.append('ajax', 'get_user_characters');

        fetch('?site=admin_raidplaner', { method: 'POST', body: formData, credentials: 'same-origin' })
          .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
          .then(result => {
            if (result && result.success && Array.isArray(result.characters)) {
              charSelect.innerHTML = `<option value="">${raidplanerLang.select_character}</option>`;
              result.characters.forEach(char => {
                charSelect.innerHTML += `<option value="${char.id}">${esc(char.character_name)}</option>`;
              });
            } else {
              charSelect.innerHTML = `<option value="">${raidplanerLang.no_chars_found}</option>`;
            }
          })
          .catch(() => {
            charSelect.innerHTML = `<option value="">${raidplanerLang.no_chars_found}</option>`;
          });
      });
    })();

    // ---- 4) Vorlagen-Logik (Titel, Setup, Bosse -> Chips/Hidden)
    (async function initTemplateFill() {
      
      const tplSelect = document.getElementById('load-template');
      const raidFields = document.getElementById('raid-fields');
      const appliedTplHidden = document.getElementById('applied_template_id');
      const titleInput = document.querySelector('[name="title"]');
      const descArea = document.querySelector('textarea[name="description"]');
      const durInput = document.querySelector('[name="duration"]');

      // Aktuelle Boss-UI (Chips + Hidden JSON)
      const chips = document.getElementById('raidBossChips');
      const hidBoss = document.getElementById('raid_boss_ids_json');
      const bossSelect = document.getElementById('boss_ids');

      // --- Titel-Helfer: disable + Hidden-Mirror, damit der Wert im POST landet
      function ensureHiddenTitle(value) {
        if (!titleInput) return;
        let hid = document.getElementById('title_mirror');
        if (!hid) {
          hid = document.createElement('input');
          hid.type = 'hidden';
          hid.id = 'title_mirror';
          hid.name = 'title';
          titleInput.insertAdjacentElement('afterend', hid);
        }
        hid.value = value || '';
      }
      function removeHiddenTitle() {
        const hid = document.getElementById('title_mirror');
        if (hid) hid.remove();
      }
      function setTitleDisabled(disabled, mirrorValue) {
        if (!titleInput) return;
        titleInput.disabled = !!disabled;
        if (disabled) {
          ensureHiddenTitle(mirrorValue ?? titleInput.value);
          titleInput.removeAttribute('required');
        } else {
          removeHiddenTitle();
          titleInput.setAttribute('required', 'required');
        }
      }

      function toggleRaidFieldsVisibility() {
        if (!raidFields || !tplSelect) return;
        const show = !!(tplSelect.value && tplSelect.value !== '0');
        raidFields.style.display = show ? '' : 'none';
      }

      function resetSetupFields() {
        document.querySelectorAll('[name^="setup["]').forEach(input => { input.value = 0; });
      }

      function applyBossFilterToSelect(bossIds) {
        if (!bossSelect) return;
        const hasTemplate = Array.isArray(bossIds) && bossIds.length > 0;
        const idSet = new Set((bossIds || []).map(Number));

        Array.from(bossSelect.options).forEach(opt => {
          const id = parseInt(opt.value || '0', 10);
          if (hasTemplate) {
            const show = idSet.has(id);
            opt.style.display = show ? '' : 'none';
            opt.disabled = !show;
            opt.selected = show;
          } else {
            opt.style.display = '';
            opt.disabled = false;
            opt.selected = false;
          }
        });
        bossSelect.disabled = hasTemplate;
      }

      // ------- Boss-Chips/Hidden setzen
      function renderBossChips(ids, byId) {
        if (!chips) return;
        chips.innerHTML = '';
        ids.forEach(id => {
          const name = byId && byId[id] ? byId[id] : ('#' + id);
          const span = document.createElement('span');
          span.className = 'badge rounded-pill bg-secondary d-inline-flex align-items-center me-2 mb-2';
          span.setAttribute('data-id', id);
          span.innerHTML =
            '<span class="me-1">' + (window.esc ? window.esc(name) : String(name)) + '</span>' +
            '<button type="button" class="btn btn-sm btn-light ms-1 chip-remove" aria-label="Entfernen">&times;</button>';
          chips.appendChild(span);
        });
      }

      function setBossSelection(ids) {
        const clean = Array.isArray(ids) ? ids.map(n => parseInt(n, 10)).filter(n => n > 0) : [];
        if (hidBoss) hidBoss.value = JSON.stringify(clean);

        applyBossFilterToSelect(clean);

        if (!chips) return;
        if (clean.length === 0) { chips.innerHTML = ''; return; }

        // Namen nachladen
        const fd = new FormData();
        fd.append('ajax', 'bosses_by_ids');
        fd.append('ids', clean.join(','));
        fd.append('csrf_token', csrfToken);

        fetch('?site=admin_raidplaner', { method: 'POST', credentials: 'same-origin', body: fd })
          .then(r => r.json())
          .then(d => {
            const byId = {};
            (d.items || []).forEach(it => { byId[parseInt(it.id, 10)] = it.boss_name; });
            renderBossChips(clean, byId);
          })
          .catch(() => renderBossChips(clean, null));
      }

      // Helpers
      function num(v, fallback = 0) { const n = parseInt(v, 10); return Number.isFinite(n) ? n : fallback; }
      function str(v, fallback = '') { return (v == null) ? fallback : String(v); }

      let tplReqSeq = 0;

      async function fetchTemplateAndFill(templateId) {
        console.log('🚀 fetchTemplateAndFill aufgerufen mit ID:', templateId);

        const my = ++tplReqSeq;

        const formData = new FormData();
        formData.append('template_id', templateId);
        formData.append('csrf_token', csrfToken);
        formData.append('ajax', 'get_template_data');

        console.log('📤 Sende Request für Template-ID:', templateId);
        
        try {
            const res = await fetch('?site=admin_raidplaner', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            console.log('📥 Response Status:', res.status);
            console.log('📥 Response OK:', res.ok);
            
            if (!res.ok) {
                console.error('❌ HTTP Error:', res.status);
                throw new Error('HTTP ' + res.status);
            }

            const result = await res.json();
            console.log('📄 Server Response:', result);
            
            if (my !== tplReqSeq) {
                console.log('⏩ Veraltete Antwort - ignoriert');
                return;
            }

            if (!result || result.success === false) {
                console.error('❌ Server Error:', result?.error);
                return;
            }

            const data = result.data || {};
            const setupArr = Array.isArray(result.setup) ? result.setup
              : (Array.isArray(data.setup) ? data.setup : []);

            console.log('✅ Template-Daten empfangen:');
            console.log('   - Titel:', data.title);
            console.log('   - Beschreibung Länge:', data.description?.length);
            console.log('   - Dauer:', data.duration_minutes);
            console.log('   - Bosse:', data.boss_ids);
            console.log('   - Setup:', setupArr);

            // 1) Titel setzen & ausgrauen (mit Hidden-Mirror)
            if (titleInput) {
              console.log('✏️ Setze Titel:', data.title);
              titleInput.value = str(data.title ?? '');
              setTitleDisabled(true, titleInput.value);
            }
            if (appliedTplHidden) {
              console.log('✏️ Setze appliedTplHidden:', templateId);
              appliedTplHidden.value = String(templateId);
            }

            // 2) Beschreibung
            if (descArea) {
              console.log('✏️ Setze Beschreibung');
              descArea.value = str(data.description ?? '');
            }

            // 3) Dauer
            if (durInput) {
              console.log('✏️ Setze Dauer:', data.duration_minutes);
              durInput.value = num(data.duration_minutes ?? 180, 180);
            }

            // 4) Rollen-Setup setzen
            console.log('✏️ Setze Setup-Felder');
            resetSetupFields();
            setupArr.forEach(item => {
              const rid = num(item.role_id ?? item.id, 0);
              const amt = num(item.needed_count ?? item.value, 0);
              if (rid > 0) {
                const setupInput = document.querySelector(`[name="setup[${rid}]"]`);
                if (setupInput) {
                  console.log(`✏️ Setze Setup[${rid}]:`, amt);
                  setupInput.value = amt;
                }
              }
            });

            // 5) Bosse aus Vorlage anwenden
            const bossIds = Array.isArray(data.boss_ids) ? data.boss_ids
              : Array.isArray(result.boss_ids) ? result.boss_ids
                : [];
            console.log('✏️ Setze Bosse:', bossIds);
            setBossSelection(bossIds);
            
            console.log('✅ Template erfolgreich geladen und Formular befüllt');
            
        } catch (err) {
            console.error('💥 Fehler in fetchTemplateAndFill:', err);
            throw err;
        }
      }

      if (tplSelect) {
        console.log('🔧 INIT: Template-Select gefunden, registriere Event-Listener');
        
        tplSelect.addEventListener('change', async function () {
          console.log('🔄 Template-Select geändert zu:', this.value);
          toggleRaidFieldsVisibility();

          const templateId = this.value;
          if (!templateId || templateId === '0') {
            console.log('🔧 Vorlage abgewählt - aktiviere Titel');
            // Vorlage abgewählt → Titel wieder aktivieren, appliedTpl leeren
            setTitleDisabled(false);
            if (appliedTplHidden) appliedTplHidden.value = '';
            return;
          }

          try {
            console.log('🚀 Lade Vorlage...');
            await fetchTemplateAndFill(templateId);
            console.log('✅ Vorlage erfolgreich geladen');
          } catch (err) {
            console.error('❌ Fehler beim Laden der Vorlage:', err);
          }
        });

        console.log('🔧 INIT: Initialisiere Sichtbarkeit');
        toggleRaidFieldsVisibility();

        // Edit-Fall: falls bereits Vorlage gesetzt
        const appliedTpl = document.querySelector('#applied_template_id');
        console.log('🔍 INIT: Prüfe vorhandene Vorlage:', appliedTpl?.value);
        
        if (appliedTpl && appliedTpl.value && appliedTpl.value !== '0') {
          try {
            console.log('🔄 INIT: Lade vorhandene Vorlage:', appliedTpl.value);
            await fetchTemplateAndFill(appliedTpl.value);
            console.log('✅ INIT: Vorhandene Vorlage geladen');
          } catch (e) {
            console.warn('[TPL PREFILL] failed:', e);
          }
        }
      } else {
        console.log('❌ INIT: Template-Select nicht gefunden!');
      }

      // Submit-Guard: Ohne Vorlage muss Titel gesetzt sein
      const raidForm = document.querySelector('form');
      if (raidForm && titleInput && tplSelect) {
        raidForm.addEventListener('submit', function (e) {
          const tid = parseInt(tplSelect ? (tplSelect.value || '0') : '0', 10);
          if (tid <= 0 && !titleInput.disabled) {
            if (!titleInput.value.trim()) {
              e.preventDefault();
              alert('Bitte einen Titel angeben (oder eine Vorlage wählen).');
              titleInput.focus();
            }
          }
        });
      }
    })();

    // ---- 5) BiS-Handling
    (function initBIS() {
      document.querySelectorAll('.bis-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
          const itemId = this.dataset.itemId;
          const classId = this.value;

          if (!itemId || !classId) {
            console.warn('Fehlende data-Attribute bei .bis-toggle', this);
            return;
          }

          const formData = new FormData();
          formData.append('item_id', itemId);
          formData.append('class_id', classId);
          formData.append('is_bis', this.checked);
          formData.append('csrf_token', csrfToken);
          formData.append('ajax', 'toggle_bis');

          fetch('?site=admin_raidplaner', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
            .then(data => {
              if (!data || !data.success) {
                alert(raidplanerLang.error_prefix + (data?.error || raidplanerLang.unknown_error));
                this.checked = !this.checked;
              }
            })
            .catch(err => {
              console.error('Fehler:', err);
              this.checked = !this.checked; 
            });
        });
      });

      const toggleAll = document.querySelector('.bis-toggle-all');
      if (toggleAll) {
        toggleAll.addEventListener('change', function () {
          const isChecked = this.checked;
          const checkboxes = document.querySelectorAll('.bis-toggle');

          checkboxes.forEach(checkbox => {
            if (checkbox.disabled) return;
            if (checkbox.checked !== isChecked) {
              checkbox.checked = isChecked;
              checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
          });
        });
      }
    })();

    (function initInlineAttendanceEdit() {
      function isAttendanceSelect(sel) {
        if (!sel) return false;
        const name = sel.getAttribute('name') || '';
        return /^signup_(char|role)\[\d+\]$/.test(name);
      }
      function optionText(sel) {
        const opt = sel.options[sel.selectedIndex];
        return opt ? opt.text : '';
      }
      function closeSelect(sel, updateSpanText) {
        const span = document.querySelector('.editable-text[data-target="' + sel.id + '"]');
        if (!span) return;
        if (updateSpanText) {
          span.textContent = optionText(sel);
        } else if (sel.dataset._origValue != null) {
          sel.value = sel.dataset._origValue;
        }
        sel.classList.add('d-none');
        span.classList.remove('d-none');
      }

      // Klick auf den Text → Select anzeigen
      document.addEventListener('click', function (e) {
        const span = e.target.closest('.editable-text');
        if (!span) return;

        const selectId = span.getAttribute('data-target');
        if (!selectId) return;

        const sel = document.getElementById(selectId);
        if (!isAttendanceSelect(sel)) return;

        sel.dataset._origValue = sel.value;

        span.classList.add('d-none');
        sel.classList.remove('d-none');
        sel.focus();
      });

      // Änderungen übernehmen (nur UI – gespeichert wird mit dem vorhandenen "Änderungen speichern")
      document.addEventListener('change', function (e) {
        const sel = e.target.closest('select.form-select-sm');
        if (!isAttendanceSelect(sel)) return;
        closeSelect(sel, true);
      });

      document.addEventListener('keydown', function (e) {
        const sel = e.target.closest('select.form-select-sm');
        if (!isAttendanceSelect(sel)) return;
        if (e.key === 'Escape') {
          e.preventDefault();
          closeSelect(sel, false);
        } else if (e.key === 'Enter') {
          e.preventDefault();
          closeSelect(sel, true);
        }
      });
      document.addEventListener('blur', function (e) {
        const sel = e.target.closest('select.form-select-sm');
        if (!isAttendanceSelect(sel)) return;
        closeSelect(sel, true);
      }, true);
    })();

    // Optionale globale Fehlerlogs im Browser
    window.addEventListener('error', (e) => {
      console.error('[GLOBAL ERROR]', e.message, e.lineno, e.colno, e.filename);
    });
  });
})();
</script>