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

// SICHERHEIT: $cfg immer definieren und in Array wandeln
if (!isset($cfg)) {
    $cfg = [];
} elseif ($cfg instanceof stdClass) {
    $cfg = (array) $cfg;
} elseif (!is_array($cfg)) {
    $cfg = [];
}

if (!function_exists('rp_format_dt')) {
    function rp_format_dt($ts, string $format = 'd.m.Y H:i'): string {
        if (!is_numeric($ts)) { $ts = strtotime((string)$ts); }
        return $ts ? date($format, (int)$ts) : '';
    }
}

// Template + Headstyle
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style'] ?? '');

// Header-Daten
$widgetTitle = isset($cfg['title']) && $cfg['title'] !== ''
    ? $cfg['title']
    : $languageService->get('upcoming_raids_title');

$data_array = [
    'class'    => $class,
    'title'    => $widgetTitle,
    'subtitle' => 'Raidplaner'
];

echo $tpl->loadTemplate("raidplaner", "widget_sidebar_upperhead", $data_array, 'plugin');

// Admin-Widget-Settings über $cfg
$limit = isset($cfg['limit']) ? (int)$cfg['limit'] : 5;
$title = (isset($cfg['title']) && $cfg['title'] !== '') ? (string)$cfg['title'] : (string)$languageService->get('upcoming_raids_title');

// Carousel/Autoplay
$interval_ms    = isset($cfg['interval']) ? (int)$cfg['interval'] : 5000;
$autoplay       = isset($cfg['autoplay']) ? (bool)$cfg['autoplay'] : true;
$pause_on_hover = isset($cfg['pause_on_hover']) ? (bool)$cfg['pause_on_hover'] : true;

// Daten holen
$upcoming_raids_raw = $_database->query(
    "SELECT 
        e.id,
        COALESCE(t.title, e.title) AS title,
        e.event_time
     FROM plugins_raidplaner_events e
     LEFT JOIN plugins_raidplaner_templates t ON e.template_id = t.id
     WHERE e.event_time >= NOW() AND e.is_active = 1
     ORDER BY e.event_time ASC
     LIMIT " . (int)$limit
)->fetch_all(MYSQLI_ASSOC);

echo $tpl->loadTemplate('raidplaner', 'widget_sidebar_head', [
], 'plugin');

if (!$upcoming_raids_raw || count($upcoming_raids_raw) === 0) {
    echo '<div class="p-2 small text-muted">' . htmlspecialchars($languageService->get('msg_no_upcoming_raids_found')) . '</div>';
    echo $tpl->loadTemplate('raidplaner', 'widget_sidebar_foot', [], 'plugin');
    return;
}

$raid_ids     = array_column($upcoming_raids_raw, 'id');
$placeholders = implode(',', array_map('intval', $raid_ids));

