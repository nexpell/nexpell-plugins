<?php
// Alert-Messages via Query-String (zentral nutzbar, idempotent)
if (!function_exists('rp_qs_alert')) {
    function rp_qs_alert(): void {
        static $alreadyPrinted = false;
        if ($alreadyPrinted) return;

        $msg = $_GET['msg'] ?? '';
        $msg = is_string($msg) ? $msg : '';
        if ($msg === '') return;

        $map = [
            'saved'   => ['success', $GLOBALS['languageService']->get('msg_saved')       ?? 'Gespeichert.'],
            'updated' => ['success', $GLOBALS['languageService']->get('msg_updated')     ?? 'Änderungen gespeichert.'],
            'deleted' => ['success', $GLOBALS['languageService']->get('msg_deleted')     ?? 'Gelöscht.'],
            'exists'  => ['warning', $GLOBALS['languageService']->get('msg_boss_exists') ?? 'Boss mit diesem Namen existiert bereits.'],
            'error'   => ['danger',  $GLOBALS['languageService']->get('msg_error')       ?? 'Es ist ein Fehler aufgetreten.'],
        ];
        if (!isset($map[$msg])) return;

        [$type, $text] = $map[$msg];

        echo '<div class="alert alert-'.htmlspecialchars($type, ENT_QUOTES, 'UTF-8').' alert-dismissible fade show" role="alert">'
           . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
           . '</div>';

        $alreadyPrinted = true;
    }
}

// Mini-Redirect-Helfer: hängt ?msg=... an und leitet weiter
if (!function_exists('rp_redirect_with_msg')) {
    function rp_redirect_with_msg(string $url, string $msgKey, array $extra = []): void {
        $parts = parse_url($url);
        $path  = ($parts['scheme'] ?? '') ? ($parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '')) : ($parts['path'] ?? $url);

        $qs = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $qs);
        $qs = array_merge($qs, $extra, ['msg' => $msgKey]);

        header('Location: '.$path.'?'.http_build_query($qs));
        exit;
    }
}
/**
 * Sprachabhängige Datums-/Zeitformatierung.
 */
function rp_format_dt($timestamp, $format) {
    global $languageService;

    $isEnglish = false;
    if (is_object($languageService)) {
        $rp_title = (string)$languageService->get('raidplaner_title');
        $isEnglish = (stripos($rp_title, 'raid planner') !== false);
    }

    $fmt = $format;
    if ($isEnglish && strpos($fmt, '\\T') === false) {
        $fmt = str_replace('H:i \\U\\h\\r', 'g:i A', $fmt);
        $fmt = str_replace('H:i', 'g:i A', $fmt);
    }

    return date($fmt, $timestamp);
}
function has_role(int $userID, string $roleName): bool {
    $userRoles = nexpell\RoleManager::getUserRoles($userID);
    foreach ($userRoles as $userRole) {
        if (strcasecmp($userRole, $roleName) === 0) {
            return true;
        }
    }
    return false;
}

function send_discord_notification(string $webhook_url, array $embeds, ?string $content = null) {
    if (empty($webhook_url)) {
        return false;
    }
    
    $data = [
        'username' => 'Raidplaner',
        'embeds' => $embeds,
    ];

    if (!empty($content)) {
        $data['content'] = $content;
    }
    $ch = curl_init($webhook_url . '?wait=true'); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response_body = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode >= 200 && $httpcode < 300) {
        $response_data = json_decode($response_body, true);
        return $response_data['id'] ?? false;
    }
    
    return false;
}

