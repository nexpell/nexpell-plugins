<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();

$filepath = $plugin_path."images/";
$filepathvid = $plugin_path."videos/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings"));

echo '
<section id="hero" style="height: '.$ds['carousel_height'].';">
    <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">

        <ol class="carousel-indicators" id="hero-carousel-indicators"></ol>

        <div class="carousel-inner" role="listbox">';
        
$carousel = safe_query("SELECT * FROM plugins_carousel WHERE displayed = '1' ORDER BY sort");
$x = 1;

if (mysqli_num_rows($carousel)) {
    while ($db = mysqli_fetch_array($carousel)) {

        $timesec = !empty($db['time_pic']) ? ($db['time_pic'] * 1000) : 10000;

        echo '<div class="carousel-item ' . ($x == 1 ? 'active' : '') . '" data-bs-interval="'.$timesec.'">';

        if (!empty($db['carousel_vid'])) {
            // Video ausgeben
            $carousel_pic = '<video autoplay loop muted playsinline width="100%" class="pic" controls>
                                <source src="' . $filepathvid . $db['carousel_vid'] . '" type="video/mp4">
                             </video>';
        } elseif (!empty($db['carousel_pic'])) {
            // Bild ausgeben
            $carousel_pic = '<img class="pic" src="' . $filepath . $db['carousel_pic'] . '" alt="' . htmlspecialchars($db['title']) . '" style="height: '.$ds['carousel_height'].';">';
        } else {
            // Falls kein Bild und kein Video, leer setzen
            $carousel_pic = '';
        }

        $carouselID = $db['carouselID'];
        $title = $db['title'];
        $ani_title = $db['ani_title'];
        $ani_link = $db['ani_link'];
        $ani_description = $db['ani_description'];
        $description = $db['description'];

        if (!empty($db['link'])) {
            $link = '<a href="'.$db['link'].'" class="btn-get-started animated '.$ani_link.' scrollto">'.$languageService->get('read_more').'</a>';
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

        echo $tpl->loadTemplate("widget_carousel_only", "content", $data_array, 'plugin');

        echo '</div>'; // carousel-item schließen
        $x++;
    }
}

echo '</div>
    <a class="carousel-control-prev" href="#heroCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon bi bi-chevron-left" aria-hidden="true"></span>
    </a>
    <a class="carousel-control-next" href="#heroCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon bi bi-chevron-right" aria-hidden="true"></span>
    </a>  
</div>
</section>';
