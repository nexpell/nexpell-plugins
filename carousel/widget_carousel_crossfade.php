<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();

$filepath = "../includes/plugins/carousel/";
$filepathvid = "../includes/plugins/carousel/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings"));

echo '
<section id="hero" style="height: '.$ds['carousel_height'].';">
    <div id="hero carouselExampleInterval" class="carousel slide" data-bs-ride="carousel">
';

// Query laden
$carousel = safe_query("SELECT * FROM plugins_carousel WHERE displayed = '1' ORDER BY sort");

// Indikatoren als Buttons erzeugen
echo '<div class="carousel-indicators" id="hero-carousel-indicators">';
$indicatorIndex = 0;
while ($row = mysqli_fetch_array($carousel)) {
    echo '<button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="' . $indicatorIndex . '"'
        . ($indicatorIndex === 0 ? ' class="active" aria-current="true"' : '')
        . ' aria-label="Slide ' . ($indicatorIndex + 1) . '"></button>';
    $indicatorIndex++;
}
// Reset Resultset-Pointer für zweite Schleife
mysqli_data_seek($carousel, 0);
echo '</div>';

// Carousel-Items
echo '<div class="carousel-inner" role="listbox">';

$x = 1;
while ($db = mysqli_fetch_array($carousel)) {

    $timesec = !empty($db['time_pic']) ? ($db['time_pic'] * 1000) : 10000;

    echo '<div class="carousel-item ' . ($x == 1 ? 'active' : '') . '" data-bs-interval="'.$timesec.'">';

    if (!empty($db['carousel_vid'])) {
        // Video ausgeben
        $carousel_pic = '<video autoplay loop muted playsinline width="100%" class="pic" controls>
                            <source src="' . $filepath . "videos/" . $db['carousel_vid'] . '" type="video/mp4">
                         </video>';
    } elseif (!empty($db['carousel_pic'])) {
        // Bild ausgeben
        $carousel_pic = '<img class="pic" src="' .$filepath . "images/" . $db['carousel_pic'] . '" alt="' . htmlspecialchars($db['title']) . '" style="height: '.$ds['carousel_height'].';">';
    } else {
        $carousel_pic = '';
    }

    $carouselID = $db['carouselID'];
    $title = $db['title'];
    $ani_title = $db['ani_title'];
    $ani_link = $db['ani_link'];
    $ani_description = $db['ani_description'];
    $description = $db['description'];

    if (!empty($db['link'])) {
        $link = '<a href="'.$db['link'].'" class="btn btn-primary animated '.$ani_link.' scrollto">'.$languageService->get('read_more').'</a>';
    } else {
        $link = '';
    }

    // Mehrsprachigkeit
    $translate = new multiLanguage($lang);
    $translate->detectLanguages($title);
    $title = $translate->getTextByLanguage($title);
    $translate->detectLanguages($description);
    $description = $translate->getTextByLanguage($description);

    $data_array = [
        'carouselID'       => $carouselID,
        'carousel_pic'     => $carousel_pic,
        'title'            => $title,
        'ani_title'        => $ani_title,
        'ani_description'  => $ani_description,
        'link'             => $link,
        'description'      => $description
    ];

    echo $tpl->loadTemplate("widget_carousel_crossfade", "content", $data_array, 'plugin');

    echo '</div>'; // carousel-item schließen
    $x++;
}

echo '</div>
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

