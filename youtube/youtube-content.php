<?php
// Diese Datei generiert nur den HTML-Code für die YouTube-Videos und die Paginierung.
// Sie wird dynamisch von JavaScript über eine fetch-Anfrage aufgerufen.

// Video-IDs und Paginierungsvariablen aus den GET-Parametern auslesen
$fullWidthVideo = $_GET['fullWidthVideoId'] ?? null;
$videosToDisplay = isset($_GET['otherVideoIds']) ? explode(',', $_GET['otherVideoIds']) : [];
$displayMode = $_GET['displayMode'] ?? 'grid';
$page = intval($_GET['page'] ?? 1);
$totalVideos = intval($_GET['totalVideos'] ?? 0);
$videosPerPageFirst = intval($_GET['videosPerPageFirst'] ?? 0);
$videosPerPageOther = intval($_GET['videosPerPageOther'] ?? 0);
$defaultVideoId = 'D_x8ms9nGQw'; // Fallback-ID

// Hilfsfunktion zur Prüfung der Video-ID
function isVideoValid($videoId) {
    return is_string($videoId) && strlen($videoId) === 11;
}

// Berechnung der Paginierung
$videosAfterFirst = max(0, $totalVideos - $videosPerPageFirst);
$totalPagesOther = ceil($videosAfterFirst / $videosPerPageOther);
$totalPages = ($totalVideos > $videosPerPageFirst) ? 1 + $totalPagesOther : 1;

?>

<div class="youtube-widget-container">
    <?php if ($fullWidthVideo): ?>
        <div class="youtube-video-full">
            <iframe width="100%" height="315"
                src="https://www.youtube.com/embed/<?php echo htmlspecialchars(isVideoValid($fullWidthVideo) ? $fullWidthVideo : $defaultVideoId); ?>"
                frameborder="0" allowfullscreen>
            </iframe>
        </div>
        <div class="youtube-video-grid">
            <?php foreach ($videosToDisplay as $videoId): ?>
                <div class="youtube-video-grid-item">
                    <iframe width="100%" height="215"
                        src="https://www.youtube.com/embed/<?php echo htmlspecialchars(isVideoValid($videoId) ? $videoId : $defaultVideoId); ?>"
                        frameborder="0" allowfullscreen>
                    </iframe>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="<?php echo $displayMode === 'grid' ? 'youtube-video-grid' : 'youtube-video-list'; ?>">
            <?php foreach ($videosToDisplay as $videoId): ?>
                <div class="<?php echo $displayMode === 'grid' ? 'youtube-video-grid-item' : 'youtube-video-list-item'; ?>">
                    <iframe width="100%" height="215"
                        src="https://www.youtube.com/embed/<?php echo htmlspecialchars(isVideoValid($videoId) ? $videoId : $defaultVideoId); ?>"
                        frameborder="0" allowfullscreen>
                    </iframe>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="youtube-pagination mt-3">
    <?php if ($page > 1): ?>
        <a href="index.php?site=youtube&page=<?php echo $page - 1; ?>" class="btn btn-secondary">← Vorherige</a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
        <a href="index.php?site=youtube&page=<?php echo $page + 1; ?>" class="btn btn-secondary">Nächste →</a>
    <?php endif; ?>
</div>
