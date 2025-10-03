<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

use nexpell\SeoUrlHandler;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');



$query = "
    SELECT a.id, a.title, a.content, a.updated_at, a.banner_image,
           c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    WHERE a.is_active = 1
    ORDER BY a.updated_at DESC
    LIMIT 6
";

$res = safe_query($query);

if (mysqli_num_rows($res) > 0):
?>
<link rel="stylesheet" href="/includes/plugins/news/css/news_flip.css">
<h1>News Flip</h1>
<div class="news-flip-widget">
    <?php while ($news = mysqli_fetch_assoc($res)):
        $image = !empty($news['category_image'])
            ? '/includes/plugins/news/images/news_categories/' . $news['category_image']
            : '/includes/plugins/news/images/no-image.jpg';

        $title = htmlspecialchars($news['title']);
        $plainText = strip_tags($news['content']); // Alle HTML-Tags entfernen
        $maxLength = 1200;
        $shortContent = mb_strlen($plainText) > $maxLength
            ? mb_substr($plainText, 0, $maxLength) . '...'
            : $plainText;

        $category = htmlspecialchars($news['category_name']);

        // SEO-Link zur News
        $url_watch = SeoUrlHandler::buildPluginUrl('plugins_news', intval($news['id']), $lang);
    ?>
    <div class="flip-card">
        <div class="flip-card-inner">
            <div class="flip-card-front" style="background-image: url('<?= $image ?>');">
                <div class="flip-card-front-overlay">
                    <h6><?= $title ?></h6>
                </div>
            </div>
            <div class="flip-card-back d-flex flex-column border">
                <p><?= $shortContent ?></p>
                <span class="badge bg-primary"><?= $category ?></span>
                <a href="<?= $url_watch ?>" class="btn btn-sm btn-light mt-auto">Mehr lesen</a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

