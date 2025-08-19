<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;
use nexpell\Database;

global $languageService, $_database;

$currentLang = $languageService->detectLanguage();
$languageService->readPluginModule('search');

$tpl = new Template();

// --- CONFIG: Style laden ---
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// --- HEAD ---
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => $languageService->get('subtitle'),
];
echo $tpl->loadTemplate("search", "head", $data_array, "plugin");

// --- Suchparameter ---
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';


// --- Helper-Funktionen ---
function esc_like($s) {
    $conn = $GLOBALS['_database'];
    $s = mysqli_real_escape_string($conn, $s);
    return str_replace(['%', '_'], ['\%', '\_'], $s);
}

function make_snippet_multi(string $text, array $terms, int $radius = 50): string {
    $lowerText = mb_strtolower($text);
    $matches = [];

    // Alle Trefferpositionen sammeln
    foreach ($terms as $term) {
        $term = trim($term);
        if ($term === '') continue;
        $termLower = mb_strtolower($term);
        $offset = 0;

        while (($pos = mb_stripos($lowerText, $termLower, $offset)) !== false) {
            $start = max(0, $pos - $radius);
            $end   = min(mb_strlen($text), $pos + mb_strlen($term) + $radius);
            $matches[] = [$start, $end];
            $offset = $pos + mb_strlen($termLower);
        }
    }

    if (empty($matches)) {
        $snippet = mb_substr($text, 0, $radius * 2) . (mb_strlen($text) > $radius * 2 ? '...' : '');
        $snippet = '<p>' . $snippet . '</p>';
        return tidy_html($snippet);
    }

    // Sortieren nach Start
    usort($matches, fn($a, $b) => $a[0] <=> $b[0]);

    // Überlappende Bereiche zusammenfassen
    $merged = [];
    foreach ($matches as $m) {
        if (empty($merged) || $m[0] > $merged[count($merged)-1][1]) {
            $merged[] = $m;
        } else {
            $merged[count($merged)-1][1] = max($merged[count($merged)-1][1], $m[1]);
        }
    }

    // Snippets ausschneiden
    $snippets = [];
    foreach ($merged as [$start, $end]) {
        $snippet = mb_substr($text, $start, $end - $start);
        if ($start > 0) $snippet = '...' . $snippet;
        if ($end < mb_strlen($text)) $snippet .= '...';
        $snippets[] = tidy_html('<p>' . $snippet . '</p>');
    }

    $final = implode("\n", $snippets);

    // Treffer hervorheben
    foreach ($terms as $term) {
        $final = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $final);
    }

    return $final;
}

// Hilfsfunktion zum Schließen offener HTML-Tags
function tidy_html(string $html): string {
    if (function_exists('tidy_parse_string')) {
        $tidy = tidy_parse_string($html, ['show-body-only' => true], 'UTF8');
        $tidy->cleanRepair();
        return (string)$tidy;
    }
    return $html; // fallback
}


