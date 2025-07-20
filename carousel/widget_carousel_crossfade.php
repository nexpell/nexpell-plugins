<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();


$filepath = "../includes/plugins/carousel/images/";

// Lade Einstellungen (z.B. Carousel Höhe)
$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings")); 
$carousel_height = (int)($ds['carousel_height']); // Fallback 75 vh

echo '
<header id="hero" style="height: ' . $carousel_height . 'vh;">
  <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
';

// Datensätze laden
$carousel = safe_query("SELECT * FROM plugins_carousel WHERE type = 'carousel' AND visible = 1 ORDER BY sort");

// Prüfen, ob überhaupt Slides da sind
if (mysqli_num_rows($carousel) > 0) {

    // Carousel Indicators
    echo '<div class="carousel-indicators" id="hero-carousel-indicators">';
    $indicatorIndex = 0;
    while ($row = mysqli_fetch_array($carousel)) {
        echo '<button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="' . $indicatorIndex . '"'
            . ($indicatorIndex === 0 ? ' class="active" aria-current="true"' : '')
            . ' aria-label="Slide ' . ($indicatorIndex + 1) . '"></button>';
        $indicatorIndex++;
    }
    echo '</div>';

    mysqli_data_seek($carousel, 0); // Cursor zurücksetzen

    // Carousel Items
    echo '<div class="carousel-inner" role="listbox">';

    $slideIndex = 0;
    while ($db = mysqli_fetch_array($carousel)) {
        $slideIndex++;
        $interval_ms = 10000; // Standard Intervall

        $media_file = $filepath . $db['media_file'];
        $media_type = $db['media_type']; // "image" oder "video"
        $title_raw = $db['title'];
        $description_raw = $db['description'];
        $link_url = $db['link'];

        // Mehrsprachigkeit
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($title_raw);
        $title = $translate->getTextByLanguage($title_raw);

        $translate->detectLanguages($description_raw);
        $description = $translate->getTextByLanguage($description_raw);

        // Media HTML bauen
        $common_classes = 'pic img-fluid w-100';
        $common_style = 'max-height:' . $carousel_height . 'vh; object-fit:cover;';

        if ($media_type === 'image') {
            $media_html = '<img src="' . htmlspecialchars($media_file) . '" alt="' . htmlspecialchars($title) . '" class="' . $common_classes . '" style="' . $common_style . '">';
        } elseif ($media_type === 'video') {
            $media_html = '<video class="' . $common_classes . '" style="' . $common_style . '; margin-top: 15px;" autoplay muted loop playsinline>
                <source src="' . htmlspecialchars($media_file) . '" type="video/mp4">
                ' . htmlspecialchars($languageService->get('video_not_supported')) . '
            </video>';
        }

        // Link Button (falls Link vorhanden)
        $link = '';
        if (!empty($link_url)) {
            $link = '<a href="' . htmlspecialchars($link_url) . '" class="btn btn-primary scrollto">' . htmlspecialchars($languageService->get('read_more')) . '</a>';
        }

        // Template-Replacements
        $replaces = [
            'carouselID'       => $db['id'],
            'carousel_pic'     => $media_html,
            'title'            => $title,
            'link'             => $link,
            'description'      => $description
        ];

        echo '<div class="carousel-item ' . ($slideIndex === 1 ? 'active' : '') . '" data-bs-interval="' . $interval_ms . '">';
        echo $tpl->loadTemplate("widget_carousel_crossfade", "content", $replaces, 'plugin');
        echo '</div>';
    }

    echo '</div>'; // .carousel-inner

    // Controls
    echo '
    <a class="carousel-control-prev" href="#heroCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon bi bi-chevron-left" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </a>
    <a class="carousel-control-next" href="#heroCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon bi bi-chevron-right" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </a>
    ';

} else {
    echo '<p>Keine Carousel-Elemente gefunden.</p>';
}

echo '
  </div>
</header>';
?>
