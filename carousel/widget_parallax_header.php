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

$ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_carousel_settings")); // optional: z. B. für Höhe

$parallax_height = (int)$ds['parallax_height'];

$result = safe_query("SELECT * FROM plugins_carousel WHERE type = 'parallax' AND visible = 1 ORDER BY sort ASC");

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
            $media_html = '<img src="' . $media_file . '" alt="' . htmlspecialchars($title) . '" class="img-fluid w-100" style="max-height:' . $parallax_height . 'vh; object-fit:cover;">';
        } elseif ($media_type === 'video') {
            $media_html = '<video class="img-fluid w-100" style="max-height:' . $parallax_height . 'vh; object-fit:cover;" autoplay muted loop playsinline>
                <source src="' . $media_file . '" type="video/mp4">
                ' . $languageService->get('video_not_supported') . '
            </video>';
        }

        $replaces = [
            'parallax_pic'     => $media_html,
            'parallax_height'  => $parallax_height,
            'title'          => $title,
            'link'           => $link,
            'description'    => $description
        ];

        echo $tpl->loadTemplate("parallax_header", "content", $replaces, 'plugin');
    }
} else {
    echo '<div class="alert alert-danger" role="alert">' . $languageService->get('no_header') . '</div>';
}
