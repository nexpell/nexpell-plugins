<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('carousel');

$tpl = new Template();

/*$filepath = "../includes/plugins/carousel/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings"));

$ergebnis = safe_query("SELECT * FROM plugins_carousel_agency");
if (mysqli_num_rows($ergebnis)) {
    $i = 1;
    while ($db = mysqli_fetch_array($ergebnis)) {
        $agency_pic = $filepath . "images/" . $db['agency_pic'];
        $agency_height = $ds['agency_height'];
        $description = $db['description'];
        $title = $db['title'];
        $link_raw = $db['link'];

        // Link korrekt einbauen
        if (!empty($link_raw)) {
            $link = '<a data-aos="fade-up" data-aos-delay="200" href="' . $link_raw . '" class="btn-get-started scrollto">' . $languageService->get('read_more') . '</a>';
        } else {
            $link = '';
        }

        // Mehrsprachigkeit
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($title);
        $title = $translate->getTextByLanguage($title);
        $translate->detectLanguages($description);
        $description = $translate->getTextByLanguage($description);

        // Template-Daten
        $data_array = [
            'agency_pic'    => $agency_pic,
            'agency_height' => $agency_height,
            'title'         => $title,
            'link'          => $link,
            'description'   => $description
        ];

        echo $tpl->loadTemplate("agency_header", "content", $data_array, "plugin");
        $i++;
    }
} else {
    echo '<div class="alert alert-danger" role="alert">' . $languageService->get('no_header') . '</div>';
}

*/

$filepath = "../includes/plugins/carousel/images/";

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings")); // optional: z. B. für Höhe

$agency_height = (int)$ds['agency_height'];

$result = safe_query("SELECT * FROM plugins_carousel WHERE type = 'agency' AND visible = 1 ORDER BY sort ASC");

if (mysqli_num_rows($result)) {
    while ($db = mysqli_fetch_array($result)) {
        $media_file = $filepath . $db['media_file'];
        $media_type = $db['media_type']; // "image" oder "video"
        $description = $db['description'];
        $link_url = $db['link'];
        $title = $db['title'];

        // Link-Button
        $link = '';
        if (!empty($link_url)) {
            if (str_starts_with($link_url, 'https://')) {
                $link = '<a data-aos="fade-up" data-aos-delay="200" href="' . htmlspecialchars($link_url) . '" class="btn-get-started scrollto"><i class="bi bi-chevron-double-down"></i></a>';
            } else {
                $link = '<a data-aos="fade-up" data-aos-delay="200" href="' . htmlspecialchars($link_url) . '" class="btn-get-started scrollto">' . $languageService->get('read_more') . '</a>';
            }
        }

        // Mehrsprachigkeit
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($title);
        $title = $translate->getTextByLanguage($title);
        $translate->detectLanguages($description);
        $description = $translate->getTextByLanguage($description);

        // Medientyp erkennen
        $media_html = '';
        if ($media_type === 'image') {
            $media_html = '<img src="' . $media_file . '" alt="' . htmlspecialchars($title) . '" class="img-fluid w-100" style="max-height:' . $agency_height . 'vh; object-fit:cover;">';
        } elseif ($media_type === 'video') {
            $media_html = '<video class="img-fluid w-100" style="max-height:' . $agency_height . 'vh; object-fit:cover;" autoplay muted loop playsinline>
                <source src="' . $media_file . '" type="video/mp4">
                ' . $languageService->get('video_not_supported') . '
            </video>';
        }

        $replaces = [
            'agency_pic'     => $media_html,
            'agency_height'  => $agency_height,
            'title'          => $title,
            'link'           => $link,
            'description'    => $description
        ];

        echo $tpl->loadTemplate("agency_header", "content", $replaces, 'plugin');
    }
} else {
    echo '<div class="alert alert-danger" role="alert">' . $languageService->get('no_header') . '</div>';
}