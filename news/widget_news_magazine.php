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
    SELECT a.id, a.title, a.updated_at, a.banner_image,
           a.content, c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    WHERE a.is_active = 1
    ORDER BY a.updated_at DESC
    LIMIT 4
";

$res = safe_query($query);
$news = [];
while ($row = mysqli_fetch_assoc($res)) {
    // SEO-Link zur News generieren
    $row['link'] = SeoUrlHandler::buildPluginUrl('plugins_news', $row['id'], $lang);
    $news[] = $row;
}

if (count($news) > 0):
?>
<h1>News Magazine</h1>
<div class="news-magazine-widget d-flex flex-wrap gap-3">
    <!-- Featured News -->
    <?php
    $featured = array_shift($news);
    $featured_image = !empty($featured['category_image'])
        ? '/includes/plugins/news/images/news_categories/' . $featured['category_image']
        : '/includes/plugins/news/images/no-image.jpg';

    // Link zur News
    $featured_link = $featured['link']; 
    ?>
    <div class="featured-news flex-grow-1" style="flex: 2 1 60%; position: relative;">
    <a href="<?= $featured_link ?>" style="display:block; color:inherit; text-decoration:none;">
        <div style="position: relative; width: 100%; overflow: hidden; border-radius:0px;">
            <img src="<?= $featured_image ?>" alt="<?= htmlspecialchars($featured['title']) ?>" 
                 style="width:100%; height:604px; object-fit:cover;" class="featured-img">

            <div class="featured-text" style="
                position:absolute; 
                bottom:0; /* immer über dem Bild */
                left:0; 
                right:0; 
                color:#fff; 
                background: rgba(0,0,0,0.5); 
                padding:10px; 
                box-sizing: border-box;
            ">
                <small><?= date('d.m.Y', $featured['updated_at']) ?> | <?= htmlspecialchars($featured['category_name']) ?></small>
                <h3 style="
                    margin:5px 0; 
                    white-space: normal; 
                    overflow: hidden; 
                    text-overflow: ellipsis;
                    display: -webkit-box;
                    -webkit-line-clamp:2; 
                    -webkit-box-orient: vertical;
                "><?= htmlspecialchars($featured['title']) ?></h3>

                <?php $plain_content = strip_tags($featured['content']); ?>
                <p style="
                    margin:0;
                    display: -webkit-box;
                    -webkit-line-clamp:3;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    text-overflow: ellipsis;
                "><?= htmlspecialchars($plain_content) ?></p>
            </div>
        </div>
    </a>
</div>





    <!-- Smaller News -->
    <div class="smaller-news d-flex flex-column flex-grow-1" style="flex:1 1 35%; gap:15px;">
        <?php foreach ($news as $n):
            $img = !empty($n['category_image'])
                ? '/includes/plugins/news/images/news_categories/' . $n['category_image']
                : '/includes/plugins/news/images/no-image.jpg';
            $news_link = $n['link']; // SEO-Link verwenden
        ?>
            <div class="card small-news-card" style="position:relative; overflow:hidden; border-radius:0px;">
                <a href="<?= $news_link ?>" style="display:block; color:inherit; text-decoration:none;">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($n['title']) ?>" style="width:100%; height:120px; object-fit:cover;">
                    <div style="padding:8px;">
                        <small class="text-muted"><?= date('d.m.Y', $n['updated_at']) ?> | <?= htmlspecialchars($n['category_name']) ?></small>
                        <h6 style="margin:5px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($n['title']) ?></h6>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div><?php
    /*<!-- Smaller News -->
    <div class="smaller-news d-flex flex-column flex-grow-1" style="flex:1 1 35%; gap:15px;">
        <?php foreach ($news as $n):
            $img = !empty($n['category_image'])
                ? '/includes/plugins/news/images/news_categories/' . $n['category_image']
                : '/includes/plugins/news/images/no-image.jpg';
            $news_link = $n['link']; // SEO-Link verwenden
        ?>
            <div class="card small-news-card" style="position:relative; overflow:hidden; border-radius:0px;">
                <a href="<?= $news_link ?>" style="display:block; color:inherit; text-decoration:none;">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($n['title']) ?>" style="width:100%; height:120px; object-fit:cover;">
                    <div style="
                        position:absolute;
                        bottom:0;
                        left:0;
                        width:100%;
                        background: rgba(0,0,0,0.5);
                        color:#fff;
                        padding:8px;
                        box-sizing:border-box;
                    ">
                        <small class="text-light"><?= date('d.m.Y', $n['updated_at']) ?> | <?= htmlspecialchars($n['category_name']) ?></small>
                        <h6 style="
                            margin:2px 0 0 0;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        "><?= htmlspecialchars($n['title']) ?></h6>
                    </div>
                </a>
            </div>

        <?php endforeach; ?>
    </div>*/?>
</div>
<?php endif; ?>
<style>
@media (max-width: 768px) {
    .news-magazine-widget {
        flex-direction: column !important; /* Spalten untereinander */
        gap: 15px !important;
    }

    .featured-news,
    .smaller-news {
        flex: 1 1 100% !important; /* volle Breite */
        width: 100% !important;
    }

    .smaller-news {
        display: flex !important;
        flex-direction: column !important; /* kleine News untereinander */
        gap: 10px !important;
    }

    .smaller-news .small-news-card {
        width: 100% !important;
        height: auto !important;
        position: relative;
        overflow: hidden;
    }

    /* Text über Bild legen */
    .smaller-news .small-news-card img {
        width: 100%;
        height: auto;
        display: block;
        object-fit: cover;
    }

    .smaller-news .small-news-card div {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(0,0,0,0.5);
        color: #fff;
        padding: 8px;
        box-sizing: border-box;
    }

    .smaller-news .small-news-card h6 {
        margin: 2px 0 0 0;
        white-space: normal;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .smaller-news .small-news-card small {
        font-size: 12px;
    }
}



</style>