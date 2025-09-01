<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\RoleManager;
use nexpell\SeoUrlHandler;

global $languageService, $_database, $tpl;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('achievements');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Die Core-Engine einbinden
require_once dirname(__FILE__) . '/engine_achievements.php';

// Header ausgeben
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$data_array_head = [
    'class'    => htmlspecialchars($config['selected_style']),
    'title'    => $languageService->get('achievements_title'),
    'subtitle' => 'Achievements'
];
echo $tpl->loadTemplate("achievements", "head", $data_array_head, 'plugin');

// --- Helper Functions ---

/**
 * Berechnet den Fortschritt für zeitbasierte Achievements.
 * @return array|null Ein Array mit Fortschrittsdaten oder null bei ungültigem Datum.
 */
/*function achievements_helper_calculate_registration_progress(?string $reg_date_str, int $required_value, string $time_unit): ?array {
    global $languageService;
    if (empty($reg_date_str) || $required_value < 0) {
        return null;
    }

    $reg = new DateTimeImmutable($reg_date_str);
    $now = new DateTimeImmutable('now');
    $days_since = $reg->diff($now)->days; // gesamte Tage seit Registrierung

    $current_units = 0;
    $required_days = 0;
    $unit_key = '';

    switch ($time_unit) {
        case 'days':
            $current_units = $days_since;
            $required_days = $required_value;
            $unit_key = 'day';
            break;

        case 'weeks':
            $current_units = floor($days_since / 7);
            $required_days = $required_value * 7;
            $unit_key = 'week';
            break;

        case 'months':
            $current_units = floor($days_since / 30.44); // Durchschnitt
            $required_days = (int)round($required_value * 30.44);
            $unit_key = 'month';
            break;

        case 'years':
            $current_units = floor($days_since / 365);   // Jahre ≈ 365 Tage
            $required_days = $required_value * 365;
            $unit_key = 'year';
            break;

        default:
            return null;
    }

    $is_unlocked = $days_since >= $required_days;

    // Plural/Singular
    $unit_name = method_exists($languageService, 'getPlural')
        ? $languageService->getPlural($unit_key, $required_value)
        : ($required_value === 1 ? $languageService->get($unit_key) : $languageService->get($unit_key . 's'));

    return [
        'current_days'    => $days_since,
        'required_days'   => $required_days,
        'is_unlocked'     => $is_unlocked,
        'current_units'   => $current_units,
        'required_units'  => $required_value,
        'unit'            => $time_unit,
        'requirement_text'=> $languageService->get('be_since') . ' ' . $required_value . ' ' . $unit_name . ' ' . $languageService->get('registered'),
    ];
}

*/



/**
 * Erstellt den HTML-Code für den Fortschrittsbalken.
 * @return string Der generierte HTML-Code.
 */
function achievements_helper_create_progress_bar(float $current_value, int $trigger_value, bool $is_unlocked, string $type): string {
    global $languageService;
    if ($trigger_value <= 0) {
        return '';
    }

    $progress_percent = min(100, ($current_value / $trigger_value) * 100);
    $remaining = max(0, $trigger_value - $current_value);
    
    if ($type === 'registration_time') {
         $progress_text = $is_unlocked ? $languageService->get('finished') : $languageService->get('about_days') . ' ' . floor($remaining) . ' ' . $languageService->get('days');
    } else {
         $progress_text = $is_unlocked ? $languageService->get('finished') : $languageService->get('about_days') . ' ' . $remaining . ' ' . $languageService->get('needed');
    }
   
    $progress_bar_class = $is_unlocked ? 'bg-success' : 'bg-warning';
    $rounded_percent = round($progress_percent);

    return <<<HTML
        <div class="progress" style="height: 20px;">
            <div class="progress-bar {$progress_bar_class}" role="progressbar" style="width: {$rounded_percent}%;" aria-valuenow="{$rounded_percent}" aria-valuemin="0" aria-valuemax="100">{$rounded_percent}%</div>
        </div>
        <small class="text-center d-block mt-1">{$progress_text}</small>
    HTML;
}