function delete_discord_message(string $webhook_url, string $message_id) {
    if (empty($webhook_url) || empty($message_id)) {
        return false;
    }
    $url = rtrim($webhook_url, '/') . '/messages/' . $message_id;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpcode >= 200 && $httpcode < 300);
}
function getRaidSignupsFormatted(int $event_id, mysqli $_database): array
{
    $stmt_signups = $_database->prepare("
        SELECT 
            s.status, s.comment, s.role_id,
            c.character_name, cl.class_name
        FROM plugins_raidplaner_signups s
        LEFT JOIN plugins_raidplaner_characters c ON s.character_id = c.id
        LEFT JOIN plugins_raidplaner_classes cl ON c.class_id = cl.id
        WHERE s.event_id = ?
        ORDER BY s.status, c.character_name
    ");
    $stmt_signups->bind_param("i", $event_id);
    $stmt_signups->execute();
    $result_signups = $stmt_signups->get_result();

    $participants = [];
    $benched = [];
    $declined = [];

    while ($signup = $result_signups->fetch_assoc()) {
        $participant_html = '<li class="list-group-item">'
            . htmlspecialchars($signup['character_name'] ?? 'Unbekannt')
            . ' (' . htmlspecialchars($signup['class_name'] ?? 'Keine Klasse') . ')';

        if (!empty($signup['comment'])) {
            $participant_html .= ' <i class="bi bi-chat-left-dots-fill ms-1" 
                data-bs-toggle="tooltip" data-bs-placement="top" 
                title="' . htmlspecialchars($signup['comment'], ENT_QUOTES, 'UTF-8') . '"></i>';
        }
        $participant_html .= '</li>';

        switch (strtolower(trim($signup['status']))) {
            case 'angemeldet':
                $participants[$signup['role_id']][] = $participant_html;
                break;
            case 'ersatzbank':
                $benched[] = $participant_html;
                break;
            case 'abgemeldet':
                $declined[] = $participant_html;
                break;
        }
    }
    $stmt_signups->close();

    return [
        'participants' => $participants,
        'benched' => $benched,
        'declined' => $declined
    ];
}
class ItemManager {
    private $_database;

    public function __construct(mysqli $database) {
        $this->_database = $database;
    }

    public function getAllItemsGroupedByClass() {
        $sql = "
            SELECT i.id, i.item_name, i.slot, b.boss_name, c.class_name
            FROM plugins_raidplaner_items i
            LEFT JOIN plugins_raidplaner_bosses b ON b.id = i.boss_id
            LEFT JOIN plugins_raidplaner_bis_list bl ON bl.item_id = i.id
            LEFT JOIN plugins_raidplaner_classes c ON c.id = bl.class_id
            ORDER BY i.slot, i.item_name, c.class_name";
        
        $items_raw = $this->_database->query($sql)->fetch_all(MYSQLI_ASSOC);

        $items_grouped = [];
        foreach($items_raw as $row) {
            $id = $row['id'];
            if(!isset($items_grouped[$id])) {
                $items_grouped[$id] = [
                    'item_name' => $row['item_name'], 
                    'slot' => $row['slot'], 
                    'boss_name' => $row['boss_name'], 
                    'classes' => [] 
                ];
            }
            if($row['class_name']) { 
                $items_grouped[$id]['classes'][] = $row['class_name']; 
            }
        }
        return $items_grouped;
    }

    public function saveItem(array $data) {
    $item_id   = (int)($data['id'] ?? 0);
    $item_name = trim((string)($data['item_name'] ?? ''));
    $slot      = trim((string)($data['slot'] ?? ''));
    $source    = trim((string)($data['source'] ?? ''));
    $boss_id   = (int)($data['boss_id'] ?? 0);
    $boss_id   = $boss_id > 0 ? $boss_id : null;

    $bis_classes = [];
    if (!empty($data['bis_classes']) && is_array($data['bis_classes'])) {
        foreach ($data['bis_classes'] as $cid) {
            $cid = (int)$cid;
            if ($cid > 0) $bis_classes[] = $cid;
        }
        $bis_classes = array_values(array_unique($bis_classes));
    }

    if ($item_name === '') {
        return false;
    }

    $this->_database->begin_transaction();
    try {
        if ($item_id > 0) {
            // UPDATE
            $stmt = $this->_database->prepare("UPDATE plugins_raidplaner_items SET item_name = ?, slot = ?, source = ?, boss_id = ? WHERE id = ?");
            $stmt->bind_param("sssii", $item_name, $slot, $source, $boss_id, $item_id);
            if (!$stmt->execute()) {
                throw new RuntimeException('Update failed: ' . $stmt->error);
            }
            $stmt->close();

            // BiS-Reset + Neu setzen
            $del = $this->_database->prepare("DELETE FROM plugins_raidplaner_bis_list WHERE item_id = ?");
            $del->bind_param("i", $item_id);
            if (!$del->execute()) {
                throw new RuntimeException('Delete bis_list failed: ' . $del->error);
            }
            $del->close();

            if (!empty($bis_classes)) {
                $ins = $this->_database->prepare("INSERT IGNORE INTO plugins_raidplaner_bis_list (item_id, class_id) VALUES (?, ?)");
                foreach ($bis_classes as $cid) {
                    $ins->bind_param("ii", $item_id, $cid);
                    if (!$ins->execute()) {
                        throw new RuntimeException('Insert bis_list failed: ' . $ins->error);
                    }
                }
                $ins->close();
            }
        } else {
            // INSERT
            $stmt = $this->_database->prepare("INSERT INTO plugins_raidplaner_items (item_name, slot, source, boss_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $item_name, $slot, $source, $boss_id);
            if (!$stmt->execute()) {
                throw new RuntimeException('Insert failed: ' . $stmt->error);
            }
            $newId = $stmt->insert_id;
            $stmt->close();

            if (!empty($bis_classes)) {
                $ins = $this->_database->prepare("INSERT IGNORE INTO plugins_raidplaner_bis_list (item_id, class_id) VALUES (?, ?)");
                foreach ($bis_classes as $cid) {
                    $ins->bind_param("ii", $newId, $cid);
                    if (!$ins->execute()) {
                        throw new RuntimeException('Insert bis_list failed: ' . $ins->error);
                    }
                }
                $ins->close();
            }
        }

        $this->_database->commit();
        return true;
    } catch (Throwable $ex) {
        $this->_database->rollback();
        return false;
    }
}

    public function deleteItem(int $item_id) {
    if ($item_id <= 0) return false;

    $this->_database->begin_transaction();
    try {
        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_bis_list WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
        $stmt->close();

        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_character_gear WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
        $stmt->close();

        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);
        $stmt->close();

        $this->_database->commit();
        return true;
    } catch (Throwable $e) {
        $this->_database->rollback();
        return false;
    }
}
}
class BossManager
{
    /** @var mysqli */
    private $db;

    /** @var string */
    private $lastError = '';

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function setLastError(string $code): void
    {
        $this->lastError = $code;
    }

    // -------------------------
    // Helpers
    // -------------------------
    private function tableExists(string $table): bool
    {
        $res = $this->db->query("SHOW TABLES LIKE '".$this->db->real_escape_string($table)."'");
        return ($res && $res->num_rows > 0);
    }

    private function columns(string $table): array
    {
        $cols = [];
        if (!$this->tableExists($table)) return $cols;
        if ($res = $this->db->query("SHOW COLUMNS FROM {$table}")) {
            while ($c = $res->fetch_assoc()) {
                $cols[strtolower($c['Field'])] = true;
            }
        }
        return $cols;
    }

    // -------------------------
    // Lesen
    // -------------------------
    public function getAll(): array
    {
        $out = [];
        if (!$this->tableExists('plugins_raidplaner_bosses')) return $out;

        $hasDesc = false;
        $cols = $this->columns('plugins_raidplaner_bosses');
        if (isset($cols['description'])) $hasDesc = true;

        $sql = $hasDesc
            ? "SELECT id, boss_name, description FROM plugins_raidplaner_bosses ORDER BY boss_name, id"
            : "SELECT id, boss_name FROM plugins_raidplaner_bosses ORDER BY boss_name, id";

        if ($res = $this->db->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $row['id']        = (int)$row['id'];
                $row['boss_name'] = stripslashes((string)$row['boss_name']);
                if (isset($row['description'])) {
                    $row['description'] = stripslashes((string)$row['description']);
                }
                $out[] = $row;
            }
            $res->free();
        }
        return $out;
    }

    public function getById(int $id): array
    {
        $id = (int)$id;
        if ($id <= 0 || !$this->tableExists('plugins_raidplaner_bosses')) return [];

        $hasDesc = false;
        $cols = $this->columns('plugins_raidplaner_bosses');
        if (isset($cols['description'])) $hasDesc = true;

        if ($hasDesc) {
            $stmt = $this->db->prepare("SELECT id, boss_name, description FROM plugins_raidplaner_bosses WHERE id=?");
            $stmt->bind_param("i", $id);
        } else {
            $stmt = $this->db->prepare("SELECT id, boss_name FROM plugins_raidplaner_bosses WHERE id=?");
            $stmt->bind_param("i", $id);
        }
        if (!$stmt) return [];

        $data = [];
        if ($stmt->execute()) {
            if (method_exists($stmt,'get_result')) {
                if ($r = $stmt->get_result()) {
                    $data = (array)$r->fetch_assoc();
                }
            } else {
                $stmt->store_result();
                if ($hasDesc) {
                    $i=0; $n=''; $d='';
                    $stmt->bind_result($i,$n,$d);
                    if ($stmt->fetch()) $data = ['id'=>$i,'boss_name'=>$n,'description'=>$d];
                } else {
                    $i=0; $n='';
                    $stmt->bind_result($i,$n);
                    if ($stmt->fetch()) $data = ['id'=>$i,'boss_name'=>$n];
                }
            }
        }
        $stmt->close();

        if (!empty($data)) {
            $data['id']        = (int)$data['id'];
            $data['boss_name'] = stripslashes((string)$data['boss_name']);
            if (isset($data['description'])) {
                $data['description'] = stripslashes((string)$data['description']);
            }
        }
        return $data ?: [];
    }

    // -------------------------
    // Speichern
    // -------------------------
    /**
     * Speichert (INSERT/UPDATE) einen Boss nur in plugins_raidplaner_bosses.
     * - Verhindert Duplikate
     */
   public function save(array $data): int
    {
        // Tabelle vorhanden?
        $resTbl = $this->db->query("SHOW TABLES LIKE 'plugins_raidplaner_bosses'");
        if (!$resTbl || $resTbl->num_rows === 0) {
            $this->setLastError('table_missing');
            return 0;
        }

        // Spalten ermitteln: bevorzugt 'tactics', Fallback 'description'
        $cols = [];
        if ($resCols = $this->db->query("SHOW COLUMNS FROM plugins_raidplaner_bosses")) {
            while ($c = $resCols->fetch_assoc()) {
                $cols[strtolower($c['Field'])] = true;
            }
        }
        $hasTactics = isset($cols['tactics']);
        $hasDesc    = isset($cols['description']);

        // Eingaben
        $id        = (int)($data['boss_id'] ?? $data['id'] ?? 0);
        $boss_name = trim((string)($data['boss_name'] ?? ''));
        $tactics_in = (string)($data['tactics'] ?? ($data['description'] ?? ''));
        $tactics = str_replace(["\r\n", "\r"], "\n", $tactics_in);
        $tactics = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $tactics);

        if ($boss_name === '') {
            $this->setLastError('invalid_name');
            return 0;
        }

        // Duplikat-Check
        if ($st = $this->db->prepare("SELECT id FROM plugins_raidplaner_bosses WHERE LOWER(boss_name) = LOWER(?) AND id <> ? LIMIT 1")) {
            $st->bind_param("si", $boss_name, $id);
            if ($st->execute()) {
                if (method_exists($st,'get_result')) {
                    $dupRes = $st->get_result();
                    if ($dupRes && $dupRes->num_rows > 0) {
                        $st->close();
                        $this->setLastError('duplicate_name');
                        return 0;
                    }
                } else {
                    $st->store_result();
                    if ($st->num_rows > 0) {
                        $st->close();
                        $this->setLastError('duplicate_name');
                        return 0;
                    }
                }
            }
            $st->close();
        } else {
            $nameEsc = $this->db->real_escape_string($boss_name);
            $dupRes = $this->db->query("SELECT id FROM plugins_raidplaner_bosses WHERE LOWER(boss_name) = LOWER('{$nameEsc}') AND id <> {$id} LIMIT 1");
            if ($dupRes && $dupRes->num_rows > 0) {
                $this->setLastError('duplicate_name');
                return 0;
            }
        }

        // UPDATE / INSERT mit TACTICS-Unterstützung
        if ($id > 0) {
            if ($hasTactics) {
                $stmt = $this->db->prepare("UPDATE plugins_raidplaner_bosses SET boss_name = ?, tactics = ? WHERE id = ?");
                if (!$stmt) { $this->setLastError('update_prepare_failed'); return 0; }
                $stmt->bind_param("ssi", $boss_name, $tactics, $id);
            } elseif ($hasDesc) {
                $stmt = $this->db->prepare("UPDATE plugins_raidplaner_bosses SET boss_name = ?, description = ? WHERE id = ?");
                if (!$stmt) { $this->setLastError('update_prepare_failed'); return 0; }
                $stmt->bind_param("ssi", $boss_name, $tactics, $id);
            } else {
                $stmt = $this->db->prepare("UPDATE plugins_raidplaner_bosses SET boss_name = ? WHERE id = ?");
                if (!$stmt) { $this->setLastError('update_prepare_failed'); return 0; }
                $stmt->bind_param("si", $boss_name, $id);
            }

            if (!$stmt->execute()) { $stmt->close(); $this->setLastError('update_execute_failed'); return 0; }
            $stmt->close();
            return $id;

        } else {
            if ($hasTactics) {
                $stmt = $this->db->prepare("INSERT INTO plugins_raidplaner_bosses (boss_name, tactics) VALUES (?, ?)");
                if (!$stmt) { $this->setLastError('update_prepare_failed'); return 0; }
                $stmt->bind_param("ss", $boss_name, $tactics);
            } elseif ($hasDesc) {
                $stmt = $this->db->prepare("INSERT INTO plugins_raidplaner_bosses (boss_name, description) VALUES (?, ?)");
                if (!$stmt) { $this->setLastError('update_prepare_failed'); return 0; }
                $stmt->bind_param("ss", $boss_name, $tactics);
            } else {
                $stmt = $this->db->prepare("INSERT INTO plugins_raidplaner_bosses (boss_name) VALUES (?)");
                if (!$stmt) { $this->setLastError('update_prepare_failed'); return 0; }
                $stmt->bind_param("s", $boss_name);
            }

            if (!$stmt->execute()) { $stmt->close(); $this->setLastError('update_execute_failed'); return 0; }
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            if ($newId > 0) {
                $this->setLastError('create_success');
                return $newId;
            }
            $this->setLastError('create_failed');
            return 0;
        }
    }
    public function delete(int $id): bool
    {
        $id = (int)$id;
        if ($id <= 0 || !$this->tableExists('plugins_raidplaner_bosses')) return false;

        if ($st = $this->db->prepare("DELETE FROM plugins_raidplaner_bosses WHERE id=?")) {
            $st->bind_param("i", $id);
            $ok = $st->execute();
            $st->close();
            return $ok;
        }
        return false;
    }
}
// --------------------------------------------------------------
// Boss-Mapping zentral lesen: Event → Boss-IDs / Boss-Objekte
// --------------------------------------------------------------
class BossLookup
{
    private mysqli $db;
    public function __construct(mysqli $db) { $this->db = $db; }

