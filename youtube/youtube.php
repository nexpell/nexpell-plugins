<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\RoleManager;
use nexpell\SeoUrlHandler;

global $languageService, $_database;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('youtube');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class' => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'youtube'
];

echo $tpl->loadTemplate("youtube", "head", $data_array, 'plugin');

// --- Einstellungen direkt aus DB laden ---
$settings = [];
$result = $_database->query("SELECT setting_key, setting_value FROM plugins_youtube_settings WHERE plugin_name='youtube'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Standardwerte, falls Einstellungen fehlen
$defaultVideoId = $settings['default_video_id'] ?? 'D_x8ms9nGQw';
$videosPerPageFirst = intval($settings['videos_per_page'] ?? 4);
$videosPerPageOther = intval($settings['videos_per_page_other'] ?? 6);
$displayMode = $settings['display_mode'] ?? 'grid';
$firstFullWidth = intval($settings['first_full_width'] ?? 1);

// --- Alle Videos aus DB laden und vereinheitlichen ---
$allVideos = [];
$firstVideo = null;
$result = $_database->query("
    SELECT setting_value, is_first
    FROM plugins_youtube
    WHERE plugin_name='youtube' AND setting_key LIKE 'video_%'
    ORDER BY id DESC
");

if ($result) {
    $tempVideos = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['is_first'] == 1) {
            $firstVideo = $row['setting_value'];
        }
        $tempVideos[] = $row['setting_value'];
    }
    // Wichtig: Die Duplizierung nur entfernen, wenn das Video auch existiert
    if ($firstVideo !== null) {
        $tempVideos = array_diff($tempVideos, [$firstVideo]);
        array_unshift($tempVideos, $firstVideo);
    }
    $allVideos = $tempVideos;
}

// Fallback, wenn keine Videos gefunden wurden
$allVideos = [];
$firstVideo = null;
$result = $_database->query("
    SELECT setting_value, is_first 
    FROM plugins_youtube 
    WHERE plugin_name='youtube' AND setting_key LIKE 'video_%' 
    ORDER BY id DESC
");

if ($result) {
    $tempVideos = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['is_first'] == 1) {
            $firstVideo = $row['setting_value'];
        }
        $tempVideos[] = $row['setting_value'];
    }
    // Wichtig: Die Duplizierung nur entfernen, wenn das Video auch existiert
    if ($firstVideo !== null) {
        $tempVideos = array_diff($tempVideos, [$firstVideo]);
        array_unshift($tempVideos, $firstVideo);
    }
    $allVideos = $tempVideos;
}

// Fallback, wenn keine Videos gefunden wurden
if (empty($allVideos)) {
    $allVideos[] = $defaultVideoId;
    $firstVideo = $defaultVideoId;
}

// --- Pagination ---
$page = max(1, intval($_GET['page'] ?? 1));
$videosToDisplay = [];

if ($page === 1) {
    // Wenn die Startseite ein Fullwidth-Video hat
    if ($displayMode === 'grid' && $firstFullWidth && $firstVideo !== null) {
        // Fullwidth-Video separat behandeln
        $fullWidthVideo = $allVideos[0];
        $videosToDisplay = array_slice($allVideos, 1, $videosPerPageFirst - 1);
    } else {
        // Normale Ansicht: Das erste Video ist Teil der paginierten Liste
        $fullWidthVideo = null;
        $videosToDisplay = array_slice($allVideos, 0, $videosPerPageFirst);
    }
} else {
    // Folgeseiten: den Offset korrekt berechnen
    $offset = $videosPerPageFirst + ($page - 2) * $videosPerPageOther;
    $limit = $videosPerPageOther;
    $videosToDisplay = array_slice($allVideos, $offset, $limit);
    $fullWidthVideo = null;
}

// --- Prüfen, ob Video existiert ---
function isVideoValid($videoId) {
    $url = "https://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=$videoId&format=json";
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200') !== false;
}

// --- HTML-Struktur für das Laden der Videos durch JS ---
?>
<div id="youtube-video-container"></div>

<!-- Fallback-Hinweis, falls Cookies nicht akzeptiert sind -->
<div id="fallback-youtube" class="alert alert-info text-center mt-3" style="display:none;">
    ⚠️ Bitte akzeptieren Sie die Cookies, um die YouTube-Videos sehen zu können.
</div>

<!-- JS-Config für YouTube -->
<script>
const YOUTUBE_CONFIG = {
    displayMode: <?= json_encode($displayMode) ?>,
    fullWidthVideoId: <?= json_encode($fullWidthVideo) ?>,
    otherVideoIds: <?= json_encode($videosToDisplay) ?>,
    currentPage: <?= json_encode($page) ?>,
    totalVideos: <?= json_encode(count($allVideos)) ?>,
    videosPerPageFirst: <?= json_encode($videosPerPageFirst) ?>,
    videosPerPageOther: <?= json_encode($videosPerPageOther) ?>
};
</script>

<style>
.youtube-video-full { width: 100%; margin-bottom: 1rem; }
.youtube-video-grid { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
.youtube-video-grid-item { flex: 1 1 calc(33.333% - 1rem); }
.youtube-video-list-item { width: 100%; margin-bottom: 1rem; }
</style>