// Setups/Signups
$setups_raw = $_database->query("
    SELECT rs.event_id, rs.needed_count, rr.role_name, rr.icon
    FROM plugins_raidplaner_setup rs
    JOIN plugins_raidplaner_roles rr ON rs.role_id = rr.id
    WHERE rs.event_id IN ($placeholders)
")->fetch_all(MYSQLI_ASSOC);

$signups_raw = $_database->query("
    SELECT s.event_id, r.role_name, COUNT(*) AS count
    FROM plugins_raidplaner_signups s
    JOIN plugins_raidplaner_roles r ON s.role_id = r.id
    WHERE s.event_id IN ($placeholders) AND s.status = 'accepted'
    GROUP BY s.event_id, r.role_name
")->fetch_all(MYSQLI_ASSOC);

$setups_by_raid = [];
foreach ($setups_raw as $setup) {
    $setups_by_raid[(int)$setup['event_id']][] = $setup;
}

$signups_by_raid_role = [];
foreach ($signups_raw as $signup) {
    $signups_by_raid_role[(int)$signup['event_id']][$signup['role_name']] = (int)$signup['count'];
}

// vorhandenes Rollen-Teil-Template
$role_item_template = $tpl->loadTemplate('raidplaner', 'dashboard_roles_signup', [], 'plugin');

$n = 0;
$items_html = [];

// Sprachabhängiges Zeitformat
$isEnglish = (stripos((string)$languageService->get('raidplaner_title'), 'raid planner') !== false)
          || (is_string($lang) && stripos($lang, 'en') === 0);

$datetimeFmt = $isEnglish ? 'M d, Y g:i A' : 'd.m.Y H:i';
$clockLabel  = $isEnglish ? '' : $languageService->get('clock');

foreach ($upcoming_raids_raw as $raid) {
    $raid_id   = (int)$raid['id'];
    $raidTitle = (string)$raid['title'];
    $raidTime  = (string)$raid['event_time'];

    $total_needed = 0;
    $total_signed = 0;
    $roles_html   = '';

    if (isset($setups_by_raid[$raid_id])) {
        foreach ($setups_by_raid[$raid_id] as $setup) {
            $roleName = (string)$setup['role_name'];
            $needed   = (int)$setup['needed_count'];
            $signed   = (int)($signups_by_raid_role[$raid_id][$roleName] ?? 0);

            $total_needed += $needed;
            $total_signed += $signed;

            $role_progress_percent = ($needed > 0) ? round(($signed / $needed) * 100) : 0;

            $role_key   = 'role_' . strtolower($roleName);
            $role_label = (string)$languageService->get($role_key);
            if ($role_label === '' || $role_label === $role_key || $role_label === '['.$role_key.']') {
                $role_label = $roleName;
            }

            $placeholders_role = ['{role_icon}', '{role_name}', '{signed_up}', '{needed}', '{role_progress_percent}'];
            $values_role       = ['bi-dot', $role_label, $signed, $needed, $role_progress_percent];
            $roles_html       .= str_replace($placeholders_role, $values_role, $role_item_template);
        }
    }

    $total_progress_percent = ($total_needed > 0) ? round(($total_signed / $total_needed) * 100) : 0;

    // Badge: Heute / Morgen / in X Tagen
    $raidDate = new DateTime($raidTime);
    $now      = new DateTime();
    $today    = new DateTime($now->format('Y-m-d'));
    $raidDay  = new DateTime($raidDate->format('Y-m-d'));
    $diff     = (int)$today->diff($raidDay)->format('%r%a');

    if ($diff === 0) {
        $status = '<span class="badge bg-success">' . htmlspecialchars((string)$languageService->get('status_today'), ENT_QUOTES, 'UTF-8') . '</span>';
    } elseif ($diff === 1) {
        $status = '<span class="badge bg-primary">' . htmlspecialchars((string)$languageService->get('status_tomorrow'), ENT_QUOTES, 'UTF-8') . '</span>';
    } else {
        $fmt = (string)$languageService->get('status_in_x_days');
        if ($fmt === '' || $fmt === '[status_in_x_days]') { $fmt = '%s d'; }
        $status = '<span class="badge bg-secondary">' . sprintf($fmt, $diff) . '</span>';
    }

    // Details-URL mit SEO-Fallback
    $detailsUrl = 'index.php?site=raidplaner&action=show&id=' . $raid_id;
    if (class_exists('\\nexpell\\SeoUrlHandler')) {
        $detailsUrl = \nexpell\SeoUrlHandler::convertToSeoUrl($detailsUrl);
    }
    // Ausgabe
    $items_html[] = $tpl->loadTemplate('raidplaner', 'widget_sidebar_content', [
        'n'                      => ++$n,
        'title'                  => htmlspecialchars($raidTitle, ENT_QUOTES, 'UTF-8'),
        'status_badge_html'      => $status,
        'total_signed_up'        => (int)$total_signed,
        'total_needed'           => (int)$total_needed,
        'total_progress_percent' => (int)$total_progress_percent,
        'role_details_html'      => $roles_html,
        'details_url'            => $detailsUrl,
        'details_signup'         => $languageService->get('details_signup'),
        'time'                   => rp_format_dt(strtotime($raidTime), $datetimeFmt),
        'clock'                  => $clockLabel,
        'total'                  => $languageService->get('total'),
    ], 'plugin');
}

// Ein Item pro Slide
$carouselId = 'rdplnrCarousel_' . bin2hex(random_bytes(3));
$interval   = isset($interval_ms) ? (int)$interval_ms : 5000;
$pauseAttr  = (!isset($pause_on_hover) || $pause_on_hover) ? 'hover' : 'false';

if (count($items_html) <= 1) {
    echo implode('', $items_html);
} else {
    echo '<div id="'. $carouselId .'" class="carousel slide rdplnr-carousel"'
       . ' data-bs-ride="carousel"'
       . ' data-bs-interval="'. $interval .'"'
       . ' data-bs-pause="'. $pauseAttr .'">';

    // Slides
    echo '<div class="carousel-inner">';
    foreach ($items_html as $idx => $html) {
        $active = ($idx === 0) ? ' active' : '';
        echo '<div class="carousel-item'. $active .'"><div class="p-1">'. $html .'</div></div>';
    }
    echo '</div>';

    // Indicators
    echo '<div class="carousel-indicators position-static my-2 rdplnr-indicators">';
    for ($i = 0; $i < count($items_html); $i++) {
        $active = ($i === 0) ? ' class="active" aria-current="true"' : '';
        echo '<button type="button" data-bs-target="#'. $carouselId .'" data-bs-slide-to="'. $i .'"'. $active .' aria-label="Slide '. ($i+1) .'"></button>';
    }
    echo '</div>';

    echo '</div>'; // end carousel

    // JS-Init-Fallback (falls data-Attribute wegen Lade-Reihenfolge nicht greifen)
    if ($autoplay) {
        echo '<script>(function(){var el=document.getElementById("'. $carouselId .'");'
           . 'if(window.bootstrap && bootstrap.Carousel){try{new bootstrap.Carousel(el,{interval:'.(int)$interval_ms.',pause:"'.$pauseAttr.'",ride:"carousel"});}catch(e){}}'
           . '})();</script>';
    }
}

echo $tpl->loadTemplate('raidplaner', 'widget_sidebar_foot', [], 'plugin');
?>