    /** Liefert Boss-IDs für ein Event – berücksichtigt Vorlagen & Mapping-Tabellen */
    public function getBossIdsForEvent(int $event_id): array
    {
        if ($event_id <= 0) return [];

        // 1) Wenn Event eine Vorlage hat → Bosse direkt aus der Vorlage
        $tplId = $this->getTemplateIdForEvent($event_id);
        if ($tplId > 0) {
            if (!$this->tableExists('plugins_raidplaner_template_bosses')) return [];
            $ids = [];
            if ($st = $this->db->prepare("SELECT boss_id FROM plugins_raidplaner_template_bosses WHERE template_id=? ORDER BY boss_id")) {
                $st->bind_param("i", $tplId);
                if ($st->execute() && ($r = $st->get_result())) {
                    while ($row = $r->fetch_assoc()) {
                        $ids[] = (int)$row['boss_id'];
                    }
                } else {
                    $st->store_result();
                    $bid = 0;
                    $st->bind_result($bid);
                    while ($st->fetch()) { $ids[] = (int)$bid; }
                }
                $st->close();
            }
            return array_values(array_unique(array_filter($ids)));
        }

        // 2) Manuell: Mapping-Tabellen heuristisch durchsuchen
        $mapTables = [
            'plugins_raidplaner_event_bosses',
        ];
        $possibleEventCols = ['event_id', 'raid_id', 'raidID', 'eventID'];

        foreach ($mapTables as $tbl) {
            if (!$this->tableExists($tbl)) continue;
            $cols = $this->columns($tbl);
            if (empty($cols)) continue;

            $useEventCol = null;
            foreach ($possibleEventCols as $c) {
                if (isset($cols[$c])) { $useEventCol = $c; break; }
            }
            if (!$useEventCol || !isset($cols['boss_id'])) continue;

            $ids = [];
            if ($st = $this->db->prepare("SELECT boss_id FROM {$tbl} WHERE {$useEventCol} = ? ORDER BY boss_id")) {
                $st->bind_param("i", $event_id);
                if ($st->execute() && ($r = $st->get_result())) {
                    while ($row = $r->fetch_assoc()) {
                        $ids[] = (int)$row['boss_id'];
                    }
                } else {
                    $st->store_result();
                    $bid = 0; $st->bind_result($bid);
                    while ($st->fetch()) { $ids[] = (int)$bid; }
                }
                $st->close();
            }
            if (!empty($ids)) {
                return array_values(array_unique(array_filter($ids)));
            }
        }

        return [];
    }

