<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('gallery');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
  'class'    => $class,
  'title' => $languageService->get('gallery'),
  'subtitle' => 'gallery'
];
echo $tpl->loadTemplate("gallery", "head", $data_array, 'plugin');

$data_array = [
  'title'  => ''
];

echo $tpl->loadTemplate("gallery", "content_head", $data_array, 'plugin');

$columns = 4;
$rowsPerPage = 3;
$imagesPerPage = $columns * $rowsPerPage;

// Aktuelle Seite
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Gesamtanzahl Bilder
$countResult = $_database->query("SELECT COUNT(*) as total FROM plugins_gallery");
$totalImages = 0;
if ($countResult) {
    $row = $countResult->fetch_assoc();
    $totalImages = (int)$row['total'];
}

// Seitenanzahl
$totalPages = max(1, ceil($totalImages / $imagesPerPage));
if ($page > $totalPages) $page = $totalPages;

// Offset berechnen
$offset = ($page - 1) * $imagesPerPage;

// Bilder laden
$stmt = $_database->prepare("SELECT filename, class FROM plugins_gallery ORDER BY position ASC, id ASC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $imagesPerPage);
$stmt->execute();
$result = $stmt->get_result();

$images = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
}
?>

<!-- Galerie -->
<div class="grid-wrapper">
    <?php foreach ($images as $image): ?>
        <div class="<?= htmlspecialchars($image['class']) ?>">
            <img src="/includes/plugins/gallery/images/<?= htmlspecialchars($image['filename']) ?>" alt="" loading="lazy">
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<nav aria-label="Seiten-Navigation">
  <ul class="pagination justify-content-center mt-4">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="index.php?site=gallery&page=<?= $p ?>">
                <?= $p ?>
            </a>
        </li>
    <?php endfor; ?>
  </ul>
</nav>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>