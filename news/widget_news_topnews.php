<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');


$topNewsResult = $_database->query("
    SELECT a.id, a.title, a.updated_at, a.sort_order, c.name as category_name, c.image as category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    WHERE a.is_active = 1
    ORDER BY a.updated_at DESC
    LIMIT 5
");

// Hilfsfunktion: Text kürzen
if (!function_exists('shortenText')) {
    function shortenText($text, $maxLength = 100) {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength) . '…';
        }
        return $text;
    }
}

if ($topNewsResult && $topNewsResult->num_rows > 0) {

    echo '<div class="top-news-widget">';
    echo '<h5 class="mb-3">Top News</h5>';
    echo '<div class="list-group">';

    while ($news = $topNewsResult->fetch_assoc()) {
        $image = !empty($news['category_image']) 
            ? "/includes/plugins/news/images/news_categories/{$news['category_image']}" 
            : "/includes/plugins/news/images/no-image.jpg";

        $day = date('d', $news['updated_at']);
        $month = date('F', $news['updated_at']);
        $year = date('Y', $news['updated_at']);
        $title = htmlspecialchars($news['title']);
        $category_name = htmlspecialchars($news['category_name']);
        // News-Link
        $url_watch_seo = SeoUrlHandler::buildPluginUrl('plugins_news', $news['id'], $lang);

        echo '<a href="' . $url_watch_seo . '" class="list-group-item list-group-item-action d-flex align-items-center">';
        echo '<img src="' . $image . '" alt="' . $category_name . '" class="me-3 rounded" style="width:160px; height:auto; object-fit:cover;">';
        echo '<div class="flex-grow-1">';
        echo '<div class="d-flex justify-content-between align-items-start">';
        echo '<div><strong>' . $title . '</strong><br><small class="text-muted">' . $day . ' ' . $languageService->get(strtolower($month)) . ' ' . $year . '</small></div>';
        echo '<span class="badge bg-primary">' . $category_name . '</span>';
        echo '</div></div></a>';
    }

    echo '</div></div>';
}
?>