    /** Liefert vollständige Boss-Datensätze (id, boss_name, evtl. tactics/description) für ein Event */
    public function getBossesForEvent(int $event_id): array
    {
        $ids = $this->getBossIdsForEvent($event_id);
        if (empty($ids)) return [];

        $nameCol = $this->detectBossNameColumn();

        // Prüfe verfügbare Spalten
        $cols = $this->columns('plugins_raidplaner_bosses');
        $hasTactics = isset($cols['tactics']);
        $hasDescription = isset($cols['description']);

        // Nur vorhandene Spalten selektieren
        $selectCols = "id, {$nameCol} AS boss_name";
        if ($hasTactics)     { $selectCols .= ", tactics"; }
        if ($hasDescription) { $selectCols .= ", description"; }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $out = [];

        $sql = "SELECT {$selectCols} FROM plugins_raidplaner_bosses WHERE id IN ({$placeholders}) ORDER BY boss_name, id";
        if ($st = $this->db->prepare($sql)) {
            $st->bind_param($types, ...$ids);
            if ($st->execute() && ($r = $st->get_result())) {
                while ($row = $r->fetch_assoc()) {
                    $tacticsRaw =
                        $hasTactics     ? (string)($row['tactics'] ?? '') :
                        ($hasDescription ? (string)($row['description'] ?? '') : '');

                    $out[] = [
                        'id'        => (int)$row['id'],
                        'boss_name' => (string)$row['boss_name'],
                        'tactics'   => $tacticsRaw,
                    ];
                }
            } else {
                $st->store_result();
            }
            $st->close();
        }
        return $out;
    }

    private function getTemplateIdForEvent(int $event_id): int
    {
        $tplId = 0;
        if ($st = $this->db->prepare("SELECT template_id FROM plugins_raidplaner_events WHERE id=?")) {
            $st->bind_param("i", $event_id);
            if ($st->execute()) {
                if (method_exists($st, 'get_result') && ($r = $st->get_result())) {
                    $row = $r->fetch_assoc();
                    $tplId = (int)($row['template_id'] ?? 0);
                } else {
                    $st->store_result(); $tmp = 0; $st->bind_result($tmp);
                    if ($st->fetch()) $tplId = (int)$tmp;
                }
            }
            $st->close();
        }
        return $tplId;
    }

    private function tableExists(string $name): bool
    {
        $name = $this->db->real_escape_string($name);
        $res  = $this->db->query("SHOW TABLES LIKE '{$name}'");
        return ($res && $res->num_rows > 0);
    }

    private function columns(string $table): array
    {
        $out = [];
        $table = $this->db->real_escape_string($table);
        if ($res = $this->db->query("SHOW COLUMNS FROM {$table}")) {
            while ($row = $res->fetch_assoc()) {
                $out[$row['Field']] = $row;
            }
        }
        return $out;
    }

    private function detectBossNameColumn(): string
    {
        $col = 'boss_name';
        if ($res = $this->db->query("SHOW COLUMNS FROM plugins_raidplaner_bosses")) {
            $hasTactics = false; $hasDescription = false;
            while ($row = $res->fetch_assoc()) {
                if (strcasecmp($row['Field'], 'tactics') === 0) $hasTactics = true;
                if (strcasecmp($row['Field'], 'description') === 0) $hasDescription = true;
            }
        }
        return $col;
    }
}
// ------------------------------------------------------------
// TemplateManager – serverseitige Logik für Raid-Vorlagen
// ------------------------------------------------------------
class TemplateManager
{
    /** @var mysqli */
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Hilfsfunktion: prüft Existenz einer Tabelle
    // ------------------------------------------------------------------
    private function tableExists(string $table): bool
    {
        $res = $this->db->query("SHOW TABLES LIKE '".$this->db->real_escape_string($table)."'");
        return ($res && $res->num_rows > 0);
    }

    // ------------------------------------------------------------------
    // Hilfsfunktion: Spalten-Liste
    // ------------------------------------------------------------------
    private function columns(string $table): array
    {
        $cols = [];
        if (!$this->tableExists($table)) return $cols;
        if ($res = $this->db->query("SHOW COLUMNS FROM {$table}")) {
            while ($c = $res->fetch_assoc()) {
                $cols[strtolower($c['Field'])] = true;
            }
        }
        return $cols;
    }

    // ------------------------------------------------------------------
    // Vorlagen laden/speichern
    // ------------------------------------------------------------------

    /**
     * Lädt eine Vorlage per ID.
     * Liefert alle relevanten Felder zurück, die in der Tabelle existieren.
     */
    public function getById(int $template_id): array
    {
        $data = [];
        if ($template_id <= 0 || !$this->tableExists('plugins_raidplaner_templates')) return $data;

        $cols = $this->columns('plugins_raidplaner_templates');

        $select = ['id'];
        if (isset($cols['template_name']))   $select[] = 'template_name';
        if (isset($cols['title']))           $select[] = 'title';
        if (isset($cols['duration_minutes']))$select[] = 'duration_minutes';
        if (isset($cols['description']))     $select[] = 'description';

        $sql = "SELECT ".implode(',', $select)." FROM plugins_raidplaner_templates WHERE id=?";
        if (!$stmt = $this->db->prepare($sql)) return $data;

        $stmt->bind_param("i", $template_id);
        if (!$stmt->execute()) { $stmt->close(); return $data; }

        if (method_exists($stmt, 'get_result')) {
            if ($res = $stmt->get_result()) {
                $data = (array)$res->fetch_assoc();
            }
        } else {
            $meta = $stmt->result_metadata();
            if ($meta) {
                $fields = [];
                $row = [];
                while ($field = $meta->fetch_field()) {
                    $row[$field->name] = null;
                    $fields[] = &$row[$field->name];
                }
                $stmt->bind_result(...$fields);
                if ($stmt->fetch()) $data = $row;
            }
        }
        $stmt->close();

        return $data ?: [];
    }

    /**
     * Speichert (INSERT/UPDATE) eine Vorlage.
     */
    public function save(array $data): int
    {
        if (!$this->tableExists('plugins_raidplaner_templates')) return 0;

        $cols = $this->columns('plugins_raidplaner_templates');

        $id = (int)($data['template_id'] ?? $data['id'] ?? 0);

        // Eingänge normalisieren
        $template_name = trim((string)($data['template_name'] ?? ''));
        $title         = trim((string)($data['title'] ?? $template_name));
        $duration      = (int)($data['duration'] ?? $data['duration_minutes'] ?? 0);
        $description   = (string)($data['description'] ?? '');

        // INSERT/UPDATE dynamisch nach vorhandenen Spalten
        if ($id > 0) {
            // UPDATE
            $set = []; $params = []; $types = '';
            if (isset($cols['template_name']))   { $set[] = 'template_name=?';    $params[] = $template_name; $types.='s'; }
            if (isset($cols['title']))           { $set[] = 'title=?';            $params[] = $title;         $types.='s'; }
            if (isset($cols['duration_minutes'])){ $set[] = 'duration_minutes=?'; $params[] = $duration;      $types.='i'; }
            if (isset($cols['description']))     { $set[] = 'description=?';      $params[] = $description;   $types.='s'; }

            if (empty($set)) return $id;

            $sql = "UPDATE plugins_raidplaner_templates SET ".implode(',', $set)." WHERE id=?";
            if (!$stmt = $this->db->prepare($sql)) return 0;

            $types .= 'i';
            $params[] = $id;
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) { $stmt->close(); return 0; }
            $stmt->close();
            return $id;
        } else {
            // INSERT
            $fields = ['id'];
            $vals   = [];
            $params = [];
            $types  = '';

            $insertCols = [];
            $placeholders = [];

            if (isset($cols['template_name']))   { $insertCols[]='template_name';    $placeholders[]='?'; $params[]=$template_name; $types.='s'; }
            if (isset($cols['title']))           { $insertCols[]='title';            $placeholders[]='?'; $params[]=$title;         $types.='s'; }
            if (isset($cols['duration_minutes'])){ $insertCols[]='duration_minutes'; $placeholders[]='?'; $params[]=$duration;      $types.='i'; }
            if (isset($cols['description']))     { $insertCols[]='description';      $placeholders[]='?'; $params[]=$description;   $types.='s'; }

            if (empty($insertCols)) return 0;

            $sql = "INSERT INTO plugins_raidplaner_templates (".implode(',', $insertCols).") VALUES (".implode(',', $placeholders).")";
            if (!$stmt = $this->db->prepare($sql)) return 0;

            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) { $stmt->close(); return 0; }
            $newId = (int)$stmt->insert_id;
            $stmt->close();

