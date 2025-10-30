<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('partners');
$translate = new multiLanguage($lang);

// Styleklasse laden
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class' => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Partners'
];
echo $tpl->loadTemplate("partners", "head", $data_array, "plugin");

// Partnerdaten laden
$alertColors = ['primary', 'secondary', 'success', 'warning', 'danger', 'info'];
$filepath = "/includes/plugins/partners/images/";
$query = "SELECT * FROM plugins_partners WHERE is_active = 1 ORDER BY sort_order";
$result = $_database->query($query);

$partners = [];
$colorIndex = 0;

if ($result && $result->num_rows > 0) {
    while ($partner = $result->fetch_assoc()) {
        $name = htmlspecialchars($partner['name']);
        $logo = !empty($partner['logo']) ? $filepath . $partner['logo'] : $filepath . "no-image.jpg";
        $description = $partner['description'];
        $translate->detectLanguages($description);
        
        $colorKey = $alertColors[$colorIndex];
        $colorIndex = ($colorIndex + 1) % count($alertColors);

        $slug = '';
        $urlRaw = trim($partner['slug']);
        if (!empty($urlRaw)) {
            $urlCandidate = (stripos($urlRaw, 'http') === 0) ? $urlRaw : 'http://' . $urlRaw;
            if (filter_var($urlCandidate, FILTER_VALIDATE_URL)) {
                $slug = $urlCandidate;
            }
        }

        $description = $translate->getTextByLanguage($description);
        $partners[] = [
            'id'          => (int)$partner['id'],
            'name'        => $name,
            'logo'        => $logo,
            'description' => $description,
            'color'       => $colorKey,
            'slug'        => $slug,
            'learn_more'  => $languageService->get('learn_more'),
            'no_valid_link' => $languageService->get('no_valid_link'),
        ];
    }

    // Template-Daten übergeben
    $data_array = [
        'partners' => $partners
    ];

    echo $tpl->loadTemplate("partners", "main", $data_array, "plugin");

} else {
    // Keine Partner vorhanden → Hinweis anzeigen
    echo '<div class="alert alert-info">' . $languageService->get('no_partners_found') . '</div>';
}


?>