function searchStatic($search) {
    global $_database;

    $terms = explode(' ', $search);
    $placeholders = [];
    $params = [];
    foreach ($terms as $term) {
        $placeholders[] = '`title` LIKE ?';
        $placeholders[] = '`content` LIKE ?';
        $params[] = "%$term%";
        $params[] = "%$term%";
    }
    $where = implode(' OR ', $placeholders);

    $sql = "SELECT 'settings_static' AS type, `staticID` AS id, `title`, `content` AS body
            FROM `settings_static`
            WHERE $where";

    $stmt = $_database->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLocalizedText($text, $lang = 'de') {
    if (preg_match("/\[\[lang:$lang\]\](.*?)(\[\[lang:|$)/s", $text, $matches)) {
        return trim($matches[1]);
    }
    // Fallback auf die erste Sprache, falls gewünscht:
    if (preg_match("/\[\[lang:.*?\]\](.*?)(\[\[lang:|$)/s", $text, $matches)) {
        return trim($matches[1]);
    }
    return $text;
}

// oder die OR-Variante, wenn du so suchen willst:
function buildLikeConditionsOr(array $columns, array $terms): array {
    $conditions = [];
    $params = [];

    foreach ($terms as $term) {
        foreach ($columns as $col) {
            $conditions[] = "`$col` LIKE ?";
            $params[] = "%$term%";
        }
    }

    return [implode(' OR ', $conditions), $params];
}

// --- Suchformular ---
$data_array = [
    'placeholder' => $languageService->get('placeholder'),
    'button'      => $languageService->get('button'),
    'query'       => htmlspecialchars($q),
];
echo $tpl->loadTemplate("search", "form", $data_array, "plugin");

if ($q === '') {
    echo $tpl->loadTemplate("search", "foot", [], "plugin");
    return;
}


// --- Suchbegriff holen (POST oder GET) ---
$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
$results = [];

if ($q !== '') {
    $words = preg_split('/\s+/', $q); 

    // --- 1) Core: Static Pages ---
    $sqlParts = [];
    $params = [];
    $types = '';

    foreach ($words as $word) {
        $sqlParts[] = "title LIKE ?";
        $sqlParts[] = "content LIKE ?";
        $param = "%$word%";
        $params[] = $param;
        $params[] = $param;
        $types .= 'ss';
    }

    $where = implode(' OR ', $sqlParts);

    $sqlPages = "
        SELECT 'settings_static' AS type, staticID AS id, title, content AS body
        FROM settings_static
        WHERE $where
    ";

    $stmtPages = $_database->prepare($sqlPages);
    $stmtPages->bind_param($types, ...$params);
    $stmtPages->execute();
    $resPages = $stmtPages->get_result();

    while ($r = $resPages->fetch_assoc()) {
        $results[] = $r;
    }
    $stmtPages->close();

    // --- 2) Plugins durchsuchen ---
    $pluginTables = [];
    $res = safe_query("SHOW TABLES LIKE 'plugins_%'");
    while ($row = mysqli_fetch_row($res)) {
        $pluginTables[] = $row[0];
    }

    $terms = array_filter(array_map('trim', explode(' ', $q)));

    foreach ($pluginTables as $table) {
        $cols = [];
        $resCols = safe_query("SHOW COLUMNS FROM `$table`");
        while ($c = mysqli_fetch_assoc($resCols)) $cols[] = $c['Field'];

        // ID, Titel, Content bestimmen
        if ($table === 'plugins_forum_threads') {
            $idCol = 'threadID';
            $titleCol = in_array('title', $cols) ? 'title' : null;
            $contentCol = in_array('content', $cols) ? 'content' : (in_array('body', $cols) ? 'body' : null);
        } elseif ($table === 'plugins_forum_posts') {
            $idCol = 'postID';
            $titleCol = null;
            $contentCol = in_array('content', $cols) ? 'content' : (in_array('text', $cols) ? 'text' : null);
        } else {
            $idCol = in_array('id', $cols) ? 'id' : (in_array($table.'ID', $cols) ? $table.'ID' : null);
            $titleCol = in_array('title', $cols) ? 'title' : (in_array('name', $cols) ? 'name' : null);
            $contentCol = in_array('content', $cols) ? 'content' : (in_array('description', $cols) ? 'description' : null);
        }

        if (!$idCol || (!$titleCol && !$contentCol)) continue;

        $searchCols = array_filter([$titleCol, $contentCol]);
        list($conditions, $params) = buildLikeConditionsOr($searchCols, $terms);

        $sql = "SELECT '$table' AS type, `$idCol` AS id, "
             . ($titleCol ? "`$titleCol` AS title, " : "'' AS title, ")
             . ($contentCol ? "`$contentCol` AS body " : "'' AS body ")
             . ($table === 'plugins_forum_posts' ? ", threadID " : "")
             . "FROM `$table` WHERE $conditions";

        $stmt = $_database->prepare($sql);
        if ($stmt && !empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $resPlugin = $stmt->get_result();
        while ($r = $resPlugin->fetch_assoc()) {
            $results[] = $r;
        }
        $stmt->close();
    }
}

#echo $sql;
#var_dump($params);

// --- Treffer im Titel priorisieren ---
usort($results, function($a, $b) use ($q) {
    $at = (mb_stripos($a['title'], $q) !== false) ? 0 : 1;
    $bt = (mb_stripos($b['title'], $q) !== false) ? 0 : 1;
    if ($at !== $bt) return $at - $bt;
    return strcasecmp($a['title'], $b['title']);
});

// --- Pagination ---
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;


    $total = count($results);
$paginated = array_slice($results, $offset, $perPage);

if ($total > 0) {
    echo "<p>Gefundene Treffer: $total</p>";
    echo "<p>Treffer " . ($offset + 1) . " bis " . min($offset + $perPage, $total) . " von $total</p>";


    if (empty($paginated)) {
        echo $tpl->loadTemplate("search", "no_results", [
            'no_results' => $languageService->get('no_results'),
        ], "plugin");
    } else {
        // --- Ausgabe ---
        foreach ($paginated as $row) {
            $type = $row['type'] ?? '';
            $id   = (int)($row['id'] ?? 0);
            $content = ''; // Default

            switch ($type) {
                case 'plugins_articles':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=articles/watch&id=$id");
                    $typeLabel = 'Artikel (Watch)';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_articles_categories':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=articles/show&id=$id");
                    $typeLabel = 'Artikelkategorie';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_wiki':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=wiki/watch&id=$id");
                    $typeLabel = 'Wiki (Watch)';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_wiki_categories':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=wiki/show&id=$id");
                    $typeLabel = 'Wikikategorie';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_downloads':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=downloads/detail&id=$id");
                    $typeLabel = 'Downloads (Detail)';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_downloads_categories':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=downloads/cat_list&id=$id");
                    $typeLabel = 'Downloadskategorie';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_forum_boards':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=forum/board&id=$id");
                    $typeLabel = 'Forum';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_forum_threads':
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=forum/thread&id=$id");
                    $typeLabel = 'Forum';
                    $content = $row['body'] ?? '';
                    break;

                case 'plugins_forum_posts':
                    $threadID = $row['threadID'] ?? 0;
                    $url = ($threadID > 0)
                        ? SeoUrlHandler::convertToSeoUrl("index.php?site=forum/thread&id=$threadID#post$id")
                        : SeoUrlHandler::convertToSeoUrl("index.php?site=forum/thread&id=$id");
                    $typeLabel = 'Forum';
                    $content = $row['body'] ?? '';
                    break;

                // --- Statische Seiten ---
                case 'plugins_about':
                case 'site_about':
                    $content = getLocalizedText($row['title'], $currentLang)
                             . ' ' . getLocalizedText($row['intro'] ?? '', $currentLang)
                             . ' ' . getLocalizedText($row['history'] ?? '', $currentLang)
                             . ' ' . getLocalizedText($row['core_values'] ?? '', $currentLang)
                             . ' ' . getLocalizedText($row['team'] ?? '', $currentLang)
                             . ' ' . getLocalizedText($row['cta'] ?? '', $currentLang);

                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=about");
                    $typeLabel = 'Über uns';
                    break;

                case 'site_leistung':
                    $content = getLocalizedText($row['title'], $currentLang)
                             . ' ' . getLocalizedText($row['intro'] ?? '', $currentLang)
                             . ' ' . getLocalizedText($row['history'] ?? '', $currentLang);
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=leistung");
                    $typeLabel = 'Leistung';
                    break;

                case 'site_info':
                    $content = getLocalizedText($row['title'], $currentLang)
                             . ' ' . getLocalizedText($row['intro'] ?? '', $currentLang);
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=info");
                    $typeLabel = 'Info';
                    break;

                case 'site_resume':
                    $content = getLocalizedText($row['title'], $currentLang)
                             . ' ' . getLocalizedText($row['intro'] ?? '', $currentLang);
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=resume");
                    $typeLabel = 'Resume';
                    break;

                case 'settings_static':
                    $staticID = (int)($row['staticID'] ?? $row['id'] ?? 0);
                    $fields = ['title', 'intro', 'content', 'body']; // body jetzt mit dabei
                    $content = '';
                    foreach ($fields as $field) {
                        if (!empty($row[$field])) {
                            $content .= ' ' . getLocalizedText($row[$field], $currentLang);
                        }
                    }
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=static&staticID=$staticID");
                    $typeLabel = 'Statische Seite';
                    break;
                            
                // --- Standard für Statische Seite ---    
                default:
                    $typeLabel = 'Statische Seite';
                    $staticID = (int)($row['staticID'] ?? $row['id'] ?? 0);
                    $url = SeoUrlHandler::convertToSeoUrl("index.php?site=static&staticID=$staticID");

                    // Alle relevanten Felder zusammenführen
                    $fields = ['title', 'intro', 'body', 'content', 'history', 'core_values', 'team', 'cta', 'categoryID', 'editor', 'access_roles'];
                    $content = '';
                    foreach ($fields as $field) {
                        if (!empty($row[$field])) {
                            $content .= ' ' . getLocalizedText($row[$field], $currentLang);
                        }
                    }
                    $contentLocalized = getLocalizedText($content, $currentLang);
                        $snippet = make_snippet_multi($contentLocalized, $terms, 50);
                        $highlightedSnippet = $snippet; // schon markiert
                        $highlightedTitle   = make_snippet_multi($row['title'] ?? '', $terms, 50);
                    break;

            }

            // Lokalisieren & Snippet erstellen
            $contentLocalized = getLocalizedText($content, $currentLang);
            $snippet = make_snippet_multi($contentLocalized, $terms, 50);
            $highlightedSnippet = $snippet; // schon markiert
            $highlightedTitle   = make_snippet_multi($row['title'] ?? '', $terms, 50);

            // Button-Logik
            $showButton = in_array($type, ['plugins_forum_threads', 'plugins_forum_posts']);

            $tplData = [
                'type'       => htmlspecialchars($typeLabel),
                'title'      => $highlightedTitle,
                'snippet'    => $highlightedSnippet,
                'url'        => $url,
                'showButton' => $showButton,
            ];


            echo $tpl->loadTemplate("search", "result_item", $tplData, "plugin");
        }

    }
    
    // Paging-Links
    $totalPages = ceil($total / $perPage);
    if ($totalPages > 1) {
        echo '<p>';
        if ($page > 1) echo '<a href="?site=search&q=' . urlencode($q) . '&page=' . ($page - 1) . '">&laquo; Zurück</a> ';
        if ($page < $totalPages) echo '<a href="?site=search&q=' . urlencode($q) . '&page=' . ($page + 1) . '">Weiter &raquo;</a>';
        echo '</p>';
    }
} else {
    echo '<div class="alert alert-warning" role="alert">
  Keine Treffer gefunden.
</div>';
}

echo $tpl->loadTemplate("search", "foot", [], "plugin");
?>
