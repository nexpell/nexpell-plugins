<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('links');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Links'
];
    
echo $tpl->loadTemplate("links", "head", $data_array, 'plugin');

// SQL-Abfrage
$sql = "SELECT l.*, c.title AS category, c.icon, c.id AS category_id
        FROM plugins_links l
        LEFT JOIN plugins_links_categories c ON l.category_id = c.id
        WHERE l.visible = 1
        ORDER BY l.category_id ASC, l.title ASC";

$result = $_database->query($sql);

$links = [];
$categoriesMap = [];
$categories = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Fallback-Bild setzen
        $row['image'] = !empty($row['image']) ? $row['image'] : 'assets/default_thumb.jpg';

        // URL prüfen und ggf. http:// voranstellen
        $urlRaw = trim((string)($row['url'] ?? ''));
        if ($urlRaw) {
            $urlCandidate = (stripos($urlRaw, 'http') === 0) ? $urlRaw : 'http://' . $urlRaw;
            $row['valid_url'] = filter_var($urlCandidate, FILTER_VALIDATE_URL) ? $urlCandidate : '';
        } else {
            $row['valid_url'] = '';
        }

        // Beschreibung prüfen und als Boolean speichern
        $descriptionRaw = trim((string)($row['description'] ?? ''));
        $row['description'] = $descriptionRaw; // Gecleant speichern für Template
        $row['has_description'] = !empty($descriptionRaw);

        // Weitere Flags
        $row['has_icon'] = !empty($row['icon']);
        $row['has_valid_url'] = !empty($row['valid_url']);

        $links[] = $row;

        // Kategorien sammeln
        $catId = $row['category_id'] ?? 0;
        $catTitle = $row['category'] ?? 'Unbekannt';

        if ($catId > 0 && !isset($categoriesMap[$catId])) {
            $categoriesMap[$catId] = $catTitle;
            $categories[] = [
                'id'    => $catId,
                'title' => $catTitle
            ];
        }
    }
}

// Daten für Template vorbereiten
$data_array = [
    'links' => $links,
    'categories' => $categories,
    'filter_all'     => $languageService->get('filter_all'),
    'link_no_description' => $languageService->get('link_no_description'),
    'link_visit' => $languageService->get('link_visit'),
    'link_invalid' => $languageService->get('link_invalid'),
];

// Template laden und ausgeben
echo $tpl->loadTemplate("links", "main", $data_array, "plugin");

