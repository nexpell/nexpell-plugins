<?php

use webspell\LanguageService;
use webspell\AccessControl;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['language'] = $_SESSION['language'] ?? 'de';

global $_database,$languageService;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('forum');

AccessControl::checkAdminAccess('forum');

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

#global $_database;


#if (!isAdmin()) {
#    die("Zugriff verweigert.");
#}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

function forum_redirect($action = 'categories') {
    header("Location: admincenter.php?site=admin_forum&action=" . $action);
    exit;
}

// === Kategorien: Anzeigen, Anlegen, Bearbeiten, Löschen ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'categories') {
    $postAction = $_POST['action'] ?? '';
    $catID = intval($_POST['catID'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $position = intval($_POST['position'] ?? 0);

    if ($postAction === 'add' && $title !== '') {
        $title = mysqli_real_escape_string($_database, $title);
        $description = mysqli_real_escape_string($_database, $description);
        safe_query("INSERT INTO plugins_forum_categories (title, description, position) VALUES ('$title', '$description', $position)");
        redirect('categories');
    }

    if ($postAction === 'edit' && $catID > 0) {
        $title = mysqli_real_escape_string($_database, $title);
        $description = mysqli_real_escape_string($_database, $description);
        safe_query("UPDATE plugins_forum_categories SET title='$title', description='$description', position=$position WHERE catID=$catID");
        redirect('categories');
    }

    if ($postAction === 'delete' && $catID > 0) {
        safe_query("DELETE FROM plugins_forum_categories WHERE catID=$catID");
        redirect('categories');
    }
}

// === Threads: Löschen ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'threads' && isset($_POST['delete_thread'])) {
    $threadID = intval($_POST['delete_thread']);
    safe_query("DELETE FROM plugins_forum_posts WHERE threadID = $threadID");
    safe_query("DELETE FROM plugins_forum_threads WHERE threadID = $threadID");
    redirect('threads');
}

