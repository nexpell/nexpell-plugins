<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('forum');

$tpl = new Template();
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style'] ?? '');

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Forum'
];

echo $tpl->loadTemplate("forum", "head", $data_array, 'plugin');


$sql = "
    SELECT 
        t.threadID,
        t.title AS threadTitle,
        c.title AS categoryTitle,
        MAX(p.created_at) AS last_post_date,
        MAX(p.postID) AS lastPostID,
        (
            SELECT u.username
            FROM plugins_forum_posts p2
            LEFT JOIN users u ON u.userID = p2.userID
            WHERE p2.threadID = t.threadID AND p2.is_deleted = 0
            ORDER BY p2.created_at DESC
            LIMIT 1
        ) AS lastUser,
        COUNT(p.postID) - 1 AS replyCount
    FROM plugins_forum_threads t
    LEFT JOIN plugins_forum_categories c ON t.catID = c.catID
    LEFT JOIN plugins_forum_posts p ON p.threadID = t.threadID AND p.is_deleted = 0
    WHERE t.is_locked = 0
    GROUP BY t.threadID
    ORDER BY last_post_date DESC
    LIMIT 5
";
?>

<div class="card mb-4">
  <div class="card-body p-3">
    <h5 class="mb-2">
      Letzte Beiträge
    </h5>
    <hr class="my-2">

    <?php
    if ($result = $_database->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $threadID = intval($row['threadID']);
            $lastPostID = intval($row['lastPostID']);
            $title = htmlspecialchars($row['threadTitle'] ?? 'Unbekannter Titel');
            $catTitle = htmlspecialchars($row['categoryTitle'] ?? 'Unbekannte Kategorie');
            $lastUser = htmlspecialchars($row['lastUser'] ?? 'Unbekannt');
            $replyCount = intval($row['replyCount']);
            $lastPostDate = is_numeric($row['last_post_date']) 
              ? date("d.m.Y", intval($row['last_post_date'])) 
              : "Datum unbekannt";

            $link = "index.php?site=forum&action=thread&id={$threadID}#post{$lastPostID}";
            ?>
            <div class="mb-2 pb-2 border-bottom small">
              <div class="text-muted mb-1" style="font-size: 0.8rem;"><?php echo $lastPostDate; ?></div>
              <div class="fw-semibold text-truncate" title="<?php echo $title; ?>">
                <a href="<?php echo $link; ?>" class="text-decoration-none fw-bold text-truncate">
                  <?php echo $title; ?>
                </a>
              </div>
              <div class="text-muted" style="font-size: 0.8rem;">
                Forum: <span class="text-primary"><?php echo $catTitle; ?></span><br>
                (<?php echo $replyCount; ?> Antworten) – <b><?php echo $lastUser; ?></b>
              </div>
            </div>
            <?php
        }
        $result->free();
    } else {
        echo "<div class='alert alert-danger'>Fehler: " . $_database->error . "</div>";
    }
    ?>
  </div>
</div>
