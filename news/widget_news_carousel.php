<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

use nexpell\SeoUrlHandler;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');
// widget_news_carousel.php
// News Carousel mit Swiper.js


$limit = 8; // Anzahl der News im Slider

echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">';
echo '<link rel="stylesheet" href="/includes/plugins/news/css/news_carousel.css">';

$query = "
    SELECT a.id, a.title, a.updated_at, a.banner_image, a.slug,
           c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    WHERE a.is_active = 1
    ORDER BY a.updated_at DESC
    LIMIT " . intval($limit);

$res = safe_query($query);

if (mysqli_num_rows($res) > 0):
?>
<h1>News Carousel</h1>
<div class="news-carousel-widget">
  <h5 class="mb-3">News Carousel</h5>
  
  <div class="swiper newsSwiper">
    <div class="swiper-wrapper">
      <?php while ($news = mysqli_fetch_assoc($res)):

        $image = !empty($news['category_image'])
          ? '/includes/plugins/news/images/news_categories/' . $news['category_image']
          : '/includes/plugins/news/images/no-image.jpg';

        $day = date('d', $news['updated_at']);
        $month = strtolower(date('F', $news['updated_at']));
        $year = date('Y', $news['updated_at']);
        $title = htmlspecialchars($news['title']);
        $category_name = htmlspecialchars($news['category_name']);

        // SEO-Link generieren
        $url_watch = SeoUrlHandler::buildPluginUrl('plugins_news', $news['id'], $lang);

      ?>
      <div class="swiper-slide">
        <div class="card h-100">
          <img src="<?= $image ?>" class="card-img-top" alt="<?= $title ?>" style="object-fit:cover; height:180px;">
          <div class="card-body d-flex flex-column">
            
            <h6 class="card-title mb-2 text-truncate" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?= $title ?>
            </h6>

            <!-- Datum links, Kategorie rechts -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted"><?= $day ?> <?= $languageService->get($month) ?> <?= $year ?></small>
                <span class="badge bg-primary"><?= $category_name ?></span>
            </div>

            <a href="<?= htmlspecialchars($url_watch) ?>" class="btn btn-sm btn-primary mt-auto">Mehr lesen</a>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- Navigation + Pagination -->
  <div class="carousel-controls d-flex justify-content-between align-items-center mt-2">
    <div class="swiper-button-prev"></div>
    <div class="swiper-pagination flex-grow-1 text-center"></div>
    <div class="swiper-button-next"></div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
var swiper = new Swiper(".newsSwiper", {
  slidesPerView: 1,
  spaceBetween: 15,
  loop: true, // Endlos-Loop
  autoplay: {
    delay: 4000, // Zeit zwischen den Slides in ms
    disableOnInteraction: false, // auch nach Klick weiterlaufen
  },
  navigation: {
    nextEl: ".news-carousel-widget .swiper-button-next",
    prevEl: ".news-carousel-widget .swiper-button-prev",
  },
  pagination: {
    el: ".news-carousel-widget .swiper-pagination",
    clickable: true,
  },
  breakpoints: {
    768: { slidesPerView: 2 },
    992: { slidesPerView: 3 }
  }
});
</script>

<?php endif; ?>
