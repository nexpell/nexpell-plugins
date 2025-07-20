<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('sponsors');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Sponsors'
];
    
echo $tpl->loadTemplate("sponsors", "head", $data_array, 'plugin');

// Sponsoren-Daten abrufen
$result = safe_query("SELECT * FROM plugins_sponsors WHERE is_active = 1 ORDER BY sort_order ASC");

// Sponsoren-Daten abrufen und Array aufbauen
$sponsors = [];
$levelColors = [
    'platin_sponsor'   => '#00bcd4',
    'gold_sponsor'     => '#ffc107',
    'silber_sponsor'   => '#adb5bd',
    'bronze_sponsor'   => '#cd7f32',
    'partner'          => '#6c757d',
    'unterstuetzer'    => '#999'
];

$imagePath = '/includes/plugins/sponsors/images/';

while ($ds = mysqli_fetch_array($result)) {
    $levelKey = strtolower(str_replace([' ', 'ü'], ['_', 'ue'], $ds['level']));

    $urlRaw = trim((string)($row['slug'] ?? ''));
        if ($urlRaw) {
            $urlCandidate = (stripos($urlRaw, 'http') === 0) ? $urlRaw : 'http://' . $urlRaw;
            $row['valid_url'] = filter_var($urlCandidate, FILTER_VALIDATE_URL) ? $urlCandidate : '';
        } else {
            $row['valid_url'] = '';
        }
        
        $slug[] = $row;

    $sponsors[] = [
        'id'    => (int)$ds['id'],
        'name'  => htmlspecialchars($ds['name']),
        'logo'  => $imagePath . htmlspecialchars($ds['logo']),
        'level' => $languageService->get($levelKey),
        'color' => $levelColors[$levelKey] ?? '#ccc',
        'slug'  => $slug
    ];
}

// Daten in $data_array zusammenfassen
$data_array = [
    'headline' => $languageService->get('headline'),
    'text'     => $languageService->get('text'),
    'sponsors' => $sponsors
];

echo $tpl->loadTemplate("sponsors", "main", $data_array, "plugin");
