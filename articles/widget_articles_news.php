<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('articles');

// Neueste Artikel (max. 3)
$latest = safe_query("
    SELECT * FROM plugins_articles 
    WHERE is_active = 1 
    ORDER BY updated_at DESC 
    LIMIT 3
");

// Hilfsfunktion: Text kürzen
function shortenText($text, $length = 200) {
    $text = trim($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '…';
}
?>

<div class="row gy-4 mt-4">
    <?php while ($article = mysqli_fetch_array($latest)): 
        $title = htmlspecialchars($article['title']);
        $content = nl2br(shortenText(strip_tags($article['content']), 200));
        $image = $article['banner_image'] 
            ? 'includes/plugins/articles/images/article/' . $article['banner_image'] 
            : 'includes/plugins/articles/images/no-image.jpg';

        // Timestamp aus integer-Feld oder Unix-Timestamp
        $timestamp = (int)$article['updated_at'];
        $isNew = (time() - $timestamp) < (7 * 24 * 60 * 60);
    ?>
    <div class="col-lg-4 col-md-6 col-sm-12">
        <div class="card border-start border-4 border-primary shadow-sm h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title d-flex align-items-center justify-content-between">
                    <span><?= $title ?></span>
                    <?php if ($isNew): ?>
                        <span class="badge bg-danger ms-2"><?= $languageService->get('new') ?></span>
                    <?php endif; ?>
                </h5>
                <p class="card-text text-muted small mb-2">
                    <?= ($timestamp > 0) ? date('d.m.Y H:i', $timestamp) : '<em>Kein gültiges Datum</em>' ?>
                </p>
                <p class="card-text flex-grow-1"><?= $content ?></p>
                <a href="index.php?site=articles&action=watch&id=<?= $article['id'] ?>" class="btn btn-sm btn-outline-primary mt-auto">
                    <?= $languageService->get('read_more') ?>
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