            return $newId > 0 ? $newId : 0;
        }
    }

    // ------------------------------------------------------------------
    // Rollen-Setup zu einer Vorlage
    // ------------------------------------------------------------------

    /**
     * Lädt das Setup (Rollenmengen) zu einer Vorlage – falls die Tabelle existiert.
     */
    public function getSetupForTemplate(int $template_id): array
    {
        $map = [];
        if ($template_id <= 0) return $map;

        // 1) Tabelle vorhanden?
        $chkTbl = $this->db->query("SHOW TABLES LIKE 'plugins_raidplaner_template_setup'");
        if (!$chkTbl || $chkTbl->num_rows === 0) return $map;

        // 2) Spalten ermitteln
        $cols = [];
        if ($resCols = $this->db->query("SHOW COLUMNS FROM plugins_raidplaner_template_setup")) {
            while ($c = $resCols->fetch_assoc()) {
                $cols[strtolower($c['Field'])] = true;
            }
        }

        // Pflichtspalten prüfen
        if (!isset($cols['template_id']) || !isset($cols['role_id'])) return $map;

        // 3) Mögliche Namen für die Mengen-Spalte
        $candidates = ['needed_count', 'amount', 'qty', 'quantity', 'num', 'number', 'value', 'count', 'size'];
        $amountCol = null;
        foreach ($candidates as $cand) {
            if (isset($cols[$cand])) { $amountCol = $cand; break; }
        }
        if ($amountCol === null) return $map;

        // 4) Daten laden
        $sql  = "SELECT role_id, `{$amountCol}` AS amount FROM plugins_raidplaner_template_setup WHERE template_id=?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return $map;

        $stmt->bind_param("i", $template_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return $map;
        }

        if (method_exists($stmt, 'get_result')) {
            if ($res = $stmt->get_result()) {
                while ($row = $res->fetch_assoc()) {
                    $map[(int)$row['role_id']] = (int)$row['amount'];
                }
            }
        } else {
            $stmt->store_result();
            $roleId = 0; $amountVal = 0;
            $stmt->bind_result($roleId, $amountVal);
            while ($stmt->fetch()) {
                $map[(int)$roleId] = (int)$amountVal;
            }
        }
        $stmt->close();

        return $map;
    }
    // ------------------------------------------------------------------
    // Boss-Zuordnung zu einer Vorlage
    // ------------------------------------------------------------------

    /**
     * Liefert die Boss-IDs, die einer Vorlage zugeordnet sind.
     * Tabelle: plugins_raidplaner_template_bosses(template_id, boss_id)
     */
    public function getBossesForTemplate(int $template_id): array
    {
        $out = [];
        if ($template_id <= 0) return $out;
        if (!$this->tableExists('plugins_raidplaner_template_bosses')) return $out;

        $sql = "SELECT boss_id FROM plugins_raidplaner_template_bosses WHERE template_id=? ORDER BY boss_id";
        if (!$stmt = $this->db->prepare($sql)) return $out;

        $stmt->bind_param("i", $template_id);
        if (!$stmt->execute()) { $stmt->close(); return $out; }

        if (method_exists($stmt, 'get_result')) {
            if ($res = $stmt->get_result()) {
                while ($row = $res->fetch_assoc()) {
                    $out[] = (int)$row['boss_id'];
                }
            }
        } else {
            $stmt->store_result();
            $bid = 0;
            $stmt->bind_result($bid);
            while ($stmt->fetch()) {
                $out[] = (int)$bid;
            }
        }
        $stmt->close();

        return $out;
    }

    /**
     * Speichert die komplette Boss-Zuordnung (Delete + Insert).
     */
    public function saveTemplateBosses(int $template_id, array $boss_ids): bool
    {
        if ($template_id <= 0) return false;
        if (!$this->tableExists('plugins_raidplaner_template_bosses')) return true;

        // Normalisieren
        $ids = array_values(array_unique(array_map('intval', $boss_ids)));
        $ids = array_filter($ids, fn($v) => $v > 0);

        try {
            $this->db->begin_transaction();

            // Löschen
            if ($del = $this->db->prepare("DELETE FROM plugins_raidplaner_template_bosses WHERE template_id=?")) {
                $del->bind_param("i", $template_id);
                if (!$del->execute()) { $del->close(); $this->db->rollback(); return false; }
                $del->close();
            } else {
                $this->db->rollback(); return false;
            }

            // Nichts einzufügen?
            if (empty($ids)) { $this->db->commit(); return true; }

            // Einfügen (in Chunks)
            $chunk = 300;
            for ($i=0; $i<count($ids); $i+=$chunk) {
                $slice = array_slice($ids, $i, $chunk);
                $place = implode(',', array_fill(0, count($slice), '(?, ?)'));
                $types = str_repeat('ii', count($slice));
                $sql = "INSERT INTO plugins_raidplaner_template_bosses (template_id, boss_id) VALUES {$place}";
                if (!$ins = $this->db->prepare($sql)) { $this->db->rollback(); return false; }

                $params = [];
                foreach ($slice as $bid) {
                    $params[] = $template_id;
                    $params[] = (int)$bid;
                }
                $ins->bind_param($types, ...$params);
                if (!$ins->execute()) { $ins->close(); $this->db->rollback(); return false; }
                $ins->close();
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Boss-Listing für das Modal
    // ------------------------------------------------------------------

    /** Ermittelt die Namensspalte der Boss-Tabelle (boss_name | name | title). */
    public function detectBossNameColumn(): string
    {
        if (!$this->tableExists('plugins_raidplaner_bosses')) return 'boss_name';
        $have = $this->columns('plugins_raidplaner_bosses');
        if (isset($have['boss_name'])) return 'boss_name';
        if (isset($have['name']))      return 'name';
        if (isset($have['title']))     return 'title';
        return 'boss_name';
    }

    /**
     * Liefert paginierte Bosse für das Modal.
     */
    public function listBossesPaginated(string $query, int $page, int $limit): array
    {
        $out = ['success'=>true,'items'=>[], 'total'=>0, 'page'=>max(1,$page), 'has_more'=>false];
        if (!$this->tableExists('plugins_raidplaner_bosses')) return $out;

        $nameCol = $this->detectBossNameColumn();

        $q = trim($query ?? '');
        $page   = max(1, (int)$page);
        $limit  = min(100, max(10, (int)$limit));
        $offset = ($page - 1) * $limit;

        // COUNT
        $total = 0;
        if ($q !== '') {
            $like = "%{$q}%";
            if ($s = $this->db->prepare("SELECT COUNT(*) AS cnt FROM plugins_raidplaner_bosses WHERE {$nameCol} LIKE ?")) {
                $s->bind_param("s", $like);
                if ($s->execute()) {
                    if (method_exists($s,'get_result')) {
                        if ($r = $s->get_result()) $total = (int)($r->fetch_assoc()['cnt'] ?? 0);
                    } else { $s->bind_result($total); $s->fetch(); $total = (int)$total; }
                }
                $s->close();
            }
        } else {
            if ($s = $this->db->prepare("SELECT COUNT(*) AS cnt FROM plugins_raidplaner_bosses")) {
                if ($s->execute()) {
                    if (method_exists($s,'get_result')) {
                        if ($r = $s->get_result()) $total = (int)($r->fetch_assoc()['cnt'] ?? 0);
                    } else { $s->bind_result($total); $s->fetch(); $total = (int)$total; }
                }
                $s->close();
            }
        }

        // ITEMS
        $items = [];
        if ($q !== '') {
            $like = "%{$q}%";
            $sql = "SELECT id, {$nameCol} AS boss_name
                    FROM plugins_raidplaner_bosses
                    WHERE {$nameCol} LIKE ?
                    ORDER BY {$nameCol}, id
                    LIMIT ? OFFSET ?";
            if ($s = $this->db->prepare($sql)) {
                $s->bind_param("sii", $like, $limit, $offset);
                if ($s->execute()) {
                    if (method_exists($s,'get_result')) {
                        if ($r = $s->get_result()) while ($row = $r->fetch_assoc()) $items[] = ['id'=>(int)$row['id'], 'boss_name'=>(string)$row['boss_name']];
                    } else {
                        $s->store_result(); $id=0; $nm='';
                        $s->bind_result($id, $nm);
                        while ($s->fetch()) $items[] = ['id'=>(int)$id, 'boss_name'=>(string)$nm];
                    }
                }
                $s->close();
            }
        } else {
            $sql = "SELECT id, {$nameCol} AS boss_name
                    FROM plugins_raidplaner_bosses
                    ORDER BY {$nameCol}, id
                    LIMIT ? OFFSET ?";
            if ($s = $this->db->prepare($sql)) {
                $s->bind_param("ii", $limit, $offset);
                if ($s->execute()) {
                    if (method_exists($s,'get_result')) {
                        if ($r = $s->get_result()) while ($row = $r->fetch_assoc()) $items[] = ['id'=>(int)$row['id'], 'boss_name'=>(string)$row['boss_name']];
                    } else {
                        $s->store_result(); $id=0; $nm='';
                        $s->bind_result($id, $nm);
                        while ($s->fetch()) $items[] = ['id'=>(int)$id, 'boss_name'=>(string)$nm];
                    }
                }
                $s->close();
            }
        }

        $out['items']    = $items;
        $out['total']    = (int)$total;
        $out['has_more'] = ($offset + $limit < $total);
        return $out;
    }

    public function getBossesByIds(array $ids): array
    {
        $out = ['success'=>true, 'items'=>[]];
        if (!$this->tableExists('plugins_raidplaner_bosses')) return $out;

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) return $out;

        $nameCol = $this->detectBossNameColumn();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql = "SELECT id, {$nameCol} AS boss_name
                FROM plugins_raidplaner_bosses
                WHERE id IN ($placeholders)
                ORDER BY {$nameCol}, id";
        if ($s = $this->db->prepare($sql)) {
            $s->bind_param($types, ...$ids);
            if ($s->execute()) {
                if (method_exists($s,'get_result')) {
                    if ($r = $s->get_result()) {
                        while ($row = $r->fetch_assoc()) {
                            $out['items'][] = ['id'=>(int)$row['id'], 'boss_name'=>(string)$row['boss_name']];
                        }
                    }
                } else {
                    $s->store_result(); $id=0; $nm='';
                    $s->bind_result($id, $nm);
                    while ($s->fetch()) {
                        $out['items'][] = ['id'=>(int)$id, 'boss_name'=>(string)$nm];
                    }
                }
            }
            $s->close();
        }

        return $out;
    }
    public function delete(int $template_id): bool
    {
        if ($template_id <= 0) return false;

        try {
            $this->db->begin_transaction();

            if ($this->tableExists('plugins_raidplaner_template_bosses')) {
                if ($st = $this->db->prepare("DELETE FROM plugins_raidplaner_template_bosses WHERE template_id=?")) {
                    $st->bind_param("i", $template_id);
                    if (!$st->execute()) { $st->close(); $this->db->rollback(); return false; }
                    $st->close();
                }
            }
            if ($this->tableExists('plugins_raidplaner_template_setup')) {
                if ($st = $this->db->prepare("DELETE FROM plugins_raidplaner_template_setup WHERE template_id=?")) {
                    $st->bind_param("i", $template_id);
                    if (!$st->execute()) { $st->close(); $this->db->rollback(); return false; }
                    $st->close();
                }
            }
            if ($this->tableExists('plugins_raidplaner_templates')) {
                if ($st = $this->db->prepare("DELETE FROM plugins_raidplaner_templates WHERE id=?")) {
                    $st->bind_param("i", $template_id);
                    if (!$st->execute()) { $st->close(); $this->db->rollback(); return false; }
                    $st->close();
                }
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }
}
class RaidManager {
    private $_database;
    private $_settings;

    public function __construct(mysqli $database, array $settings) {
        $this->_database = $database;
        $this->_settings = $settings;
    }

    public function getAll(): array
    {
        $sql = "
            SELECT 
                e.id,
                COALESCE(t.title, e.title) AS title,
                e.event_time,
                e.duration_minutes,
                COUNT(s.id) AS signup_count
            FROM plugins_raidplaner_events e
            LEFT JOIN plugins_raidplaner_templates t 
                ON e.template_id = t.id
            LEFT JOIN plugins_raidplaner_signups s 
                ON s.event_id = e.id 
                AND s.status IN ('Angemeldet','Ersatzbank')
            GROUP BY e.id
            ORDER BY e.event_time DESC
        ";

        $stmt = $this->_database->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed: ' . $this->_database->error);
        }
        if (!$stmt->execute()) {
            throw new \RuntimeException('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            throw new \RuntimeException('get_result failed: ' . $stmt->error);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['id']              = (int)$row['id'];
            $row['duration_minutes']= isset($row['duration_minutes']) ? (int)$row['duration_minutes'] : null;
            $row['signup_count']    = (int)$row['signup_count'];
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }

    public function getById(int $id) {
        if ($id <= 0) return null;
        $sql = "SELECT 
                    e.*,
                    COALESCE(t.title, e.title) AS title
                FROM plugins_raidplaner_events e
                LEFT JOIN plugins_raidplaner_templates t ON t.id = e.template_id
                WHERE e.id = ?";
        $stmt = $this->_database->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function rp_role_label($languageService, string $roleName): string {
        $key   = 'role_' . strtolower($roleName);
        $label = (string)$languageService->get($key);

        if ($label === '[' . $key . ']' || $label === $key || $label === '' ) {
            $label = $roleName;
        }
        return $label;
    }
    
    public function getSetupForRaid(int $id) {
        if ($id <= 0) return [];
        $stmt = $this->_database->prepare("SELECT role_id, needed_count FROM plugins_raidplaner_setup WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $setup_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_column($setup_raw, 'needed_count', 'role_id');
    }

    public function save(array $data, int $userID) {
    $event_id       = intval($data['event_id'] ?? $data['id'] ?? 0);
    $title          = trim($data['title'] ?? '');
    $event_time_str = trim($data['event_time'] ?? '');
    $event_time     = str_replace('T', ' ', $event_time_str) . ':00';
    $duration       = (int)($data['duration'] ?? 180);
    $description    = trim($data['description'] ?? '');
    $setup_input    = $data['setup'] ?? [];
    $template_id    = intval($data['template_id'] ?? $data['applied_template_id'] ?? 0);

    // Konsistenz-Regel
    if ($template_id > 0) {
        // Vorlage gewählt ⇒ kein eigener Titel speichern
        $title = null;
    } else {
        // Keine Vorlage ⇒ Titel ist Pflicht
        if ($title === '') {
            // Entweder Exception, Rückgabewert false oder eigene Fehlerbehandlung:
            throw new \RuntimeException('Titel ist erforderlich, wenn keine Vorlage gewählt ist.');
        }
    }

    if ($event_id > 0) {
        $stmt = $this->_database->prepare("
            UPDATE plugins_raidplaner_events 
               SET title = ?, 
               description = ?, event_time = ?, duration_minutes = ?, template_id = ? 
             WHERE id = ?
        ");
        $stmt->bind_param("sssiii", $title, $description, $event_time, $duration, $template_id, $event_id);
        $stmt->execute();
        $new_event_id = $event_id;
    } else {
        $stmt = $this->_database->prepare("
            INSERT INTO plugins_raidplaner_events (title, description, event_time, duration_minutes, template_id, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssiii", $title, $description, $event_time, $duration, $template_id, $userID);
        $stmt->execute();
        $new_event_id = $stmt->insert_id;
    }

    if ($new_event_id > 0) {
        $del = $this->_database->prepare("DELETE FROM plugins_raidplaner_setup WHERE event_id = ?");
        $del->bind_param("i", $new_event_id);
        $del->execute();

        $stmt_setup = $this->_database->prepare("
            INSERT INTO plugins_raidplaner_setup (event_id, role_id, needed_count) 
            VALUES (?, ?, ?)
        ");
        foreach ($setup_input as $role_id => $count) {
            $needed_count = max(0, (int)$count);
            $rid = (int)$role_id;
            $stmt_setup->bind_param("iii", $new_event_id, $rid, $needed_count);
            $stmt_setup->execute();
        }
        return $new_event_id;
    }
    return 0;
}

    public function updateDiscordMessageId(int $raid_id, string $message_id) {
        $stmt = $this->_database->prepare("
            UPDATE plugins_raidplaner_events 
            SET discord_message_id = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $message_id, $raid_id);
        return $stmt->execute();
    }

    public function delete(int $id) {
        if ($id <= 0) return false;
        
        $webhook_url = $this->_settings['discord_webhook_url'] ?? '';
        if (!empty($webhook_url)) {
            $stmt_msg = $this->_database->prepare("SELECT discord_message_id FROM plugins_raidplaner_events WHERE id = ?");
            $stmt_msg->bind_param("i", $id);
            $stmt_msg->execute();
            $discord_message_id = $stmt_msg->get_result()->fetch_assoc()['discord_message_id'] ?? null;

            if (!empty($discord_message_id)) {
                delete_discord_message($webhook_url, $discord_message_id);
            }
        }

        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_signups WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_attendance WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_setup WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    public function deleteWithLootPurge(int $id): bool
    {
        if ($id <= 0) return false;

        // 1) Loot-Historie zu diesem Event vollständig löschen
        if ($stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_loot_history WHERE event_id = ?")) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        // 2) Danach den normalen Lösch-Flow für den Raid (Signups, Attendance, Setup, Event)
        return $this->delete($id);
    }
}
class LootManager {
    private $_database;

    public function __construct(mysqli $database) {
        $this->_database = $database;
    }
    public function addLoot(int $event_id, int $item_id, int $character_id, ?int $boss_id): bool {
    global $languageService;

    if ($item_id <= 0 || $character_id <= 0 || $event_id <= 0) {
        return false;
    }

    $user_stmt = $this->_database->prepare("SELECT userID FROM plugins_raidplaner_characters WHERE id = ?");
    $user_stmt->bind_param("i", $character_id);
    $user_stmt->execute();
    $user_id = $user_stmt->get_result()->fetch_assoc()['userID'] ?? null;

    if (!$user_id) {
        return false;
    }

    $status_stmt = $this->_database->prepare("SELECT is_obtained FROM plugins_raidplaner_character_gear WHERE character_id = ? AND item_id = ?");
    $status_stmt->bind_param("ii", $character_id, $item_id);
    $status_stmt->execute();
    $original_status = $status_stmt->get_result()->fetch_assoc()['is_obtained'] ?? null;
    if (!in_array($original_status, [0, 2])) {
        $original_status = null;
    }

    $this->_database->begin_transaction();

    try {
    $dup = $this->_database->prepare(
        "SELECT 1 FROM plugins_raidplaner_loot_history WHERE event_id = ? AND item_id = ? AND character_id = ? LIMIT 1"
    );
    if ($dup) {
        $dup->bind_param("iii", $event_id, $item_id, $character_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $dup->close();
            $dup->close();
            $this->setLastError('item_already_given');
            $this->_database->rollback();
            return false;
        }
        $dup->close();
    }

    $assigned_by = (int)($GLOBALS['userID'] ?? 0);

    $stmt_history = $this->_database->prepare(
        "INSERT INTO plugins_raidplaner_loot_history 
            (event_id, boss_id, item_id, character_id, user_id, original_wish_status, assigned_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt_history->bind_param(
        "iiiiisi",
        $event_id,
        $boss_id,
        $item_id,
        $character_id,
        $user_id, 
        $original_status, 
        $assigned_by
    );
    $stmt_history->execute();

    $stmt_gear = $this->_database->prepare(
        "INSERT INTO plugins_raidplaner_character_gear (character_id, item_id, is_obtained) 
         VALUES (?, ?, 1) 
         ON DUPLICATE KEY UPDATE is_obtained = 1"
    );
    $stmt_gear->bind_param("ii", $character_id, $item_id);
    $stmt_gear->execute();

    $this->_database->commit();
    return true;

    } catch (Exception $e) {
        if (method_exists($e, 'getCode') && (int)$e->getCode() === 1062) {
            $this->setLastError('item_already_given');
            $this->_database->rollback();
            return false;
        }
        $this->_database->rollback();
        return false;
    }
}
    public function getLootForRaid(int $event_id): array {
        $stmt = $this->_database->prepare("
            SELECT 
                lh.id,
                lh.looted_at,
                lh.original_wish_status,
                i.item_name,
                i.slot,
                c.character_name,
                u.username,                 -- Spieler (Empfänger)
                a.username AS assigned_by_username, -- NEU: vergebender Admin
                b.boss_name
            FROM plugins_raidplaner_loot_history lh
            JOIN plugins_raidplaner_items      i ON lh.item_id     = i.id
            JOIN plugins_raidplaner_characters c ON lh.character_id = c.id
            LEFT JOIN users              u ON lh.user_id     = u.userID
            LEFT JOIN users              a ON lh.assigned_by = a.userID   -- NEU
            LEFT JOIN plugins_raidplaner_bosses b ON lh.boss_id    = b.id
            WHERE lh.event_id = ?
            ORDER BY lh.looted_at DESC
        ");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function getHistoryForUser(int $user_id): array {
        $stmt = $this->_database->prepare("
            SELECT 
                i.item_name,
                i.slot,
                b.boss_name,
                COALESCE(t.title, re.title) AS raid_title,
                re.id         AS raid_id,
                re.event_time AS raid_date,
                lh.original_wish_status AS original_wish_status,
                lh.looted_at,
                c.character_name
            FROM plugins_raidplaner_loot_history lh
            JOIN plugins_raidplaner_items        i  ON lh.item_id = i.id
            LEFT JOIN plugins_raidplaner_events  re ON lh.event_id = re.id
            LEFT JOIN plugins_raidplaner_templates t ON t.id = re.template_id
            JOIN plugins_raidplaner_characters   c  ON lh.character_id = c.id
            LEFT JOIN plugins_raidplaner_bosses  b  ON lh.boss_id = b.id
            WHERE lh.user_id = ?
            ORDER BY lh.looted_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function delete(int $history_id): bool
{
    if ($history_id <= 0) return false;

    $hasStatusChangedAt = false;
    if ($res = $this->_database->query("SHOW COLUMNS FROM `plugins_raidplaner_character_gear` LIKE 'status_changed_at'")) {
        $hasStatusChangedAt = ($res->num_rows > 0);
        $res->close();
    }

    $this->_database->begin_transaction();
    try {
        // 1) Daten aus der History holen (hier gibt es KEIN loot_id-Feld, sondern die History-ID)
        $char_id = null;
        $item_id = null;
        $orig_status = null; // 0=needed, 2=wishlist, NULL=keine vorherige Angabe

        if ($stmt = $this->_database->prepare("
            SELECT character_id, item_id, original_wish_status
            FROM plugins_raidplaner_loot_history
            WHERE id = ?
            LIMIT 1
        ")) {
            $stmt->bind_param("i", $history_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) { $this->_database->rollback(); return false; }

            $char_id     = (int)$row['character_id'];
            $item_id     = (int)$row['item_id'];
            $orig_status = isset($row['original_wish_status']) ? (int)$row['original_wish_status'] : null;
            if ($orig_status !== 0 && $orig_status !== 2) $orig_status = null; // nur needed/wishlist sind relevant
        }

        // 2) Gear beim Charakter zurücksetzen
        if ($orig_status === null) {
            // Es gab keinen "needed/wishlist"-Zustand davor -> komplett entfernen
            if ($stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_character_gear WHERE character_id = ? AND item_id = ?")) {
                $stmt->bind_param("ii", $char_id, $item_id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            // Auf ursprünglichen Zustand zurücksetzen (0=needed, 2=wishlist)
            if ($hasStatusChangedAt) {
                if ($stmt = $this->_database->prepare("
                    UPDATE plugins_raidplaner_character_gear
                       SET is_obtained = ?, status = ?, status_changed_at = NOW()
                     WHERE character_id = ? AND item_id = ?
                ")) {
                    $stmt->bind_param("iiii", $orig_status, $orig_status, $char_id, $item_id);
                    $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                } else { $affected = 0; }
                if ($affected === 0) {
                    if ($stmt = $this->_database->prepare("
                        INSERT INTO plugins_raidplaner_character_gear (character_id, item_id, is_obtained, status, status_changed_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ")) {
                        $stmt->bind_param("iiii", $char_id, $item_id, $orig_status, $orig_status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } else {
                if ($stmt = $this->_database->prepare("
                    UPDATE plugins_raidplaner_character_gear
                       SET is_obtained = ?, status = ?
                     WHERE character_id = ? AND item_id = ?
                ")) {
                    $stmt->bind_param("iiii", $orig_status, $orig_status, $char_id, $item_id);
                    $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                } else { $affected = 0; }
                if ($affected === 0) {
                    if ($stmt = $this->_database->prepare("
                        INSERT INTO plugins_raidplaner_character_gear (character_id, item_id, is_obtained, status)
                        VALUES (?, ?, ?, ?)
                    ")) {
                        $stmt->bind_param("iiii", $char_id, $item_id, $orig_status, $orig_status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }

        // 3) History-Eintrag löschen
        if ($stmt = $this->_database->prepare("DELETE FROM plugins_raidplaner_loot_history WHERE id = ?")) {
            $stmt->bind_param("i", $history_id);
            $stmt->execute();
            $stmt->close();
        }

        $this->_database->commit();
        return true;
        } catch (\Throwable $e) {
            $this->_database->rollback();
            return false;
        }
    }
}
if (!function_exists('rp_get_all_classes')) {
    function rp_get_all_classes(mysqli $_database): array {
        $out = [];
        if ($res = $_database->query("SELECT id, class_name FROM plugins_raidplaner_classes ORDER BY class_name ASC")) {
            $out = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        }
        return $out;
    }
}

if (!function_exists('rp_save_bis_for_item')) {
    /**
     * Speichert BiS-Zuordnungen für ein Item. Erwartet UNIQUE KEY (item_id, class_id).
     * Wirft Exceptions bei DB-Fehlern (damit der aufrufende Code transaktional reagieren kann).
     * @param array<int,int> $classIds
     */
    function rp_save_bis_for_item(mysqli $_database, int $itemId, array $classIds): void {
        if (empty($classIds)) return;
        $stmt = $_database->prepare("INSERT IGNORE INTO plugins_raidplaner_bis_list (item_id, class_id) VALUES (?, ?)");
        if (!$stmt) throw new RuntimeException('Prepare failed: ' . $_database->error);

        foreach ($classIds as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) continue;
            if (!$stmt->bind_param('ii', $itemId, $cid)) throw new RuntimeException('bind_param failed: ' . $stmt->error);
            if (!$stmt->execute()) throw new RuntimeException('execute failed: ' . $stmt->error);
        }
        $stmt->close();
    }
}
if (!function_exists('rp_format_since')) {
    function rp_format_since($languageService, $addedAt): string {
        if ($addedAt === null || $addedAt === '') return '';

        // Zeitstempel zu ms normalisieren
        if (is_numeric($addedAt)) {
            $ts = (int)$addedAt;
            if ($ts < 1000000000000) { $ts *= 1000; } // Sekunden -> ms
        } else {
            $parsed = strtotime((string)$addedAt);
            if (!$parsed) return '';
            $ts = $parsed * 1000;
        }

        $nowMs = (int)(microtime(true) * 1000);
        $diff  = max(0, $nowMs - $ts);

        $dayMs  = 86400000;
        $hourMs = 3600000;
        $minMs  = 60000;

        if ($diff >= $dayMs) {
            $d = (int) floor($diff / $dayMs);
            return $d === 1
                ? $languageService->get('since_one_day')            // "seit 1 Tag"
                : sprintf($languageService->get('since_days'), $d);  // "seit %d Tagen"
        } elseif ($diff >= $hourMs) {
            $h = (int) floor($diff / $hourMs);
            return $h === 1
                ? $languageService->get('since_one_hour')           // "seit 1 Stunde"
                : sprintf($languageService->get('since_hours'), $h); // "seit %d Stunden"
        } elseif ($diff >= $minMs) {
            $m = (int) floor($diff / $minMs);
            return $m === 1
                ? $languageService->get('since_one_minute')         // "seit 1 Minute"
                : sprintf($languageService->get('since_minutes'), $m); // "seit %d Minuten"
        }
        return $languageService->get('since_today');                // "heute"
    }
}
if (!function_exists('rp_loot_pair_exists')) {
    function rp_loot_pair_exists(int $eventId, int $itemId, int $characterId): bool
    {
        global $_database;

        $stmt = $_database->prepare(
            'SELECT 1 
               FROM plugins_raidplaner_loot_history 
              WHERE event_id = ? AND item_id = ? AND character_id = ? 
              LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $eventId, $itemId, $characterId);
        $stmt->execute();
        $stmt->store_result();
        $exists = ($stmt->num_rows > 0);
        $stmt->close();

        return $exists;
    }
}