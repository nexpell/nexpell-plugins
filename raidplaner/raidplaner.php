<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lädt die zentrale Funktions-Datei
require_once __DIR__ . '/raidplaner-func.php';

// Benötigte Klassen und Services einbinden
use nexpell\LanguageService;
use nexpell\RoleManager;
use nexpell\SeoUrlHandler;

global $languageService, $tpl, $_database;

// Sprachpakete
$lang = $languageService->detectLanguage();
$languageService->readPluginModule('raidplaner');


$loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
$userID = $loggedin ? (int)$_SESSION['userID'] : 0;
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$action = $_REQUEST['action'] ?? '';

if ($action === 'update_gear_status' || $action === 'set_main_char') {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');

    if (!$loggedin || $_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => $languageService->get('error_csrf')]); exit;
    }

    $character_id = (int)($_POST['character_id'] ?? 0);

    $stmt_check = $_database->prepare("SELECT id FROM plugins_raidplaner_characters WHERE id = ? AND userID = ?");
    $stmt_check->bind_param("ii", $character_id, $userID);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => $languageService->get('error_db_operation')]); exit;
    }

    if ($action === 'set_main_char') {
        $stmt = $_database->prepare("
            UPDATE plugins_raidplaner_characters
               SET is_main = CASE WHEN id = ? THEN 1 ELSE 0 END
             WHERE userID = ?
        ");
        $stmt->bind_param("ii", $character_id, $userID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $languageService->get('msg_changes_saved_success')]);
        } else {
            echo json_encode(['success' => false, 'error' => $languageService->get('error_db_operation')]);
        }
        exit;
    }

    // ===== update_gear_status =====
    $item_id = (int)($_POST['item_id'] ?? 0);
    $status_str = strtolower(trim((string)($_POST['status'] ?? 'needed')));

    $db_status = 0;
    if ($status_str === 'wishlist')  $db_status = 2;
    elseif ($status_str === 'obtained') $db_status = 1;

    if ($character_id <= 0 || $item_id <= 0) {
        echo json_encode(['success' => false, 'error' => $languageService->get('error_db_operation')]); exit;
    }

    $hasStatusChangedAt = false;
    if ($colRes = $_database->query("SHOW COLUMNS FROM `plugins_raidplaner_character_gear` LIKE 'status_changed_at'")) {
        $hasStatusChangedAt = $colRes->num_rows > 0;
        $colRes->close();
    }

    if ($hasStatusChangedAt) {
        $stmt = $_database->prepare("
            INSERT INTO plugins_raidplaner_character_gear (character_id, item_id, is_obtained, status, status_changed_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                is_obtained = VALUES(is_obtained),
                status = VALUES(status),
                status_changed_at = IF(is_obtained <> VALUES(is_obtained), NOW(), status_changed_at)
        ");
        $stmt->bind_param("iiii", $character_id, $item_id, $db_status, $db_status);
    } else {
        $stmt = $_database->prepare("
            INSERT INTO plugins_raidplaner_character_gear (character_id, item_id, is_obtained, status)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_obtained = VALUES(is_obtained),
                status = VALUES(status)
        ");
        $stmt->bind_param("iiii", $character_id, $item_id, $db_status, $db_status);
    }

    if ($stmt && $stmt->execute()) {
        echo json_encode(['success' => true, 'message' => $languageService->get('msg_changes_saved_success')] );
    } else {
        echo json_encode(['success' => false, 'error' => $languageService->get('error_db_operation')] );
    }
    exit;
}
    // Header laden
    $config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
    $data_array = [
        'class' => htmlspecialchars($config['selected_style']), 
        'title' => $languageService->get('raidplaner_title'), 
        'calendar' => $languageService->get('calendar'), 
        'characters' => $languageService->get('characters'), 
        'my_stats' => $languageService->get('my_stats'), 
        'finished_raids' => $languageService->get('finished_raids'), 
        'subtitle' => 'Raidplaner',
        'auth_cls' => $loggedin ? 'is-auth' : 'is-guest',
    ];
    echo $tpl->loadTemplate("raidplaner", "head", $data_array, 'plugin');

    switch ($action) {
        case 'calendar':
            $stmt = $_database->prepare(
                "SELECT 
                    e.id, 
                    COALESCE(t.title, e.title) AS title, 
                    e.event_time, 
                    e.duration_minutes
                FROM plugins_raidplaner_events e
                LEFT JOIN plugins_raidplaner_templates t ON e.template_id = t.id
                WHERE e.is_active = 1"
            );
            $stmt->execute();
            $result = $stmt->get_result();

            $events = [];
            while ($row = $result->fetch_assoc()) {
                $startTs = strtotime($row['event_time']);
                if ($startTs === false) continue;

                $events[] = [
                    'title' => $row['title'],
                    'start' => date('c', $startTs),
                    'end'   => (!empty($row['duration_minutes'])) ? date('c', $startTs + ($row['duration_minutes'] * 60)) : null,
                    'url'   => SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=show&id=" . $row['id'] . "")
                ];
            }

            $data_array = [
                'title' => $languageService->get('calendar_title'),
                'events_json_data' => json_encode($events)
            ];
            echo $tpl->loadTemplate("raidplaner", "calendar_view", $data_array, 'plugin');
            break;

        case 'show':
        $event_id = (int)($_GET['id'] ?? 0);
        if ($event_id <= 0) {
            die($languageService->get('unknown_raid'));
        }

        if ($loggedin && isset($_POST['submit_signup'])) {
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                die($languageService->get('error_csrf'));
            }
        
        $character_id = (int)$_POST['character_id'];
        $role_id = (int)$_POST['role_id'];
        $status = $_POST['status'];
        $comment = htmlspecialchars(trim($_POST['comment']));

        $stmt_check_char = $_database->prepare("SELECT id FROM plugins_raidplaner_characters WHERE id = ? AND userID = ?");
        $stmt_check_char->bind_param("ii", $character_id, $userID);
        $stmt_check_char->execute();

        if ($stmt_check_char->get_result()->num_rows > 0) {
            $stmt_check_signup = $_database->prepare("SELECT id FROM plugins_raidplaner_signups WHERE event_id = ? AND user_id = ?");
            $stmt_check_signup->bind_param("ii", $event_id, $userID);
            $stmt_check_signup->execute();
            $is_already_signed_up = $stmt_check_signup->get_result()->num_rows > 0;
            $stmt_check_signup->close();

            if ($is_already_signed_up) {
                $stmt_signup = $_database->prepare("UPDATE plugins_raidplaner_signups SET character_id = ?, role_id = ?, status = ?, comment = ? WHERE event_id = ? AND user_id = ?");
                $stmt_signup->bind_param("iissii", $character_id, $role_id, $status, $comment, $event_id, $userID);
            } else {
                $stmt_signup = $_database->prepare("INSERT INTO plugins_raidplaner_signups (event_id, user_id, character_id, role_id, status, comment) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_signup->bind_param("iiiiss", $event_id, $userID, $character_id, $role_id, $status, $comment);
            }
            $stmt_signup->execute();
            $stmt_signup->close();
        }
        header("Location: " . SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=show&id=" . $event_id));
        exit;
    }
    
    // 1. Event-Grunddaten
    $stmt_event = $_database->prepare("
        SELECT 
            COALESCE(t.title, e.title) AS title,
            e.description,
            e.event_time,
            e.duration_minutes
        FROM plugins_raidplaner_events e
        LEFT JOIN plugins_raidplaner_templates t ON t.id = e.template_id
        WHERE e.id = ?
    ");
    $stmt_event->bind_param("i", $event_id);
    $stmt_event->execute();
    $event = $stmt_event->get_result()->fetch_assoc();
    $startTs = strtotime($event['event_time']);
    $endTs   = !empty($event['duration_minutes']) ? ($startTs + ((int)$event['duration_minutes'] * 60)) : $startTs;
    $isPast  = ($endTs < time());
    if (!$event) {
        die($languageService->get('unknown_raid'));
    }

    // 2. Rollen und Anmeldungen
    $setup_stmt = $_database->prepare("
        SELECT rs.role_id, rs.needed_count, rr.role_name, rr.icon
        FROM plugins_raidplaner_setup rs
        JOIN plugins_raidplaner_roles rr ON rs.role_id = rr.id
        WHERE rs.event_id = ?
    ");
    $setup_stmt->bind_param("i", $event_id);
    $setup_stmt->execute();
    $setup_result = $setup_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $roles_data = [];
    foreach ($setup_result as $row) {
        $roleName = (string)$row['role_name'];

        // Label mit Sprach-Fallback (wie besprochen)
        $key   = 'role_' . strtolower($roleName);
        $label = (string)$languageService->get($key);
        if ($label === '' || $label === $key || $label === '['.$key.']') {
            $label = $roleName;
        }

        // --- Icon-Sanitizing ---
        $iconRaw = isset($row['icon']) ? (string)$row['icon'] : '';
        // Trim: Whitespace + Anführungszeichen + spitze Klammern (falls jemand einen <i>-Tag gespeichert hat)
        $iconRaw = trim($iconRaw, " \t\n\r\0\x0B\"'<>");

        // Falls jemand einen ganzen <i>-Tag oder mehrere Klassen gespeichert hat,
        // zieh einfach die ERSTE gültige Bootstrap-Icon-Klasse heraus (bi-…)
        $iconClass = '';
        if ($iconRaw !== '' && preg_match('/\bbi-[a-z0-9-]+\b/i', $iconRaw, $m)) {
            $iconClass = strtolower($m[0]);
        } else {
            // Fallback: deine bisherigen Defaults
            $iconClass = match ($roleName) {
                'Tank'   => 'bi-shield-fill',
                'DD'     => 'bi-crosshair',
                'Healer' => 'bi-heart-fill',
                default  => 'bi-question-circle',
            };
        }

        // OPTIONAL: ein kleines spacing kannst du im Template machen (z. B. via .me-1 an <i>),
        // oder hier anhängen, wenn dein Template es nicht hat:
        // $iconClass .= ' me-1';

        $roles_data[$row['role_id']] = [
            'role_name'          => $label,
            'needed_count'       => (int)$row['needed_count'],
            'role_icon'          => $iconClass,   // <- nur die Klasse!
            'participants_count' => 0,
            'participants'       => [],
        ];
    }

    $signups_stmt = $_database->prepare("
        SELECT s.status, s.comment, s.role_id, c.character_name, cl.class_name, c.userID
        FROM plugins_raidplaner_signups s
        LEFT JOIN plugins_raidplaner_characters c ON s.character_id = c.id
        LEFT JOIN plugins_raidplaner_classes cl ON c.class_id = cl.id
        WHERE s.event_id = ? ORDER BY c.character_name
    ");
    $signups_stmt->bind_param("i", $event_id);
    $signups_stmt->execute();
    $signups_result = $signups_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // === Signups in Teilnehmer / Ersatzbank / Abgemeldet aufteilen ===
// Hinweis: Dieser Block ersetzt deine bisherige switch-Logik komplett.

$benched_data  = [];
$declined_data = [];

// Lokalisierte Labels (falls Status-Texte in der DB lokalisiert vorliegen)
$label_signed_up = strtolower($languageService->get('status_signed_up'));          // z. B. "Angemeldet"
$label_benched   = strtolower($languageService->get('status_benched_signup'));     // z. B. "Ersatzbank"
$label_absent    = strtolower($languageService->get('status_absent'));             // z. B. "Abwesend"

foreach ($signups_result as $signup) {
    $uid = (int)($signup['userID'] ?? 0);
    $rid = (int)($signup['role_id'] ?? 0);
    $st  = strtolower(trim((string)($signup['status'] ?? '')));

    // Datensatz, wie ihn deine Templates erwarten (extra: user_id für De-Dupe)
    $participant_data = [
        'user_id'     => $uid, // schadet Templates nicht, erleichtert De-Dupe
        'profile_url' => SeoUrlHandler::convertToSeoUrl("index.php?site=profile&userID=" . $uid),
        'char_name'   => htmlspecialchars($signup['character_name'] ?? $languageService->get('unknown_raid'), ENT_QUOTES, 'UTF-8'),
        'class_name'  => htmlspecialchars($signup['class_name'] ?? $languageService->get('msg_no_chars_or_classes'), ENT_QUOTES, 'UTF-8'),
        'comment'     => (!empty($signup['comment']) ? htmlspecialchars($signup['comment'], ENT_QUOTES, 'UTF-8') : null),
    ];

    // --- Teilnehmer (rollenbasiert) ---
    if ($st === 'angemeldet' || $st === $label_signed_up || $st === 'signed up' || $st === 'signed_up') {
        if ($rid > 0 && isset($roles_data[$rid])) {
            $roles_data[$rid]['participants'][] = $participant_data;
            $roles_data[$rid]['participants_count']++;
        }
        continue;
    }

    // --- Ersatzbank ---
    if ($st === 'ersatzbank' || $st === $label_benched || $st === 'benched' || $st === 'bench') {
        $benched_data[] = $participant_data;
        continue;
    }

    // --- Abgemeldet (aus Signups) → unten im Collapse
    if ($st === 'abgemeldet') {
        $declined_data[] = $participant_data;
        continue;
    }

    // Andere Signup-Status hier ignorieren (werden ggf. über Attendance aufgelöst)
}

// === Abwesende aus Attendance zusätzlich in den „unten“-Bereich mergen ===
// (Nur „Abwesend/Absent“, keine Dubletten.)

$absentLbls = array_values(array_unique(array_filter([
    strtolower($languageService->get('status_absent')), // lokalisiert, z. B. "Abwesend"
    'abwesend',
    'absent',
])));

if ($event_id > 0 && !empty($absentLbls)) {
    $placeholders = implode(',', array_fill(0, count($absentLbls), '?'));
    $types = 'i' . str_repeat('s', count($absentLbls));

    if ($stmtAbs = $_database->prepare("
        SELECT 
            ra.user_id AS userID,
            c.character_name,
            cl.class_name
        FROM plugins_raidplaner_attendance ra
        LEFT JOIN plugins_raidplaner_characters c ON ra.character_id = c.id
        LEFT JOIN plugins_raidplaner_classes cl ON c.class_id = cl.id
        WHERE ra.event_id = ?
          AND LOWER(ra.status) IN ($placeholders)
    ")) {
        $stmtAbs->bind_param($types, $event_id, ...$absentLbls);
        if ($stmtAbs->execute()) {
            $res  = $stmtAbs->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

            // Set der bereits im „unten“-Bereich erfassten User (wegen Dubletten)
            $declinedIds = [];
            foreach ($declined_data as $d) {
                if (isset($d['user_id'])) {
                    $declinedIds[(int)$d['user_id']] = true;
                }
            }

            foreach ($rows as $r) {
                $uid = (int)($r['userID'] ?? 0);
                if ($uid > 0 && empty($declinedIds[$uid])) {
                    $declined_data[] = [
                        'user_id'     => $uid,
                        'profile_url' => SeoUrlHandler::convertToSeoUrl("index.php?site=profile&userID=" . $uid),
                        'char_name'   => htmlspecialchars($r['character_name'] ?? $languageService->get('unknown_raid'), ENT_QUOTES, 'UTF-8'),
                        'class_name'  => htmlspecialchars($r['class_name'] ?? $languageService->get('msg_no_chars_or_classes'), ENT_QUOTES, 'UTF-8'),
                        'comment'     => null,
                    ];
                    $declinedIds[$uid] = true;
                }
            }
        }
        $stmtAbs->close();
    }
}
    // 3. Bosse & Loot
    $bosses_data = [];
    // Einheitliche Auflösung der Bosse pro Event (Template ODER Event-Mapping)
    $bossLookup  = new BossLookup($_database);
    $all_bosses  = $bossLookup->getBossesForEvent((int)$event_id);

    $user_class_ids = [];
    if ($loggedin) {
        $stmt_user_classes = $_database->prepare("SELECT DISTINCT class_id FROM plugins_raidplaner_characters WHERE userID = ?");
        $stmt_user_classes->bind_param("i", $userID);
        $stmt_user_classes->execute();
        $user_classes_raw = $stmt_user_classes->get_result()->fetch_all(MYSQLI_ASSOC);
        $user_class_ids = array_column($user_classes_raw, 'class_id');
    }

    foreach ($all_bosses as $boss) {
    $loot_data = [];

    if (!isset($loot_data) || !is_array($loot_data)) {
    $loot_data = [];
}

if (!empty($user_class_ids)) {
    $placeholders = implode(',', array_fill(0, count($user_class_ids), '?'));

    $sql = "SELECT i.item_name,
                   i.slot,
                   GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ', ') AS classes,
                   SUM(bl.class_id IN ($placeholders)) > 0 AS is_relevant
            FROM plugins_raidplaner_items i
            LEFT JOIN plugins_raidplaner_bis_list bl ON i.id = bl.item_id
            LEFT JOIN plugins_raidplaner_classes c ON bl.class_id = c.id
            WHERE i.boss_id = ?
            GROUP BY i.item_name, i.slot
            ORDER BY i.slot, i.item_name";

        $stmt_items = $_database->prepare($sql);

        $types  = str_repeat('i', count($user_class_ids)) . 'i';
        $params = array_merge($user_class_ids, [$boss['id']]);
        $stmt_items->bind_param($types, ...$params);
    } else {
        $sql = "SELECT i.item_name,
                    i.slot,
                    GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ', ') AS classes,
                    0 AS is_relevant
                FROM plugins_raidplaner_items i
                LEFT JOIN plugins_raidplaner_bis_list bl ON i.id = bl.item_id
                LEFT JOIN plugins_raidplaner_classes c ON bl.class_id = c.id
                WHERE i.boss_id = ?
                GROUP BY i.item_name, i.slot
                ORDER BY i.slot, i.item_name";

        $stmt_items = $_database->prepare($sql);
        $stmt_items->bind_param('i', $boss['id']);
    }

    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    foreach ($items as $item) {
        $loot_data[] = [
            'slot'        => htmlspecialchars($item['slot'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'item_name'   => htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'classes'     => htmlspecialchars($item['classes'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), // NULL-safe
            'is_relevant' => !empty($item['is_relevant']),
        ];
    }

    $bosses_data[] = [
        'boss_id' => $boss['id'],
        'boss_name' => htmlspecialchars(stripslashes($boss['boss_name'])),
        'tactics' => !empty($boss['tactics']) ? nl2br(htmlspecialchars(str_replace(["\\r\\n", "\\n", "\\r", "\\'"], ["\n", "\n", "\n", "'"], $boss['tactics']))) : null,
        'loot_loop' => $loot_data
    ];
}

$role_group_template = $tpl->loadTemplate("raidplaner", "show_details_role_group", [], 'plugin');
$participant_item_template = $tpl->loadTemplate("raidplaner", "show_details_participant_item", [], 'plugin');
$data_array = [
    'waiting' => $languageService->get('waiting'),
];
$benched_section_template = $tpl->loadTemplate("raidplaner", "show_details_benched_section", $data_array, 'plugin');
$data_array = [
    'unsubscribed' => $languageService->get('unsubscribed'),
];
$declined_section_template = $tpl->loadTemplate("raidplaner", "show_details_declined_section", $data_array, 'plugin');
$boss_item_template = $tpl->loadTemplate("raidplaner", "show_details_boss_item", [], 'plugin');

$participants_html = '';
foreach ($roles_data as $role) {
    $participant_items_html = '';
    foreach ($role['participants'] as $participant) {
        $placeholders = ['{profile_url}', '{char_name}', '{class_name}', '{comment}', '{comment_hidden_class}'];
        $values = [
            $participant['profile_url'],
            $participant['char_name'],
            $participant['class_name'],
            $participant['comment'],
            $participant['comment'] ? '' : 'd-none'
        ];
        $participant_items_html .= str_replace($placeholders, $values, $participant_item_template);
    }

    $placeholders = ['{role_icon}', '{role_name}', '{participants_count}', '{needed_count}', '{participant_items_html}'];
    $values = [
        htmlspecialchars($role['role_icon'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8'),
        (int)$role['participants_count'],
        (int)$role['needed_count'],
        $participant_items_html
    ];
    $participants_html .= str_replace($placeholders, $values, $role_group_template);
}

$benched_html = '';
if (!empty($benched_data)) {
    $benched_items_html = '';
    foreach ($benched_data as $participant) {
        $placeholders = ['{profile_url}', '{char_name}', '{class_name}', '{comment}', '{comment_hidden_class}'];
        $values = [$participant['profile_url'], $participant['char_name'], $participant['class_name'], $participant['comment'], $participant['comment'] ? '' : 'd-none'];
        $benched_items_html .= str_replace($placeholders, $values, $participant_item_template);
    }
    $benched_html = str_replace(['{benched_count}', '{benched_items_html}'], [count($benched_data), $benched_items_html], $benched_section_template);
}

$declined_html = '';
if (!empty($declined_data)) {
    $declined_items_html = '';
    foreach ($declined_data as $participant) {
        $placeholders = ['{profile_url}', '{char_name}', '{class_name}', '{comment}', '{comment_hidden_class}'];
        $values = [$participant['profile_url'], $participant['char_name'], $participant['class_name'], $participant['comment'], $participant['comment'] ? '' : 'd-none'];
        $declined_items_html .= str_replace($placeholders, $values, $participant_item_template);
    }
    $declined_html = str_replace(['{declined_count}', '{declined_items_html}'], [count($declined_data), $declined_items_html], $declined_section_template);
}

$bosses_html = '<div class="accordion" id="bossAccordion">';
foreach ($bosses_data as $boss) {
    $tactics_html = $boss['tactics'] ? '<div class="mb-3"><h6 class="fw-bold">' . $languageService->get('form_tactics') . '</h6><p class="mb-0">' . $boss['tactics'] . '</p></div><hr>' : '';
    $loot_html = '';
    $isloggedin = (int)($_SESSION['userID'] ?? $_SESSION['user_id'] ?? 0) > 0;
    $hasCharacter = isset($user_class_ids) && is_array($user_class_ids) && count($user_class_ids) > 0;

    $presentKey = !$isloggedin
    ? 'title_relevant_loot_for_guest'
    : ($hasCharacter ? 'title_relevant_loot_for_you' : 'title_relevant_loot_no_character');

    $pastKey = !$isloggedin
    ? 'title_relevant_loot_for_guest'
    : ($hasCharacter ? 'title_relevant_loot_past' : 'title_relevant_loot_no_character');

    $lootTitle = $languageService->get($isPast ? $pastKey : $presentKey);

    if (!empty($boss['loot_loop'])) {
        $loot_html = '<div class="mb-3"><h6 class="fw-bold">' . $lootTitle . '</h6><ul class="list-group">';
        foreach ($boss['loot_loop'] as $loot) {
            $slot      = htmlspecialchars($loot['slot'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $itemName  = htmlspecialchars($loot['item_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $classesRaw = trim((string)($loot['classes'] ?? ''));
            $classesArr = array_filter(array_map('trim', $classesRaw === '' ? [] : explode(',', $classesRaw)));
            $classesHtml = '<span class="text-muted">-</span>';
            if (!empty($classesArr)) {
                $classesHtml = '<div class="d-flex flex-wrap gap-1">';
                foreach ($classesArr as $cls) {
                    $classesHtml .= '<span class="badge bg-light text-dark border">'
                                . htmlspecialchars($cls, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                . '</span>';
                }
                $classesHtml .= '</div>';
            }

            $bisBadge = '';
            if (!empty($loot['is_relevant'])) {

                $bisBadge = '<span class="badge bg-success d-inline-flex align-items-center">'
                        . '<i class="bi bi-star-fill me-1"></i>'
                        . htmlspecialchars($languageService->get('title_bis_assignment_for'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</span>';
            }

            $loot_html .= '
            <li class="loot-list list-group-item">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div class="d-flex align-items-center gap-2">
                <span class="badge bg-info text-white">' . $slot . '</span>
                <span class="fw-semibold">' . $itemName . '</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                ' . $bisBadge . '
                ' . $classesHtml . '
                </div>
            </div>
            </li>';
        }
        $loot_html .= '</ul></div>';
    } else {
        $loot_html = '<p class="text-muted">' . $languageService->get('msg_no_loot_recorded_for_boss') . '</p>';
    }

    $placeholders = ['{boss_id}', '{boss_name}', '{tactics_html}', '{loot_html}'];
    $values = [$boss['boss_id'], $boss['boss_name'], $tactics_html, $loot_html];
    $bosses_html .= str_replace($placeholders, $values, $boss_item_template);
    }
    $bosses_html .= '</div>';

    if ($loggedin && empty($bosses_data)) {
        $bosses_html .= '<p class="text-muted mt-3">' . htmlspecialchars($languageService->get('msg_no_boss_loot')) . '</p>';
    }

    $show_signup_form_html = '';
    $no_character_message_html = '';
    $login_message_html = '';

    $end_timestamp = strtotime($event['event_time']) + (($event['duration_minutes'] ?? 180) * 60);
    $is_in_past = $end_timestamp < time();

    if ($is_in_past) {
    $no_character_message_html = '<div class="alert alert-secondary">' . $languageService->get('msg_raid_in_past_no_signup') . '</div>';
    } elseif ($loggedin) {
        $stmt_user_chars = $_database->prepare("SELECT id, character_name FROM plugins_raidplaner_characters WHERE userID = ? ORDER BY is_main DESC, character_name ASC");
        $stmt_user_chars->bind_param("i", $userID);
        $stmt_user_chars->execute();
        $user_chars = $stmt_user_chars->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($user_chars)) {
            $no_character_message_html = '<div class="alert alert-warning">' . $languageService->get('msg_no_chars_or_classes') . '</div>';
        } else {
            $current_signup = ['character_id' => 0, 'role_id' => 0, 'status' => $languageService->get('status_signed_up'), 'comment' => ''];
            $stmt_current_signup = $_database->prepare("SELECT character_id, role_id, status, comment FROM plugins_raidplaner_signups WHERE event_id = ? AND user_id = ?");
            $stmt_current_signup->bind_param("ii", $event_id, $userID);
            $stmt_current_signup->execute();
            $signup_res = $stmt_current_signup->get_result();
            if ($signup_res->num_rows > 0) {
                $current_signup = $signup_res->fetch_assoc();
            }
            $char_options = '';
            foreach ($user_chars as $char) {
                $selected = ($char['id'] == $current_signup['character_id']) ? ' selected' : '';
                $char_options .= '<option value="' . $char['id'] . '"' . $selected . '>' . htmlspecialchars($char['character_name']) . '</option>';
            }
            $role_options = '';
            foreach ($roles_data as $role_id => $role) {
                $selected = ($role_id == $current_signup['role_id']) ? ' selected' : '';
                $role_options .= '<option value="' . $role_id . '"' . $selected . '>' . htmlspecialchars($role['role_name']) . '</option>';
            }
            $status_radios = '';
            $statuses = [$languageService->get('status_signed_up'), $languageService->get('status_benched_signup'), $languageService->get('status_absent')];
            foreach ($statuses as $status_val) {
                $checked = (strtolower($status_val) == strtolower($current_signup['status'])) ? ' checked' : '';
                $status_radios .= '<div class="form-check"><input class="form-check-input" type="radio" name="status" id="status_' . $status_val . '" value="' . $status_val . '"' . $checked . '><label class="form-check-label" for="status_' . $status_val . '">' . $status_val . '</label></div>';
            }
            $show_signup_form_html = '<form method="POST" action=""><input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '"><div class="card"><div class="card-header"><h5 class="card-title">' . $languageService->get('form_select_player') . '</h5></div><div class="card-body"><select class="form-select" id="character_id" name="character_id" required>' . $char_options . '</select><label for="role_id" class="form-label">' . $languageService->get('tbl_header_role') . '</label><select class="form-select" id="role_id" name="role_id" required>' . $role_options . '</select><label class="form-label">' . $languageService->get('form_status') . '</label>' . $status_radios . '<label for="comment" class="form-label">' . $languageService->get('form_comment') . '</label><input class="form-control" id="comment" name="comment" placeholder="' . $languageService->get('placeholder_comment') . '"><br><button type="submit" name="submit_signup" class="btn btn-primary w-100">' . $languageService->get('btn_signup_player') . '</button></div></div></form>';
        }
    } else {
        $login_message_html = '<div class="alert alert-info">' . $languageService->get('msg_login_to_signup') . '</div>';
    }

    $hasSignupForm = trim((string)$show_signup_form_html) !== '';
    $signup_panel_html = $show_signup_form_html ?: ($no_character_message_html ?: $login_message_html);
    if (!$is_in_past) {
        $form  = trim((string)$show_signup_form_html);
        $noChr = trim((string)$no_character_message_html);
        $login = trim((string)$login_message_html);

        $show_signup_form_html = $form !== '' ? $form : ($noChr !== '' ? $noChr : $login);
    }
    $signup_col_class  = $is_in_past ? 'd-none'                         : 'col-12 col-lg-4 order-lg-2';
    $content_col_class = $is_in_past ? 'col-12'                         : 'col-12 col-lg-8 order-lg-1';

    $data_array = [
        'event_title' => htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8'),
        'event_time_formatted' => rp_format_dt(strtotime($event['event_time']), 'd.m.Y H:i')
            . (stripos((string)$languageService->get('raidplaner_title'), 'raid planner') !== false
                ? ''
                : ' ' . $languageService->get('clock')),
        'event_description' => nl2br(htmlspecialchars(str_replace(["\\r\\n", "\\n", "\\r", "\\'"], ["\n", "\n", "\n", "'"], $event['description'] ?? ''))),
        'participants_html' => $participants_html,
        'benched_html' => $benched_html,
        'declined_html' => $declined_html,
        'bosses_html' => $bosses_html,
        'show_signup_form_html' => $signup_panel_html,
        'signup_col_class' => $signup_col_class,
        'content_col_class' => $content_col_class,
        'no_character_message_html' => $no_character_message_html,
        'login_message_html' => $login_message_html,
        'description' => $languageService->get('description'),
        'signups' => $languageService->get('signups'),
        'boss_bis_loot' => $languageService->get('boss_bis_loot'),
    ];

    echo $tpl->loadTemplate("raidplaner", "show_details", $data_array, 'plugin');
    break;

    case 'bis_planner':
    if (!$loggedin) { die("Bitte einloggen."); }

    $character_id = (int)($_GET['character_id'] ?? 0);
    if ($character_id <= 0) { die("Kein Charakter ausgewählt."); }

    $stmt_char = $_database->prepare("SELECT character_name, class_id FROM plugins_raidplaner_characters WHERE id = ? AND userID = ?");
    $stmt_char->bind_param("ii", $character_id, $userID);
    $stmt_char->execute();
    $character = $stmt_char->get_result()->fetch_assoc();
    if (!$character) { die("Charakter nicht gefunden oder keine Berechtigung."); }

    $stmt_items = $_database->prepare("
        SELECT 
            i.id,
            i.item_name,
            i.source,
            i.slot,
            COALESCE(i.boss_name, b.boss_name) AS boss_name,
            COALESCE(cg.is_obtained, 0) AS status,
            cg.status_changed_at,

            /* 1) Sperre: hat der Char das Item schon einmal gelootet? */
            (EXISTS (
                SELECT 1
                FROM plugins_raidplaner_loot_history lh
                WHERE lh.item_id = i.id
                AND lh.character_id = ?
            )) + 0 AS awarded_lock,

            /* Finale Raid-Anzeige:
            1) zuletzt gelootetes Event (Template-Titel oder Event-Titel)
            2) items.raid_name
            3) Template-Titel über Boss→Template
            4) Fallback-Text
            */
            COALESCE(
                (
                    SELECT COALESCE(t.title, e.title)
                    FROM plugins_raidplaner_loot_history lh2
                    JOIN plugins_raidplaner_events e
                    ON e.id = lh2.event_id
                    LEFT JOIN plugins_raidplaner_templates t
                    ON t.id = e.template_id
                    WHERE lh2.item_id = i.id
                    AND lh2.character_id = ?
                    ORDER BY lh2.looted_at DESC
                    LIMIT 1
                ),
                i.raid_name,
                (
                    SELECT t2.title
                    FROM plugins_raidplaner_template_bosses tb2
                    JOIN plugins_raidplaner_templates t2
                    ON t2.id = tb2.template_id
                    WHERE tb2.boss_id = i.boss_id
                    ORDER BY t2.id
                    LIMIT 1
                ),
                'kein Raid zugehörig'
            ) AS raid_title

        FROM plugins_raidplaner_bis_list bl
        JOIN plugins_raidplaner_items i
        ON bl.item_id = i.id
        LEFT JOIN plugins_raidplaner_bosses b
        ON i.boss_id = b.id
        LEFT JOIN plugins_raidplaner_character_gear cg
        ON i.id = cg.item_id
        AND cg.character_id = ?
        WHERE bl.class_id = ?
        ORDER BY i.slot, i.item_name
    ");
    $stmt_items->bind_param(
        "iiii",
        $character_id,
        $character_id,
        $character_id,
        $character['class_id']
    );
    $stmt_items->execute();
    $items_result = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    // Wenn keine BiS-Items für diese Klasse existieren
    if (empty($items_result)) {

        if (has_role($userID, 'Admin')) {

            echo '<div class="alert alert-info text-center">
                    <strong>' . $languageService->get('bis_no_items_admin') . '</strong><br><br>
                    <a class="btn btn-sm btn-primary" title="' . $languageService->get('bis_manage_items_title') . '" 
                    href="admincenter.php?site=admin_raidplaner&tab=items">
                        <i class="bi bi-wrench"></i> ' . $languageService->get('bis_manage_items_button') . '
                    </a>
                </div>';

        } else {

            echo '<div class="alert alert-info text-center">
                    <strong>' . $languageService->get('bis_no_items_user') . '</strong>
                </div>';
        }

        return;
    }

    $items_by_slot = [];
    foreach ($items_result as $item) {
        $items_by_slot[$item['slot']][] = $item;
    }

    $bis_list_html = '';
    if (empty($items_by_slot)) {
        $bis_list_html = '<tr><td colspan="3" class="text-center">' . $languageService->get('msg_no_bis_list') . '</td></tr>';
    } else {
        $slot_header_template = $tpl->loadTemplate("raidplaner", "bis_planner_slot", [], 'plugin');
        $item_row_template    = $tpl->loadTemplate("raidplaner", "bis_planner_item", [], 'plugin');
        $statuses = [
            'needed'   => $languageService->get('badge_needed'),
            'wishlist' => $languageService->get('badge_wishlist'),
        ];

        foreach ($items_by_slot as $slot_name => $items) {
            $bis_list_html .= str_replace('{slot_name}', htmlspecialchars($slot_name), $slot_header_template);

            foreach ($items as $item) {
                $current_status_value = match ((int)$item['status']) {
                    1 => 'obtained',
                    2 => 'wishlist',
                    default => 'needed'
                };

                $locked = ((int)($item['awarded_lock'] ?? 0) === 1);
                if ($locked) {
                    $status_dropdown_html = '<span class="badge bg-success">'
                        . htmlspecialchars($languageService->get('badge_obtained'))
                        . '</span> <i class="bi bi-lock-fill text-muted" title="Durch Loot festgesetzt"></i>';
                    $current_status_value = 'obtained';
                    $icon_class = 'd-none';
                } else {
                    $icon_class = match ((int)$item['status']) {
                        2 => 'bi-star-fill text-warning',
                        default => 'bi-x-circle text-danger',
                    };
                    $status_dropdown_html = '<select class="form-select form-select-sm gear-status-select me-2"'
                        . ' data-item-id="' . (int)$item['id'] . '"'
                        . ' data-char-id="' . (int)$character_id . '">';
                    foreach ($statuses as $value => $label) {
                        $selected = ($value === $current_status_value) ? ' selected' : '';
                        $status_dropdown_html .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
                    }
                    $status_dropdown_html .= '</select>';
                }

                $since_html = '';
                if ($current_status_value === 'wishlist') {
                    $since_label = rp_format_since($languageService, $item['status_changed_at'] ?? null);
                    if ($since_label !== '') {
                        $since_html = '<div class="small text-muted">' . htmlspecialchars($since_label) . '</div>';
                    }
                }

                $raid_text = !empty($item['raid_title'])
                ? htmlspecialchars($item['raid_title'])
                : '<span class="text-muted">' . $languageService->get('msg_no_raid') . '</span>';

                $boss_or_source = !empty($item['boss_name']) ? stripslashes($item['boss_name']) : ($item['source'] ?? '');
                $boss_text = $boss_or_source !== ''
                ? htmlspecialchars($boss_or_source)
                : '';

                $placeholders = ['{id}', '{item_name}', '{raid_text}', '{boss_text}', '{status_dropdown_html}', '{icon_class}'];
                $values = [
                (int)$item['id'],
                htmlspecialchars($item['item_name']) . $since_html,
                $raid_text,
                $boss_text,
                $status_dropdown_html,
                $icon_class
                ];
                $bis_list_html .= str_replace($placeholders, $values, $item_row_template);
            }
        }
    }

    $data_array = [
        'title'              => $languageService->get('title_bis_assignment_for'),
        'back_to_chars_overview'  => $languageService->get('back_to_chars_overview'),
        'for'                => $languageService->get('for'),
        'source'             => $languageService->get('source'),
        'status'             => $languageService->get('status'),
        'character_name'     => htmlspecialchars($character['character_name']),
        'back_to_chars_url'  => SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=characters"),
        'bis_list_html'      => $bis_list_html,
        'csrf_token'         => $_SESSION['csrf_token'],
    ];
    echo $tpl->loadTemplate("raidplaner", "bis_planner", $data_array, 'plugin');
    break;

    case 'characters':
    if (!$loggedin) {
        die($languageService->get('must_be_logged_in_chars'));
    }

    // --- Formularverarbeitung für Hinzufügen, Bearbeiten und Löschen ---
    if (isset($_POST['submit_add_character'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die($languageService->get('error_csrf'));
        }
        $character_name = htmlspecialchars(trim($_POST['character_name']));
        $class_id = (int)($_POST['class_id'] ?? 0);
        $level = (int)($_POST['level'] ?? 0);
        if (!empty($character_name) && $class_id > 0 && $level > 0) {
            $stmt_check = $_database->prepare("SELECT id FROM plugins_raidplaner_characters WHERE userID = ? AND character_name = ?");
            $stmt_check->bind_param("is", $userID, $character_name);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows === 0) {
                $stmt_insert = $_database->prepare("INSERT INTO plugins_raidplaner_characters (userID, character_name, class_id, level) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("isii", $userID, $character_name, $class_id, $level);
                $stmt_insert->execute();
            }
        }
        header("Location: " . SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=characters"));
        exit;
    }

    if (isset($_POST['submit_edit_character'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die($languageService->get('error_csrf'));
        }
        $character_id = (int)($_POST['character_id'] ?? 0);
        $character_name = htmlspecialchars(trim($_POST['character_name']));
        $class_id = (int)($_POST['class_id'] ?? 0);
        $level = (int)($_POST['level'] ?? 0);
        if ($character_id > 0 && !empty($character_name) && $class_id > 0 && $level > 0) {
            $stmt_update = $_database->prepare("UPDATE plugins_raidplaner_characters SET character_name = ?, class_id = ?, level = ? WHERE id = ? AND userID = ?");
            $stmt_update->bind_param("siiii", $character_name, $class_id, $level, $character_id, $userID);
            $stmt_update->execute();
        }
        header("Location: " . SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=characters"));
        exit;
    }

    if (isset($_POST['submit_delete_character'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die($languageService->get('error_csrf'));
        }
        $character_id = (int)($_POST['character_id'] ?? 0);
        if ($character_id > 0) {
            $stmt_delete = $_database->prepare("DELETE FROM plugins_raidplaner_characters WHERE id = ? AND userID = ?");
            $stmt_delete->bind_param("ii", $character_id, $userID);
            $stmt_delete->execute();
        }
        header("Location: " . SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=characters"));
        exit;
    }

    // --- Ab hier beginnt die Anzeige der Seite ---
    $all_classes_raw = $_database->query("SELECT id, class_name FROM plugins_raidplaner_classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);
    $all_classes = array_column($all_classes_raw, 'class_name', 'id');

    $stmt_chars = $_database->prepare("
        SELECT rc.id, rc.character_name, rc.level, rc.is_main, rc.class_id, rcl.class_name
        FROM plugins_raidplaner_characters rc LEFT JOIN plugins_raidplaner_classes rcl ON rc.class_id = rcl.id
        WHERE rc.userID = ? ORDER BY rc.is_main DESC, rc.character_name ASC
    ");
    $stmt_chars->bind_param("i", $userID);
    $stmt_chars->execute();
    $result_chars = $stmt_chars->get_result()->fetch_all(MYSQLI_ASSOC);

    $character_table_rows_html = '';
    $modals_html = '';

    $row_template = $tpl->loadTemplate("raidplaner", "characters_entry", [], 'plugin');

    foreach ($result_chars as $char) {
        $class_options_for_modal = '';
        foreach ($all_classes as $id => $name) {
            $selected = ($id == $char['class_id']) ? ' selected' : '';
            $class_options_for_modal .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }

        $placeholders = [
            '{id}', '{is_main_icon_class}', '{character_name}',
            '{class_name}', '{level}', '{bis_planner_url}'
        ];
        $values = [
            $char['id'],
            $char['is_main'] ? 'bi-star-fill text-warning' : 'bi-star',
            htmlspecialchars($char['character_name']),
            htmlspecialchars($char['class_name'] ?? $languageService->get('msg_no_chars_or_classes')),
            $char['level'],
            SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=bis_planner&character_id=" . $char['id'])
        ];
        $character_table_rows_html .= str_replace($placeholders, $values, $row_template);

        $modals_html .= '
            <div class="modal fade" id="editCharacterModal' . $char['id'] . '" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action=""><input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '"><input type="hidden" name="character_id" value="' . $char['id'] . '">
                    <div class="modal-header"><h5 class="modal-title">' . $languageService->get('modal_edit_character_title') . '</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">' . $languageService->get('form_label_name') . '</label><input type="text" class="form-control" name="character_name" value="' . htmlspecialchars($char['character_name']) . '" required></div>
                        <div class="mb-3"><label class="form-label">' . $languageService->get('form_label_class') . '</label><select class="form-select" name="class_id" required>' . $class_options_for_modal . '</select></div>
                        <div class="mb-3"><label class="form-label">' . $languageService->get('form_label_level') . '</label><input type="number" class="form-control" name="level" value="' . $char['level'] . '" required></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('btn_cancel') . '</button><button type="submit" name="submit_edit_character" class="btn btn-primary">' . $languageService->get('btn_save') . '</button></div>
                </form>
            </div></div></div>';

        $modals_html .= '
            <div class="modal fade" id="deleteCharacterModal' . $char['id'] . '" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action=""><input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '"><input type="hidden" name="character_id" value="' . $char['id'] . '">
                    <div class="modal-header"><h5 class="modal-title">' . $languageService->get('modal_delete_character_title') . '</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">' . str_replace('{character_name}', htmlspecialchars($char['character_name']), $languageService->get('modal_delete_character_body')) . '</div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('btn_cancel') . '</button><button type="submit" name="submit_delete_character" class="btn btn-danger">' . $languageService->get('btn_delete') . '</button></div>
                </form>
            </div></div></div>';
    }

    $class_options_html = '';
    foreach ($all_classes as $id => $name) {
        $class_options_html .= '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
    }

    $data_array = [
        'modals_html' => $modals_html,
        'character_table_rows_html' => $character_table_rows_html,
        'class_options_html' => $class_options_html,
        'csrf_token' => $_SESSION['csrf_token'],
        'form_action_url' => SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=characters"),
        'th_main' => 'Main',
        'characters_title' => $languageService->get('characters_title'),
        'add_character_title' => $languageService->get('add_character_title'),
        'form_char_name' => $languageService->get('form_char_name'),
        'form_char_class' => $languageService->get('form_char_class'),
        'form_char_level' => $languageService->get('form_char_level'),
        'form_add_button' => $languageService->get('form_add_button'),
        'th_name' => $languageService->get('th_name'),
        'th_class' => $languageService->get('th_class'),
        'th_level' => $languageService->get('th_level'),
        'th_actions' => $languageService->get('th_actions'),
        'please_choose' => $languageService->get('please_choose'),
    ];
    echo $tpl->loadTemplate("raidplaner", "characters", $data_array, 'plugin');
    break;

    case 'my_stats':
    if (!$loggedin) {
        die($languageService->get('must_be_logged_in'));
    }
    // --- ANWESENHEIT ---
    $count_present = 0;
    $count_late = 0;
    $count_absent = 0;
    $stmt_attendance = $_database->prepare(
        "SELECT ra.status, COUNT(ra.id) as count
        FROM plugins_raidplaner_attendance ra
        WHERE ra.user_id = ?
        GROUP BY ra.status"
    );
    $stmt_attendance->bind_param("i", $userID);
    $stmt_attendance->execute();
    $attendance_result = $stmt_attendance->get_result();

    while ($row = $attendance_result->fetch_assoc()) {
        if ($row['status'] === $languageService->get('status_present')) {
            $count_present = (int)$row['count'];
        }
        if ($row['status'] === $languageService->get('status_late')) {
            $count_late = (int)$row['count'];
        }
        if ($row['status'] === $languageService->get('status_absent')) {
            $count_absent = (int)$row['count'];
        }
    }
    $total_recorded_for_ratio = $count_present + $count_absent + $count_late;
    $attendance_percentage = ($total_recorded_for_ratio > 0)
        ? round((($count_present + 0.5 * $count_late) / $total_recorded_for_ratio) * 100)
        : 0;
    $attendance_note = $languageService->get('msg_attendance_based_on_recorded_raids');

    // --- ANWESENHEITS-VERLAUF ---
    $stmt_history = $_database->prepare(
        "SELECT 
            re.id, 
            COALESCE(t.title, re.title) AS title, 
            re.event_time, 
            ra.status, 
            c.character_name
        FROM plugins_raidplaner_attendance ra
        JOIN plugins_raidplaner_events re ON ra.event_id = re.id
        LEFT JOIN plugins_raidplaner_templates t ON t.id = re.template_id
        LEFT JOIN plugins_raidplaner_characters c ON ra.character_id = c.id
        WHERE ra.user_id = ?
        ORDER BY re.event_time DESC"
    );
    $stmt_history->bind_param("i", $userID);
    $stmt_history->execute();
    $history_result = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);

    $attendance_history_html = '';
    if (empty($history_result)) {
        $attendance_history_html = '<tr><td colspan="4" class="text-center text-muted">' . $languageService->get('msg_no_attendance_history') . '</td></tr>';
    } else {
        foreach ($history_result as $entry) {
            $status_badge = '';
            switch ($entry['status']) {
                case $languageService->get('status_present'):
                    $status_badge = '<span class="badge bg-success">' . $languageService->get('status_present') . '</span>';
                    break;
                case $languageService->get('status_late'):
                    $status_badge = '<span class="badge bg-info text-dark">' . $languageService->get('status_late') . '</span>';
                    break;
                case $languageService->get('status_absent'):
                    $status_badge = '<span class="badge bg-danger">' . $languageService->get('status_absent') . '</span>';
                    break;
                case $languageService->get('status_benched_signup'):
                    $status_badge = '<span class="badge bg-warning text-dark">' . $languageService->get('status_benched') . '</span>';
                    break;
                default:
                    $status_badge = '<span class="badge bg-secondary">' . $languageService->get('status_unknown') . '</span>';
                    break;
            }
            $raid_url = SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=show&id=" . $entry['id']);

            $attendance_history_html .= '<tr>
                <td><a href="' . $raid_url . '">' . htmlspecialchars($entry['title']) . '</a></td>
                <td>' . htmlspecialchars($entry['character_name'] ?? 'N/A') . '</td>
                <td>' . date('d.m.Y', strtotime($entry['event_time'])) . '</td>
                <td>' . $status_badge . '</td>
            </tr>';
        }
    }
    // --- LOOT-HISTORIE ---
    $lootManager = new LootManager($_database);
    $loot_result = $lootManager->getHistoryForUser($userID);

    $loot_history_html = '';

    if (empty($loot_result)) {
        $loot_history_html = '<tr><td colspan="6" class="text-center text-muted">' . $languageService->get('msg_no_loot_history') . '</td></tr>';
    } else {
        foreach ($loot_result as $entry) {
        // Wishlist-Badge nur, wenn Feld existiert und exakt 2 ist
        $ows = array_key_exists('original_wish_status', $entry) ? (int)$entry['original_wish_status'] : null;
        $wishlistBadge = '';
        if ($ows === 2) {
            $wishlistBadge = ' <span class="badge bg-warning text-dark ms-1" title="Wishlist">'
                        . '  <i class="bi bi-star-fill"></i> '
                        .      htmlspecialchars($languageService->get('badge_wishlist'))
                        . '</span>';
        }

        // Datum robust formatieren
        $dateOut = '';
        if (!empty($entry['looted_at'])) {
            $t = is_numeric($entry['looted_at']) ? (int)$entry['looted_at'] : strtotime((string)$entry['looted_at']);
            if ($t) { $dateOut = date('d.m.Y', $t); }
        }

        $loot_history_html .= '<tr>'
            . '<td>' . htmlspecialchars($entry['item_name']) . $wishlistBadge . '</td>'
            . '<td>' . htmlspecialchars($entry['slot'] ?? 'N/A') . '</td>'
            . '<td>' . htmlspecialchars($entry['character_name'] ?? 'N/A') . '</td>'
            . '<td>' . htmlspecialchars(stripslashes($entry['boss_name'] ?? 'N/A')) . '</td>'
            . '<td>'
            . (!empty($entry['raid_title'])
                ? htmlspecialchars($entry['raid_title'])
                : '<span class="text-muted">Raid wurde entfernt</span>')
            . '</td>'
            . '<td>' . htmlspecialchars($dateOut ?: '–') . '</td>'
        . '</tr>';
    }
    }

    $data_array = [
        'title' => $languageService->get('title_my_stats'),
        'personal_raid_statistic' => $languageService->get('personal_raid_statistic'),
        'attendance_rate' => $languageService->get('attendance_rate'),
        'present' => $languageService->get('present'),
        'late' => $languageService->get('late'),
        'absent' => $languageService->get('absent'),
        'attendance_history' => $languageService->get('attendance_history'),
        'character' => $languageService->get('character'),
        'date' => $languageService->get('date'),
        'status' => $languageService->get('status'),
        'myloot_history' => $languageService->get('myloot_history'),
        'item' => $languageService->get('item'),
        'slot' => $languageService->get('slot'),
        'count_present' => $count_present,
        'count_late' => $count_late,
        'count_absent' => $count_absent,
        'attendance_percentage' => $attendance_percentage,
        'attendance_note' => $attendance_note,
        'attendance_history_html' => $attendance_history_html,
        'loot_history_html' => $loot_history_html
    ];
    echo $tpl->loadTemplate("raidplaner", "my_stats_page", $data_array, 'plugin');
    break;
    case 'archive':
    $raids_per_page = 20;
    $current_page = (int)($_GET['page'] ?? 1);
    if ($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $raids_per_page;

    $total_raids_result = $_database->query("SELECT COUNT(id) as total FROM plugins_raidplaner_events WHERE event_time < NOW()");
    $total_raids = (int)$total_raids_result->fetch_assoc()['total'];
    $total_pages = ceil($total_raids / $raids_per_page);

    $stmt = $_database->prepare("
        SELECT e.id,
            COALESCE(t.title, e.title) AS title,
            e.event_time
        FROM plugins_raidplaner_events e
        LEFT JOIN plugins_raidplaner_templates t ON t.id = e.template_id
        WHERE e.event_time < NOW()
        ORDER BY e.event_time DESC
        LIMIT ?, ?
    ");
    $stmt->bind_param("ii", $offset, $raids_per_page);
    $stmt->execute();
    $past_raids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $no_raids_message_html = '';
    $raid_list_container_html = '';

    if (empty($past_raids)) {
        $no_raids_message_html = '<div class="alert alert-info">' . $languageService->get('msg_no_past_raids_found') . '</div>';
    } else {
        $list_items_html = '';
        $entry_template = $tpl->loadTemplate("raidplaner", "archive_entry", [], 'plugin');
        foreach ($past_raids as $raid) {
            $placeholders = ['{details_url}', '{title}', '{date}'];
            $values = [
                SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=show&id=" . $raid['id']),
                htmlspecialchars((string)($raid['title'] ?? $languageService->get('unknown_raid')), ENT_QUOTES, 'UTF-8'),
                rp_format_dt(strtotime($raid['event_time']), 'd.m.Y - H:i')
                    . (stripos((string)$languageService->get('raidplaner_title'), 'raid planner') !== false
                        ? ''
                        : ' ' . $languageService->get('clock'))
            ];
            $list_items_html .= str_replace($placeholders, $values, $entry_template);
        }

        $pagination_html = '';
        if ($total_pages > 1) {
            $pagination_html .= '<nav><ul class="pagination justify-content-center">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                $page_url = SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=archive&page=" . $i);
                $pagination_html .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
            }
            $pagination_html .= '</ul></nav>';
        }

        $raid_list_container_html = '
            <div class="card">
                <div class="list-group list-group-flush">'
                . $list_items_html .
                '</div>
            </div>
            <div class="mt-4">'
                . $pagination_html .
                '</div>';
    }

    $data_array = [
        'title' => $languageService->get('title_archive'),
        'no_raids_message_html' => $no_raids_message_html,
        'raid_list_container_html' => $raid_list_container_html
    ];
    echo $tpl->loadTemplate("raidplaner", "archive", $data_array, 'plugin');
    break;

        default:
        $upcoming_raids_raw = $_database->query(
            "SELECT 
                e.id, 
                COALESCE(t.title, e.title) AS title, 
                e.event_time
            FROM plugins_raidplaner_events e
            LEFT JOIN plugins_raidplaner_templates t ON e.template_id = t.id
            WHERE e.event_time >= NOW() AND e.is_active = 1
            ORDER BY e.event_time ASC
            LIMIT 9"
        )->fetch_all(MYSQLI_ASSOC);

        $upcoming_raids_html = '';
        $raid_ids = array_column($upcoming_raids_raw, 'id');

        if (empty($raid_ids)) {
            $upcoming_raids_html = '<div class="col-12"><p class="text-muted p-3">' . $languageService->get('msg_no_upcoming_raids_found') . '</p></div>';
        } else {
            $placeholders = implode(',', $raid_ids);
            $setups_raw = $_database->query("
                SELECT
                    rs.event_id,
                    rs.needed_count,
                    rr.role_name,
                    rr.icon
                FROM plugins_raidplaner_setup rs
                JOIN plugins_raidplaner_roles rr ON rs.role_id = rr.id
                WHERE rs.event_id IN ($placeholders)
            ")->fetch_all(MYSQLI_ASSOC);
            $signups_raw = $_database->query("SELECT s.event_id, r.role_name, COUNT(*) as count FROM plugins_raidplaner_signups s JOIN plugins_raidplaner_roles r ON s.role_id = r.id WHERE s.event_id IN ($placeholders) AND s.status = '" . $languageService->get('status_signed_up') . "' GROUP BY s.event_id, r.role_name")->fetch_all(MYSQLI_ASSOC);

            $setups_by_raid = [];
            foreach ($setups_raw as $setup) {
                $setups_by_raid[$setup['event_id']][] = $setup;
            }
            $signups_by_raid_role = [];
            foreach ($signups_raw as $signup) {
                $signups_by_raid_role[$signup['event_id']][$signup['role_name']] = $signup['count'];
            }
            $data_array = [
                'clock' => $languageService->get('clock'),
                'total' => $languageService->get('total'),
                'details_signup' => $languageService->get('details_signup'),
            ];
            $card_template = $tpl->loadTemplate("raidplaner", "dashboard_entry", $data_array, 'plugin');
            $role_item_template = $tpl->loadTemplate("raidplaner", "dashboard_roles_signup", [], 'plugin');

            foreach ($upcoming_raids_raw as $raid) {
                $raid_id = $raid['id'];
                
                $total_needed = 0;
                $total_signed_up = 0;
                $role_details_html = '';

                if (isset($setups_by_raid[$raid_id])) {
                    foreach ($setups_by_raid[$raid_id] as $setup) {
                        $needed = (int)$setup['needed_count'];
                        $signed_up = (int)($signups_by_raid_role[$raid_id][$setup['role_name']] ?? 0);
                        $total_needed += $needed;
                        $total_signed_up += $signed_up;
                            
                        $role_progress_percent = ($needed > 0) ? round(($signed_up / $needed) * 100) : 0;
                        $icon_raw = isset($setup['icon']) ? (string)$setup['icon'] : '';
                        $icon_raw = trim($icon_raw, " \t\n\r\0\x0B\"'<>");
                        if ($icon_raw !== '' && preg_match('/\bbi-[a-z0-9-]+\b/i', $icon_raw, $m)) {
                            $role_icon = strtolower($m[0]);
                        } else {
                            $role_icon = match ($setup['role_name']) {
                                'Tank'   => 'bi-shield-fill',
                                'DD'     => 'bi-crosshair',
                                'Healer' => 'bi-heart-fill',
                                default  => 'bi-question-circle',
                            };
                        }

                        $role_key   = 'role_' . strtolower((string)$setup['role_name']);
                        $role_label = (string)$languageService->get($role_key);
                        if ($role_label === '' || $role_label === $role_key || $role_label === '['.$role_key.']') {
                            $role_label = (string)$setup['role_name'];
                        }

                        $placeholders_role = ['{role_icon}', '{role_name}', '{signed_up}', '{needed}', '{role_progress_percent}'];
                        $values_role = [
                            $role_icon,
                            $role_label,
                            $signed_up,
                            $needed,
                            $role_progress_percent
                        ];
                        $role_details_html .= str_replace($placeholders_role, $values_role, $role_item_template);
                    }
                }
                $total_progress_percent = ($total_needed > 0) ? round(($total_signed_up / $total_needed) * 100) : 0;
                    
                $raidDate = new DateTime($raid['event_time']);
                $now = new DateTime();
                $today   = new DateTime($now->format('Y-m-d'));
                $raidDay = new DateTime($raidDate->format('Y-m-d'));
                $diff = (int)$today->diff($raidDay)->format('%r%a');
                if ($diff === 0) {
                    $status = '<span class="badge bg-success">' . htmlspecialchars($languageService->get('status_today') ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
                } elseif ($diff === 1) {
                   $status = '<span class="badge bg-primary">' . htmlspecialchars($languageService->get('status_tomorrow') ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    $status = '<span class="badge bg-secondary">' . htmlspecialchars(sprintf($languageService->get('status_in_x_days') ?? '%s', (int)$diff), ENT_QUOTES, 'UTF-8') . '</span>';
                }

                $placeholders_card = [
                    '{title}', '{status_badge_html}', '{time}', '{total_signed_up}', '{total_needed}',
                    '{total_progress_percent}', '{role_details_html}', '{details_url}'
                ];
                $values_card = [
                    htmlspecialchars($raid['title']), $status, rp_format_dt(strtotime($raid['event_time']), 'd.m.Y H:i'),
                    $total_signed_up, $total_needed, $total_progress_percent, $role_details_html,
                    SeoUrlHandler::convertToSeoUrl("index.php?site=raidplaner&action=show&id=" . $raid_id)
                ];
                $upcoming_raids_html .= str_replace($placeholders_card, $values_card, $card_template);
            }
        }
        $data_array = [
            'title' => $languageService->get('title_dashboard'),
            'upcoming_raids_title' => $languageService->get('upcoming_raids_title'),
            'upcoming_raids_html' => $upcoming_raids_html,
        ];
        echo $tpl->loadTemplate("raidplaner", "dashboard", $data_array, 'plugin');
        break;
    }
?>
<script>
const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
const lang = {
    errorPrefix: '<?php echo $languageService->get('js_error_prefix'); ?>',
    errorUnknown: '<?php echo $languageService->get('js_error_unknown'); ?>',
    errorSaveStatus: '<?php echo $languageService->get('js_error_save_status'); ?>'
};

document.addEventListener('DOMContentLoaded', function() {
    
    // === Event Delegation für Klicks (z.B. Main-Star) ===
    document.body.addEventListener('click', function(e) {
        const starBtn = e.target.closest('.main-star');
        if (starBtn) {
            const charId = starBtn.dataset.charId;
            const selfIcon = starBtn.querySelector('i');

            fetch('index.php?site=raidplaner&action=set_main_char', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    character_id: charId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.main-star i').forEach(i => {
                        i.classList.remove('bi-star-fill', 'text-warning');
                        i.classList.add('bi-star');
                    });
                    selfIcon.classList.remove('bi-star');
                    selfIcon.classList.add('bi-star-fill', 'text-warning');
                } else {
                    alert(lang.errorPrefix + (data.message || lang.errorUnknown));
                }
            })
        }
    });

    // === Event Delegation für Änderungen (z.B. Gear-Status) ===
    document.body.addEventListener('change', function(e) {
        const select = e.target.closest('.gear-status-select');
        if (select) {
            const itemId = select.dataset.itemId;
            const charId = select.dataset.charId;
            const statusValue = select.value;

            const formData = new FormData();
            formData.append('character_id', charId);
            formData.append('item_id', itemId);
            formData.append('status', statusValue);
            formData.append('csrf_token', csrfToken);

            fetch('index.php?site=raidplaner&action=update_gear_status', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const iconSpan = document.querySelector(`.status-icon[data-item-id-icon="${itemId}"] i`);
                    if(iconSpan) {
                        iconSpan.className = 'bi';
                        switch(statusValue) {
                            case 'wishlist':
                                iconSpan.classList.add('bi-star-fill', 'text-warning');
                                break;
                            default:
                                iconSpan.classList.add('bi-x-circle', 'text-danger');
                        }
                    }
                } else {
                    alert(lang.errorPrefix + (data.error || lang.errorSaveStatus));
                }
            })
        }
    });

});
</script>