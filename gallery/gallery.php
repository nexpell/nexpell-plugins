<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database, $languageService, $tpl;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('gallery');

// Stilklasse laden
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class' => $class,
    'title' => $languageService->get('gallery'),
    'subtitle' => 'gallery'
];
echo $tpl->loadTemplate("gallery", "head", $data_array, 'plugin');

$data_array = ['title' => ''];
echo $tpl->loadTemplate("gallery", "content_head", $data_array, 'plugin');

// Konfiguration
$columns = 3; // 4 Spalten pro Zeile
$colClass = 'col-md-3' . (12 / $columns);
$rowsPerPage = 4; // 3 Zeile pro Seite
$imagesPerPage = $columns * $rowsPerPage;

// Kategorie aus URL lesen (0 = Alle)
$categoryFilter = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;

// Seite aus URL lesen
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// WHERE-Klausel für Kategorie-Filter
$where = '';
if ($categoryFilter > 0) {
    $where = " WHERE g.category_id = $categoryFilter ";
}

// Bildanzahl mit Kategorie-Filter
$countResult = $_database->query("SELECT COUNT(*) as total FROM plugins_gallery g $where");
$totalImages = 0;
if ($countResult) {
    $row = $countResult->fetch_assoc();
    $totalImages = (int)$row['total'];
}

// Seitenanzahl berechnen
$totalPages = max(1, ceil($totalImages / $imagesPerPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $imagesPerPage;

// Kategorien laden
$categories = [];
$catResult = $_database->query("SELECT id, name FROM plugins_gallery_categories ORDER BY name ASC");
if ($catResult && $catResult->num_rows > 0) {
    while ($cat = $catResult->fetch_assoc()) {
        $categories[] = $cat;
    }
}

// Bilder mit Filter und Pagination laden
$images = [];
$sql = "SELECT g.filename, g.class, g.category_id, c.name AS category_name
        FROM plugins_gallery g
        LEFT JOIN plugins_gallery_categories c ON g.category_id = c.id
        $where
        ORDER BY g.position ASC, g.id ASC
        LIMIT $offset, $imagesPerPage";
$result = $_database->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
}
?>

<!-- Kategorie-Filter -->
<ul id="portfolio-flters" class="list-inline text-center mb-4">
    <li class="list-inline-item btn btn-outline-primary <?= $categoryFilter === 0 ? 'active' : '' ?>">
        <a href="index.php?site=gallery&page=1&category=0" class="text-decoration-none">Alle</a>
    </li>
    <?php foreach ($categories as $cat): ?>
        <li class="list-inline-item btn btn-outline-primary <?= $categoryFilter === (int)$cat['id'] ? 'active' : '' ?>">
            <a href="index.php?site=gallery&page=1&category=<?= (int)$cat['id'] ?>" class="text-decoration-none">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Galerie -->
<div class="grid-wrapper">
    <?php if (count($images) === 0): ?>
        <p class="text-center">Keine Bilder in dieser Kategorie.</p>
    <?php else: ?>
        <?php foreach ($images as $image): ?>
            <div class="<?= $colClass ?> portfolio-item filter-<?= $image['category_id'] ?> <?= htmlspecialchars($image['class']) ?>">
                <a href="#" 
                   class="lightbox-trigger d-block mb-4" 
                   data-src="/includes/plugins/gallery/images/<?= rawurlencode($image['filename']) ?>"
                   title="<?= htmlspecialchars($image['category_name']) ?>">
                    <img src="/includes/plugins/gallery/images/<?= rawurlencode($image['filename']) ?>" 
                         class="img-fluid rounded" 
                         alt="<?= htmlspecialchars($image['category_name']) ?>" loading="lazy" aria-label="Bild in Kategorie <?= htmlspecialchars($image['category_name']) ?>">
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav aria-label="Seiten-Navigation">
    <ul class="pagination justify-content-center mt-4">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="index.php?site=gallery&page=<?= $p ?>&category=<?= $categoryFilter ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body position-relative text-center">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="lightboxImage" src="" class="img-fluid rounded" alt="">
                <button class="btn position-absolute top-50 start-0 translate-middle-y text-white p-0 border-0" id="prevBtn" aria-label="Vorheriges Bild">
                    <i class="bi bi-chevron-left fs-1"></i>
                </button>
                <button class="btn position-absolute top-50 end-0 translate-middle-y text-white p-0 border-0" id="nextBtn" aria-label="Nächstes Bild">
                    <i class="bi bi-chevron-right fs-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const items = document.querySelectorAll('.portfolio-item');

    // Animation beim Anzeigen
    items.forEach((item, index) => {
        setTimeout(() => {
            item.style.display = 'block';
            void item.offsetWidth; // reflow
            item.classList.add('show');
        }, index * 100);
    });

    // Lightbox
    const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    const lightboxImage = document.getElementById('lightboxImage');

    // Bilder aus der aktuellen Seite für Lightbox sammeln
    const lightboxTriggers = document.querySelectorAll('.lightbox-trigger');
    let images = [];
    let currentIndex = 0;

    lightboxTriggers.forEach((trigger, index) => {
        const src = trigger.getAttribute('data-src');
        images.push(src);

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            currentIndex = index;
            showImage(currentIndex);
            modal.show();
        });
    });

    function showImage(index) {
        if (images[index]) {
            lightboxImage.classList.add('animate-in');
            setTimeout(() => {
                lightboxImage.src = images[index];
                lightboxImage.classList.remove('animate-in');
            }, 200);
        }
    }

    document.getElementById('prevBtn').addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        showImage(currentIndex);
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % images.length;
        showImage(currentIndex);
    });
});
</script>
