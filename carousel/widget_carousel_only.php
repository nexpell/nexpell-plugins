<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();

$filepath = $plugin_path . "images/";
$filepathvid = $plugin_path . "videos/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings"));

echo '
<section id="hero" style="height: ' . htmlspecialchars($ds['carousel_height']) . ';">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <ol class="carousel-indicators" id="hero-carousel-indicators">';

$carousel = safe_query("SELECT * FROM plugins_carousel WHERE displayed = '1' ORDER BY sort");
$x = 0;

if (mysqli_num_rows($carousel)) {
    // Carousel-Indikatoren ausgeben
    while ($db = mysqli_fetch_array($carousel)) {
        echo '<li data-bs-target="#heroCarousel" data-bs-slide-to="' . $x . '"' . ($x === 0 ? ' class="active"' : '') . '></li>';
        $x++;
    }
}
echo '</ol>';

echo '<div class="carousel-inner" role="listbox">';

// Resultset zurücksetzen, um nochmal von vorne zu lesen
mysqli_data_seek($carousel, 0);

$x = 1;
while ($db = mysqli_fetch_array($carousel)) {

    $timesec = !empty($db['time_pic']) ? intval($db['time_pic']) * 1000 : 10000;

    echo '<div class="carousel-item ' . ($x === 1 ? 'active' : '') . '" data-bs-interval="' . $timesec . '">';

    // Bild oder Video bestimmen
    if (!empty($db['carousel_vid'])) {
        $carousel_pic = '<video autoplay loop muted playsinline width="100%" class="pic" controls><source src="' . htmlspecialchars($filepathvid . $db['carousel_vid']) . '" type="video/mp4"></video>';
    } elseif (!empty($db['carousel_pic'])) {
        $carousel_pic = '<img class="pic" src="' . htmlspecialchars($filepath . $db['carousel_pic']) . '" alt="' . htmlspecialchars($db['title']) . '" style="height: ' . htmlspecialchars($ds['carousel_height']) . ';">';
    } else {
        $carousel_pic = '';
    }

    // Übersetzungen
    $translate = new multiLanguage($lang);
    $translate->detectLanguages($db['title']);
    $title = $translate->getTextByLanguage($db['title']);
    $translate->detectLanguages($db['description']);
    $description = $translate->getTextByLanguage($db['description']);

    // Link vorbereiten
    $link = '';
    if (!empty($db['link'])) {
        $link = '<a href="' . htmlspecialchars($db['link']) . '" class="btn-get-started animated ' . htmlspecialchars($db['ani_link']) . ' scrollto">' . $languageService->get('read_more') . '</a>';
    }

    $data_array = [
        'carouselID'      => $db['carouselID'],
        'carousel_pic'    => $carousel_pic,
        'title'           => $title,
        'ani_title'       => $db['ani_title'],
        'ani_description' => $db['ani_description'],
        'link'            => $link,
        'description'     => $description
    ];

    echo $tpl->loadTemplate("widget_carousel_only", "content", $data_array, 'plugin');
    echo '</div>';

    $x++;
}

echo '
        </div>
        <a class="carousel-control-prev" href="#heroCarousel" role="button" data-bs-slide="prev">
            <span class="carousel-control-prev-icon bi bi-chevron-left" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </a>
        <a class="carousel-control-next" href="#heroCarousel" role="button" data-bs-slide="next">
            <span class="carousel-control-next-icon bi bi-chevron-right" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </a>
    </div>
</section>';
