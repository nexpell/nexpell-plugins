<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;
use nexpell\LanguageService;
global $languageService, $tpl, $_database;

// Sprachpakete
$lang = $languageService->detectLanguage();
$languageService->readPluginModule('raidplaner');

// Widget-Konfiguration absichern
if (!isset($cfg)) {
    $cfg = [];
} elseif ($cfg instanceof stdClass) {
    $cfg = (array)$cfg;
} elseif (!is_array($cfg)) {
    $cfg = [];
}

// Polyfill für rp_format_dt()
if (!function_exists('rp_format_dt')) {
    function rp_format_dt($ts, string $format = 'd.m.Y H:i'): string {
        if (!is_numeric($ts)) { $ts = strtotime((string)$ts); }
        return $ts ? date($format, (int)$ts) : '';
    }
}

// Headstyle
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class  = htmlspecialchars($config['selected_style'] ?? '', ENT_QUOTES, 'UTF-8');

// Titel
$title = (isset($cfg['title']) && $cfg['title'] !== '')
    ? (string)$cfg['title']
    : (string)$languageService->get('upcoming_raids_title');

// Anzahl Items (Content-Widget: default 3)
$limit = isset($cfg['limit']) ? max(1, (int)$cfg['limit']) : 3;

// Sprachabhängiges Zeitformat
$langCode   = $languageService->detectLanguage();
$isEnglish  = (is_string($langCode) && stripos($langCode, 'en') === 0);
$datetimeFmt = $isEnglish ? 'M d, Y g:i A' : 'd.m.Y H:i';
$clockLabel  = $isEnglish ? '' : $languageService->get('clock');

// Header
echo $tpl->loadTemplate("raidplaner", "widget_sidebar_upperhead", [
    'class'    => $class,
    'title'    => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
    'subtitle' => 'Raidplaner'
], 'plugin');

// Daten laden
$rows = $_database->query("
    SELECT e.id, COALESCE(t.title, e.title) AS title, e.event_time
    FROM plugins_raidplaner_events e
    LEFT JOIN plugins_raidplaner_templates t ON e.template_id = t.id
    WHERE e.event_time >= NOW() AND e.is_active = 1
    ORDER BY e.event_time ASC
    LIMIT " . (int)$limit
)->fetch_all(MYSQLI_ASSOC);

if (!$rows || count($rows) === 0) {
    echo '<div class="p-2 small text-muted">' . htmlspecialchars($languageService->get('msg_no_upcoming_raids_found'), ENT_QUOTES, 'UTF-8') . '</div>';
    return;
}

// Setups und Signups
$raidIds = array_map('intval', array_column($rows, 'id'));
$idList  = implode(',', $raidIds);

$setups = $_database->query("
    SELECT rs.event_id, rs.needed_count, rr.role_name, rr.icon
    FROM plugins_raidplaner_setup rs
    JOIN plugins_raidplaner_roles rr ON rs.role_id = rr.id
    WHERE rs.event_id IN ($idList)
")->fetch_all(MYSQLI_ASSOC);

$signups = $_database->query("
    SELECT s.event_id, r.role_name, COUNT(*) AS count
    FROM plugins_raidplaner_signups s
    JOIN plugins_raidplaner_roles r ON s.role_id = r.id
    WHERE s.event_id IN ($idList) AND s.status = 'accepted'
    GROUP BY s.event_id, r.role_name
")->fetch_all(MYSQLI_ASSOC);

$setupsByRaid = [];
foreach ($setups as $s) {
    $setupsByRaid[(int)$s['event_id']][] = $s;
}

$signupsByRaidRole = [];
foreach ($signups as $su) {
    $signupsByRaidRole[(int)$su['event_id']][$su['role_name']] = (int)$su['count'];
}

// Rollen-Zeilenvorlage
$roleItemTpl = $tpl->loadTemplate('raidplaner', 'dashboard_roles_signup', [], 'plugin');

// Cards bauen
$htmlCards = [];
$n = 0;

foreach ($rows as $r) {
    $raidId    = (int)$r['id'];
    $raidTitle = (string)$r['title'];
    $raidTime  = (string)$r['event_time'];

    $totalNeeded = 0;
    $totalSigned = 0;
    $rolesHtml   = '';

    if (!empty($setupsByRaid[$raidId])) {
        foreach ($setupsByRaid[$raidId] as $st) {
            $roleName = (string)$st['role_name'];
            $needed   = (int)$st['needed_count'];
            $signed   = (int)($signupsByRaidRole[$raidId][$roleName] ?? 0);

            $totalNeeded += $needed;
            $totalSigned += $signed;

            $rolePct = ($needed > 0) ? round(($signed / $needed) * 100) : 0;

            $roleKey   = 'role_' . strtolower($roleName);
            $roleLabel = (string)$languageService->get($roleKey);
            if ($roleLabel === '' || $roleLabel === $roleKey || $roleLabel === '['.$roleKey.']') {
                $roleLabel = $roleName;
            }

            $rolesHtml .= str_replace(
                ['{role_icon}', '{role_name}', '{signed_up}', '{needed}', '{role_progress_percent}'],
                ['bi-dot', $roleLabel, $signed, $needed, $rolePct],
                $roleItemTpl
            );
        }
    }

    $totalPct = ($totalNeeded > 0) ? round(($totalSigned / $totalNeeded) * 100) : 0;

    // Status-Badge (Heute/Morgen/in X Tagen)
    $raidDate = new DateTime($raidTime);
    $today    = new DateTime((new DateTime())->format('Y-m-d'));
    $raidDay  = new DateTime($raidDate->format('Y-m-d'));
    $diffDays = (int)$today->diff($raidDay)->format('%r%a');

    if ($diffDays === 0) {
        $status = '<span class="badge bg-success">' . htmlspecialchars((string)$languageService->get('status_today'), ENT_QUOTES, 'UTF-8') . '</span>';
    } elseif ($diffDays === 1) {
        $status = '<span class="badge bg-primary">' . htmlspecialchars((string)$languageService->get('status_tomorrow'), ENT_QUOTES, 'UTF-8') . '</span>';
    } else {
        $fmt = (string)$languageService->get('status_in_x_days');
        if ($fmt === '' || $fmt === '[status_in_x_days]') { $fmt = '%s d'; }
        $status = '<span class="badge bg-secondary">' . sprintf($fmt, $diffDays) . '</span>';
    }

    // Details-URL (SEO-Fallback)
    $detailsUrl = 'index.php?site=raidplaner&action=show&id=' . $raidId;
    if (class_exists('\\nexpell\\SeoUrlHandler')) {
        $detailsUrl = \nexpell\SeoUrlHandler::convertToSeoUrl($detailsUrl);
    }

    // Cards-Ausgabe
    $htmlCards[] = $tpl->loadTemplate('raidplaner', 'dashboard_entry', [
        'n'                      => ++$n,
        'title'                  => htmlspecialchars($raidTitle, ENT_QUOTES, 'UTF-8'),
        'status_badge_html'      => $status,
        'time'                   => rp_format_dt(strtotime($raidTime), $datetimeFmt),
        'total_signed_up'        => (int)$totalSigned,
        'total_needed'           => (int)$totalNeeded,
        'total_progress_percent' => (int)$totalPct,
        'role_details_html'      => $rolesHtml,
        'details_url'            => $detailsUrl,
        'details_signup'         => $languageService->get('details_signup'),
        'clock'                  => $clockLabel,
        'total'                  => $languageService->get('total'),
    ], 'plugin');
}

echo '<div class="row g-4">'. implode("\n", $htmlCards) .'</div>';
?>