/**
 * Generiert die öffentliche Übersichtsseite aller Achievements mit neuer Sortierung.
 */

    global $_database, $languageService, $tpl;
    
    $userID = $_SESSION['userID'] ?? 0;
    if ($userID === 0) {
        echo "<div class='alert alert-info'>" . $languageService->get('have_to_be_registered') . "</div>";
        return;
    }

    // Alle relevanten Daten mit möglichst wenigen Abfragen laden
    $clan_name_result = $_database->query("SELECT clanname FROM settings LIMIT 1");
    $clan_name = $clan_name_result ? htmlspecialchars(mysqli_fetch_assoc($clan_name_result)['clanname']) : 'Clan-Name';
    
    $settings_result = $_database->query("SELECT setting_key, setting_value FROM plugins_achievements_settings");
    $settings = [];
    while($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

// --- User-Basisdaten laden ---
$stmt = $_database->prepare("SELECT userID, username FROM users WHERE userID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$user_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Rollen separat laden ---
$stmt = $_database->prepare("
    SELECT r.role_name 
    FROM user_role_assignments ura
    JOIN user_roles r ON ura.roleID = r.roleID
    WHERE ura.userID = ?
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$roles_result = $stmt->get_result();

$roles = [];
while ($row = $roles_result->fetch_assoc()) {
    $roles[] = $row['role_name'];
}
$stmt->close();

$user_stats = achievements_get_user_stats($userID);

// Default-Werte setzen, falls Key fehlt
$user_stats['total_points']     = $user_stats['total_points'] ?? 0;
$user_stats['level']            = $user_stats['level'] ?? 0;
$user_stats['counts']           = $user_stats['counts'] ?? [];
$user_stats['points_per_category'] = $user_stats['points_per_category'] ?? [];
$user_stats['labels']           = $user_stats['labels'] ?? [];
$user_stats['reg_date']         = $user_stats['reg_date'] ?? date('Y-m-d H:i:s');
$user_stats['bonus_points']     = $user_stats['bonus_points'] ?? 0;

// Rollen anhängen (falls separat geladen)
$user_stats['roles'] = $roles ?? [];







    
    // Eine einzige Abfrage für alle Achievements (ohne DB-Sortierung, da wir in PHP sortieren)
    $all_achievements_result = $_database->query("SELECT * FROM plugins_achievements");
    $all_achievements = $all_achievements_result->fetch_all(MYSQLI_ASSOC);

    $bonus_achievement = null;
    $regular_achievements = array_filter($all_achievements, function($ach) use (&$bonus_achievement) {
        if ($ach['type'] === 'bonus_points') {
            $bonus_achievement = $ach;
            return false;
        }
        return true;
    });

    if (empty($regular_achievements) && $user_stats['bonus_points'] == 0) {
        echo "<div class='alert alert-info'>" . $languageService->get('no_achievements') . "</div>";
        return;
    }

    $processed_achievements = [];
    $image_path = 'includes/plugins/achievements/images/icons/';

    foreach ($regular_achievements as $row) {
        $current_value = 0;
        $trigger_value = (int)$row['trigger_value'];
        $requirement_text = '';
        $is_unlocked = false;
        $is_manually_awarded = isset($manual_awards[$row['id']]);
        
        switch ($row['type']) {
            case 'level':
            case 'points':
            case 'activity_count':
            case 'category_points':
                $trigger_value = (int)$row['trigger_value'];
                if ($row['type'] === 'points') {
                    $current_value = $user_stats['total_points'];
                    $requirement_text = $languageService->get('collect') . ' ' . $trigger_value . ' ' . $languageService->get('points');
                } elseif ($row['type'] === 'level') {
                    $current_value = $user_stats['level'];
                    $requirement_text = $languageService->get('reach_lvl') . ' ' . $trigger_value;
                } else {
                    $key = $row['trigger_condition'];
                    $source = ($row['type'] === 'category_points') ? $user_stats['points_per_category'] : $user_stats['counts'];
                    $current_value = $source[$key] ?? 0;
                    $display_name = htmlspecialchars($user_stats['labels'][$key] ?? $key);

                    switch ($key) {
                        case 'Likes': $requirement_text = $languageService->get('give') . ' ' . $trigger_value . ' ' . $display_name; break;
                        case 'Kommentare': case 'Artikel': case 'Forum': $requirement_text = $languageService->get('write') . ' ' . $trigger_value . ' ' . $display_name; break;
                        case 'Downloads': $requirement_text = $trigger_value . ' ' . $display_name; break;
                        case 'Logins': $requirement_text = $languageService->get('login') . ' ' . $trigger_value . ' ' . $languageService->get('times'); break;
                        case 'Clan-Regeln': $requirement_text = $languageService->get('define') . ' ' . $trigger_value . ' ' . $display_name; break;
                        case 'Sponsoren': $requirement_text = $languageService->get('present') . ' ' . $trigger_value . ' ' . $display_name; break;
                        case 'Partners': case 'Links': $requirement_text = $languageService->get('add2') . ' ' . $trigger_value . ' ' . $display_name . ' ' . $languageService->get('add3'); break;
                        default: $text_action = ($row['type'] === 'category_points') ? $languageService->get('collect') : $languageService->get('create'); $requirement_text = sprintf('%s %d %s', $text_action, $trigger_value, $display_name); break;
                    }
                }
                $is_unlocked = ($current_value >= $trigger_value);
                break;




case 'role':
    $roles = $user_stats['roles'] ?? [];
    $required_roles = !empty($row['trigger_value']) ? array_map('trim', explode(',', $row['trigger_value'])) : [];
    $is_unlocked = !array_diff($required_roles, $roles);
    $row['description'] = str_replace('{clan_name}', $clan_name, $row['description']);
    $requirement_text = $languageService->get('require_role') . ' ' . htmlspecialchars($row['trigger_value']);
    break;



            case 'registration_time':
    // Berechnung des Fortschritts für ein Achievement,
    // das sich auf die Registrierungsdauer bezieht
    $reg_data = achievements_helper_calculate_registration_progress(
        $user_stats['reg_date'],   // Das Registrierungsdatum des Users
        $trigger_value,            // Zielwert (z. B. 30 Tage Mitgliedschaft)
        $row['trigger_condition']  // Bedingung (z. B. >=)
    );

    // Wenn die Hilfsfunktion etwas zurückgibt, Werte übernehmen
    if ($reg_data) {
        $current_value = $reg_data['current_days'];       // wie viele Tage seit Registrierung vergangen sind
        $is_unlocked   = $reg_data['is_unlocked'];        // true/false, ob Achievement schon erreicht ist
        $requirement_text = $reg_data['requirement_text']; // Text, z. B. „Sei 30 Tage registriert“
        $trigger_value = $reg_data['required_days'];      // wie viele Tage nötig sind (kann überschrieben werden)
    }
    break;
        }

        if ($is_manually_awarded) {
            $is_unlocked = true;
        }

        $progress_percent = ($trigger_value > 0) ? min(100, ($current_value / $trigger_value) * 100) : 0;
        
        $processed_achievements[] = [
            'data' => $row,
            'is_unlocked' => $is_unlocked,
            'is_manually_awarded' => $is_manually_awarded,
            'current_value' => $current_value,
            'trigger_value' => $trigger_value,
            'progress_percent' => $progress_percent,
            'requirement_text' => $requirement_text,
        ];
    }

    usort($processed_achievements, function ($a, $b) {
        if ($a['is_unlocked'] !== $b['is_unlocked']) {
            return $b['is_unlocked'] <=> $a['is_unlocked'];
        }
        if (!$a['is_unlocked']) {
            return $b['progress_percent'] <=> $a['progress_percent'];
        }
        return strcmp($a['data']['name'], $b['data']['name']);
    });

    $all_achievements_html = '';
    foreach ($processed_achievements as $ach) {
        $row = $ach['data'];
        
        // Icon-Logik
        $image_name = $row['image'];
        if (!$ach['is_unlocked'] && ($settings['hide_locked_icon'] ?? 'yes') === 'yes') {
            $image_name = $settings['custom_locked_icon'] ?? 'locked-archv.png';
        }
        $full_image_path = $image_path . $image_name;

        // Beschreibung und Fortschrittsbalken
        $description = !empty($row['allow_html'])
            ? nl2br(strip_tags($row['description'], '<b></b><i><u>'))
            : nl2br(htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'));
        
        $progress_html = '';
        if ($ach['is_manually_awarded']) {
            $admin_name = htmlspecialchars($manual_awards[$row['id']]);
            $description .= '<br><small class="text-muted fst-italic">' . $languageService->get('assigned_manually') . ' ' . $admin_name . '</small>';
        } elseif ($row['type'] !== 'role' && $row['type'] !== 'manual') {
            $progress_html = achievements_helper_create_progress_bar((float)$ach['current_value'], (int)$ach['trigger_value'], $ach['is_unlocked'], $row['type']);
        }
        
        $should_display = false;
        if ($row['type'] === 'manual') {
            if ($ach['is_unlocked']) $should_display = true;
        } else {
            if ($row['show_in_overview'] || $ach['is_unlocked']) $should_display = true;
        }

        if ($should_display) {
            $data_array_item = [
                'image_url' => htmlspecialchars($full_image_path),
                'name' => htmlspecialchars($row['name']),
                'description' => $description,
                'requirement' => $ach['requirement_text'],
                'progress_html' => $progress_html,
                'unlocked_class' => $ach['is_unlocked'] ? 'unlocked' : 'locked'
            ];
            $all_achievements_html .= $tpl->loadTemplate("achievements", "overview_item", $data_array_item, 'plugin');
        }
    }

    // Bonus-Achievement am Ende hinzufügen, falls vorhanden
    if ($user_stats['bonus_points'] > 0 && $bonus_achievement) {
        $desc = nl2br(strip_tags($bonus_achievement['description'], '<b></b>'));
        $desc = str_replace('{points}', '<b>' . $user_stats['bonus_points'] . '</b>', $desc);
        
        $data_array_item = [
            'image_url' => htmlspecialchars($image_path . $bonus_achievement['image']),
            'name' => htmlspecialchars($bonus_achievement['name']),
            'description' => $desc,
            'requirement' => '',
            'progress_html' => '',
            'unlocked_class' => 'unlocked'
        ];
        $all_achievements_html .= $tpl->loadTemplate("achievements", "overview_item", $data_array_item, 'plugin');
    }
    
    echo $tpl->loadTemplate("achievements", "overview_content", ['achievements_list' => $all_achievements_html], 'plugin');

?>