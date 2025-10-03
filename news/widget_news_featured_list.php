<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

use nexpell\SeoUrlHandler;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');
// widget_news_featured_list.php
// Featured + List News Widget

$limit = 5; // Anzahl der News insgesamt (1 Featured + Rest Liste/Grid)

echo '<link rel="stylesheet" href="/includes/plugins/news/css/news_featured_list.css">' . PHP_EOL;

$query = "
    SELECT a.id, a.title, a.content, a.updated_at, a.category_id, c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    WHERE a.is_active = 1
    ORDER BY a.sort_order DESC, a.updated_at DESC
    LIMIT " . intval($limit);

$res = safe_query($query);

if (!$res || mysqli_num_rows($res) === 0) {
    echo '<div class="news-featured-empty">Keine News verfügbar.</div>';
    return;
}

echo '<h1>News Featured</h1>';

// Erste News als Featured
$featured = mysqli_fetch_assoc($res);
$fid   = (int)$featured['id'];
$ftitle = htmlspecialchars($featured['title']);
$fplain = strip_tags($featured['content']);
$fexcerpt = mb_strlen($fplain) > 220 ? mb_substr($fplain, 0, 320) . '…' : $fplain;

$fcat_image = $featured['category_image'] ?? '';
$fimage = $fcat_image
    ? '/includes/plugins/news/images/news_categories/' . $fcat_image
    : '/includes/plugins/news/images/no-image.jpg';

// SEO-Link
$furl = SeoUrlHandler::buildPluginUrl('plugins_news', $fid, $lang);

$fcategory = htmlspecialchars($featured['category_name'] ?? 'Kategorie');
$fdate = date('d.m.Y', $featured['updated_at']);

echo '<div class="news-featured-list">';

// Featured Block
echo '<div class="featured-news border">';
echo '    <img src="' . htmlspecialchars($fimage) . '" alt="' . $fcategory . '">';
echo '  <a href="' . htmlspecialchars($furl) . '" class="featured-thumb">';
echo '    <span class="featured-badge">' . $fcategory . '</span>';
echo '  </a>';
echo '  <div class="featured-body">';
echo '    <h2 class="featured-title"><a href="' . htmlspecialchars($furl) . '">' . $ftitle . '</a></h2>';
echo '    <p class="featured-excerpt">' . htmlspecialchars($fexcerpt) . '</p>';
echo '    <div class="featured-meta"><small>' . $fdate . '</small></div>';
echo '  </div>';
echo '</div>';

// Restliche News als Grid/List
if (mysqli_num_rows($res) > 0) {
    echo '<div class="news-list">';
    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int)$row['id'];
        $title = htmlspecialchars($row['title']);
        $plain = strip_tags($row['content']);
        $excerpt = mb_strlen($plain) > 120 ? mb_substr($plain, 0, 120) . '…' : $plain;

        $cat_image = $row['category_image'] ?? '';
        $image = $cat_image
            ? '/includes/plugins/news/images/news_categories/' . $cat_image
            : '/includes/plugins/news/images/no-image.jpg';

        // SEO-Link zur News
        $url = SeoUrlHandler::buildPluginUrl('plugins_news', $id, $lang);
        $category = htmlspecialchars($row['category_name'] ?? 'Kategorie');
        $date = date('d.m.Y', $row['updated_at']);

        echo '<article class="news-item border">';
        echo '    <img src="' . htmlspecialchars($image) . '" alt="' . $category . '">';
        echo '  <div class="news-body">';
        echo '    <h5 class="news-title"><a href="' . htmlspecialchars($url) . '">' . $title . '</a></h5>';
        echo '    <p class="news-excerpt">' . htmlspecialchars($excerpt) . '</p>';
        echo '    <div class="news-meta"><small>' . $date . ' | ' . $category . '</small></div>';
        echo '  </div>';
        echo '</article>';
    }
    echo '</div>';
}

echo '</div>';
 // .news-featured-list