// === Thread bearbeiten ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit_thread') {
    $threadID = intval($_GET['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $catID = intval($_POST['catID'] ?? 0);

    if ($title !== '' && $catID > 0) {
        $title = mysqli_real_escape_string($_database, $title);
        safe_query("UPDATE plugins_forum_threads SET title='$title', catID=$catID WHERE threadID=$threadID");
        redirect('threads');
    }
}

// === HTML BEGINN ===
?>

<div class="container py-4">
    <h1 class="mb-4">Forum Verwaltung</h1>

    <nav class="mb-3">
        <a class="btn btn-outline-primary me-2" href="admincenter.php?site=admin_forum&action=categories">Kategorien</a>
        <a class="btn btn-outline-primary me-2" href="admincenter.php?site=admin_forum&action=threads">Threads</a>
        <a class="btn btn-outline-primary" href="admincenter.php?site=admin_forum&action=users">Benutzer</a>
    </nav>
    <hr>

<?php if ($action === 'categories'):
    $res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
    $categories = [];
    while ($row = mysqli_fetch_assoc($res)) $categories[] = $row;
?>
    <h2>Kategorie hinzufügen</h2>
    <form method="post" class="mb-4">
        <input type="hidden" name="action" value="add">
        <div class="mb-2">
            <input type="text" class="form-control" name="title" placeholder="Titel" required>
        </div>
        <div class="mb-2">
            <textarea class="form-control" name="description" placeholder="Beschreibung"></textarea>
        </div>
        <div class="mb-2">
            <input type="number" class="form-control" name="position" value="0">
        </div>
        <button type="submit" class="btn btn-success">Kategorie anlegen</button>
    </form>

    <h2>Bestehende Kategorien</h2>
    <table class="table table-bordered table-hover">
        <thead>
        <tr><th>ID</th><th>Titel</th><th>Beschreibung</th><th>Position</th><th>Aktionen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
        <form method="post">
            <tr>
                <td><?=intval($cat['catID'])?></td>
                <td><input type="text" name="title" class="form-control" value="<?=htmlspecialchars($cat['title'])?>" required></td>
                <td><textarea name="description" class="form-control"><?=htmlspecialchars($cat['description'])?></textarea></td>
                <td><input type="number" name="position" class="form-control" value="<?=intval($cat['position'])?>"></td>
                <td>
                    <input type="hidden" name="catID" value="<?=intval($cat['catID'])?>">
                    <div class="d-flex gap-2">
                        <button name="action" value="edit" class="btn btn-primary btn-sm">Speichern</button>
                        <button name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Wirklich löschen?')">Löschen</button>
                    </div>
                </td>
            </tr>
        </form>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'threads'):
    $threads = [];
    $res = safe_query("
        SELECT t.threadID, t.title, t.created_at, c.title AS category_title, u.username
        FROM plugins_forum_threads t
        LEFT JOIN plugins_forum_categories c ON t.catID = c.catID
        LEFT JOIN users u ON t.userID = u.userID
        ORDER BY t.updated_at DESC
    ");
    while ($row = mysqli_fetch_assoc($res)) $threads[] = $row;
?>
    <h2>Threads</h2>
    <table class="table table-striped">
        <thead>
        <tr><th>ID</th><th>Titel</th><th>Kategorie</th><th>Autor</th><th>Datum</th><th>Aktionen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($threads as $t): ?>
        <tr>
            <td><?=intval($t['threadID'])?></td>
            <td><?=htmlspecialchars($t['title'])?></td>
            <td><?=htmlspecialchars($t['category_title'])?></td>
            <td><?=htmlspecialchars($t['username'] ?? 'Gast')?></td>
            <td><?=date('d.m.Y H:i', $t['created_at'])?></td>
            <td>
                <a href="admincenter.php?site=admin_forum&action=edit_thread&id=<?=intval($t['threadID'])?>" class="btn btn-sm btn-warning">Bearbeiten</a>
                <form method="post" class="d-inline">
                    <button name="delete_thread" value="<?=intval($t['threadID'])?>" class="btn btn-sm btn-danger" onclick="return confirm('Thread wirklich löschen?')">Löschen</button>
                </form>
                
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($action === 'edit_thread'):
    $threadID = intval($_GET['id'] ?? 0);
    $res = safe_query("SELECT * FROM plugins_forum_threads WHERE threadID=$threadID");
    $thread = mysqli_fetch_assoc($res);
    $cat_res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
?>
    <h2>Thread bearbeiten</h2>
    <form method="post">
        <div class="mb-2">
            <input type="text" class="form-control" name="title" value="<?=htmlspecialchars($thread['title'])?>" required>
        </div>
        <div class="mb-2">
            <select name="catID" class="form-select">
                <?php while ($cat = mysqli_fetch_assoc($cat_res)): ?>
                    <option value="<?=$cat['catID']?>" <?=($cat['catID']==$thread['catID'])?'selected':''?>><?=htmlspecialchars($cat['title'])?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

<?php elseif ($action === 'users'):
    $users = [];
    $res = safe_query("SELECT userID, username, email, registerdate FROM users ORDER BY username ASC");
    while ($row = mysqli_fetch_assoc($res)) $users[] = $row;
?>
    <h2>Benutzer</h2>
    <table class="table table-bordered">
        <thead>
        <tr><th>ID</th><th>Benutzername</th><th>Email</th><th>Registriert</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?=intval($u['userID'])?></td>
            <td><?=htmlspecialchars($u['username'])?></td>
            <td><?=htmlspecialchars($u['email'])?></td>
            <td><?=date('d.m.Y', $u['registerdate'])?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <h2>Forenkategorien & Threads</h2>

    <?php
    $categories = [];
    $res_cats = safe_query("SELECT catID, title, description FROM plugins_forum_categories ORDER BY title ASC");

    while ($cat = mysqli_fetch_assoc($res_cats)) {
        $catID = (int)$cat['catID'];

        $res_threads = safe_query("SELECT threadID, title, userID, created_at FROM plugins_forum_threads WHERE catID = $catID ORDER BY created_at DESC");
        $threads = [];
        while ($thread = mysqli_fetch_assoc($res_threads)) {
            $threads[] = $thread;
        }

        $categories[] = [
            'catID' => $catID,
            'title' => $cat['title'],
            'description' => $cat['description'],
            'threads' => $threads
        ];
    }
    ?>

    <?php foreach ($categories as $cat): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars($cat['title']) ?></strong>
                <div>
                    <a href="admincenter.php?site=admin_forum&action=edit_category&id=<?= $cat['catID'] ?>" class="btn btn-sm btn-light me-2">Bearbeiten</a>
                    <a href="admincenter.php?site=admin_forum&action=delete_category&id=<?= $cat['catID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Kategorie wirklich löschen?')">Löschen</a>
                </div>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($cat['description'])) ?></p>
                <?php if (!empty($cat['threads'])): ?>
                    <table class="table table-sm table-striped">
                        <thead>
                        <tr>
                            <th style="width: 33%;">Titel</th>
                            <th style="width: 33%;">Autor</th>
                            <th style="width: 20%;">Erstellt am</th>
                            <th style="width: 14%;">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cat['threads'] as $thread): ?>
                            <tr>
                                <td><?= htmlspecialchars($thread['title']) ?></td>
                                <td><?= htmlspecialchars($thread['userID']) ?></td>
                                <td><?= date('d.m.Y H:i', (int)$thread['created_at']) ?></td>
                                <td>
                                    <a href="admincenter.php?site=admin_forum&action=edit_thread&id=<?= $thread['threadID'] ?>" class="btn btn-sm btn-outline-primary me-1">Bearbeiten</a>
                                    <a href="admincenter.php?site=admin_forum&action=delete_thread&id=<?= $thread['threadID'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Thread wirklich löschen?')">Löschen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info mb-0">Keine Threads in dieser Kategorie.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>



    <hr>
    <a href="admincenter.php" class="btn btn-secondary">Zurück zum Admincenter</a>
</div>
