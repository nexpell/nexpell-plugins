<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;

$_SESSION['language'] = $_SESSION['language'] ?? 'de';

global $_database,$languageService;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('forum');

AccessControl::checkAdminAccess('forum');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
        echo '<div class="alert alert-success" role="alert">Erfolgreich erstellt!</div>';
        redirect('admincenter.php?site=admin_forum', "", 3);
    }

    if ($postAction === 'edit' && $catID > 0) {
        $title = mysqli_real_escape_string($_database, $title);
        $description = mysqli_real_escape_string($_database, $description);
        safe_query("UPDATE plugins_forum_categories SET title='$title', description='$description', position=$position WHERE catID=$catID");
        echo '<div class="alert alert-success" role="alert">Erfolgreich editiert!</div>';
        redirect('admincenter.php?site=admin_forum', "", 3);
    }

    if ($postAction === 'delete' && $catID > 0) {
        safe_query("DELETE FROM plugins_forum_categories WHERE catID=$catID");
        echo '<div class="alert alert-danger" role="alert">Erfolgreich gelöscht!</div>';
        redirect('admincenter.php?site=admin_forum', "", 3);
    }
}


// === Threads: Löschen ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'threads' && isset($_POST['delete_thread'])) {
    $threadID = intval($_POST['delete_thread']);
    safe_query("DELETE FROM plugins_forum_posts WHERE threadID = $threadID");
    safe_query("DELETE FROM plugins_forum_threads WHERE threadID = $threadID");
    echo '<div class="alert alert-danger" role="alert">Erfolgreich gelöscht!</div>';
    redirect('admincenter.php?site=admin_forum', "", 3);
}

// === Thread bearbeiten ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit_thread') {
    $threadID = intval($_GET['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $catID = intval($_POST['catID'] ?? 0);

    if ($title !== '' && $catID > 0) {
        $title = mysqli_real_escape_string($_database, $title);
        safe_query("UPDATE plugins_forum_threads SET title='$title', catID=$catID WHERE threadID=$threadID");
        echo '<div class="alert alert-success" role="alert">Erfolgreich editiert!</div>';
        redirect('admincenter.php?site=admin_forum', "", 3);
    }
}





// === HTML BEGINN ===
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> Forum verwalten</div>
        <div>
            <a class="btn btn-primary btn-sm" href="admincenter.php?site=admin_forum&action=board">Board</a>
            <a class="btn btn-primary btn-sm" href="admincenter.php?site=admin_forum&action=categories">Kategorien</a>
            <a class="btn btn-primary btn-sm" href="admincenter.php?site=admin_forum&action=threads">Threads</a>
            <a class="btn btn-primary btn-sm" href="admincenter.php?site=admin_forum&action=user">Benutzer</a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_forum">Forum verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav> 

    <div class="card-body p-0">
        <div class="container py-5">


<?php $action = $_GET['action'] ?? ''; ?>


<?php
if ($action === 'board') :

    // Verarbeiten POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $actionType = $_POST['action'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $id = intval($_POST['id'] ?? 0);

        if ($actionType === 'add' && $title !== '') {
            safe_query("INSERT INTO plugins_forum_boards (title, description) VALUES ('" . escape($title) . "', '" . escape($description) . "')");
            echo '<div class="alert alert-success">Board hinzugefügt.</div>';
        } elseif ($actionType === 'edit' && $id > 0 && $title !== '') {
            safe_query("UPDATE plugins_forum_boards SET title = '" . escape($title) . "', description = '" . escape($description) . "' WHERE id = $id");
            echo '<div class="alert alert-success">Board aktualisiert.</div>';
        } elseif ($actionType === 'delete' && $id > 0) {
            safe_query("DELETE FROM plugins_forum_boards WHERE id = $id");
            echo '<div class="alert alert-success">Board gelöscht.</div>';
        } else {
            echo '<div class="alert alert-danger">Fehler bei der Verarbeitung.</div>';
        }
    }

    // Daten laden
    $boards = safe_query("SELECT * FROM plugins_forum_boards ORDER BY id ASC");
    ?>

    <form method="post" class="mb-4">
        <input type="hidden" name="action" value="add">
        <div class="mb-2"><input type="text" class="form-control" name="title" placeholder="Titel" required></div>
        <div class="mb-2"><textarea class="form-control" name="description" placeholder="Beschreibung"></textarea></div>
        <button type="submit" class="btn btn-success">Board anlegen</button>
    </form>

    <h2>Bestehende Boards</h2>
    <table class="table table-bordered table-hover">
        <thead>
            <tr><th>ID</th><th>Titel</th><th>Beschreibung</th><th>Aktionen</th></tr>
        </thead>
        <tbody>
        <?php while ($board = mysqli_fetch_assoc($boards)): ?>
        <form method="post">
            <tr>
                <td><?= intval($board['id']) ?></td>
                <td><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($board['title']) ?>" required></td>
                <td><textarea name="description" class="form-control"><?= htmlspecialchars($board['description']) ?></textarea></td>
                <td>
                    <input type="hidden" name="id" value="<?= intval($board['id']) ?>">
                    <div class="d-flex gap-2">
                        <button name="action" value="edit" class="btn btn-primary btn-sm">Speichern</button>
                        <button name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Wirklich löschen?')">Löschen</button>
                    </div>
                </td>
            </tr>
        </form>
        <?php endwhile; ?>
        </tbody>
    </table>










<?php elseif ($action === 'categories'):

// Holen der vorhandenen Boards für das Dropdown (einmal vor Formular und Liste)
$boards = [];
$res_boards = safe_query("SELECT id, title FROM plugins_forum_boards ORDER BY title ASC");
while ($board = mysqli_fetch_assoc($res_boards)) {
    $boards[] = $board;
}

// Backend: Verarbeitung von POST (Kategorie anlegen, bearbeiten, löschen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $position = intval($_POST['position'] ?? 0);
    $group_id = $_POST['group_id'];
    $catID = intval($_POST['catID'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        if ($title === '') {
            echo '<div class="alert alert-danger">Bitte einen Titel angeben.</div>';
        } elseif ($group_id === 0) {
            echo '<div class="alert alert-danger">Bitte ein gültiges Board auswählen.</div>';
        } else {
            // Prüfe, ob Board existiert
            $res_check = safe_query("SELECT id FROM plugins_forum_boards WHERE id = $group_id");
            if (mysqli_num_rows($res_check) === 0) {
                echo '<div class="alert alert-danger">Ungültiges Board ausgewählt.</div>';
            } else {
                if ($action === 'add') {
                    safe_query("INSERT INTO plugins_forum_categories (group_id, title, description, position) VALUES ($group_id, '" . escape($title) . "', '" . escape($description) . "', $position)");
                    echo '<div class="alert alert-success">Kategorie wurde erfolgreich hinzugefügt.</div>';
                } elseif ($action === 'edit' && $catID > 0) {
                    safe_query("UPDATE plugins_forum_categories SET group_id = $group_id, title = '" . escape($title) . "', description = '" . escape($description) . "', position = $position WHERE catID = $catID");
                    echo '<div class="alert alert-success">Kategorie wurde aktualisiert.</div>';
                }
            }
        }
    }

}

// Kategorien laden
$res = safe_query("SELECT * FROM plugins_forum_categories ORDER BY position ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($res)) {
    $categories[] = $row;
}
?>

<h2>Kategorie hinzufügen</h2>
<form method="post" class="mb-4">
    <input type="hidden" name="action" value="add">
    <div class="mb-2">
        <select name="group_id" class="form-control" required>
            <option value="">Board auswählen</option>
            <?php foreach ($boards as $board): ?>
                <option value="<?= intval($board['id']) ?>"><?= htmlspecialchars($board['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-2"><input type="text" class="form-control" name="title" placeholder="Titel" required></div>
    <div class="mb-2"><textarea class="form-control" name="description" placeholder="Beschreibung"></textarea></div>
    
    <div class="mb-2"><input type="number" class="form-control" name="position" value="0"></div>
    <button type="submit" class="btn btn-success">Kategorie anlegen</button>
</form>

<h2>Bestehende Kategorien</h2>
<table class="table table-bordered table-hover">
    <thead>
        <tr>
            <th>ID</th>
            <th>Titel</th>
            <th>Beschreibung</th>
            <th>Board</th>
            <th>Position</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $cat): ?>
        <form method="post">
            <tr>
                <td><?= intval($cat['catID']) ?></td>
                <td><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($cat['title']) ?>" required></td>
                <td><textarea name="description" class="form-control"><?= htmlspecialchars($cat['description']) ?></textarea></td>
                <td>
                    <select name="group_id" class="form-control" required>
                        <?php foreach ($boards as $board): ?>
                            <option value="<?= intval($board['id']) ?>" <?= ($board['id'] == $cat['group_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($board['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="position" class="form-control" value="<?= intval($cat['position']) ?>"></td>
                <td>
                    <input type="hidden" name="catID" value="<?= intval($cat['catID']) ?>">
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
        <thead><tr><th>ID</th><th>Titel</th><th>Kategorie</th><th>Autor</th><th>Datum</th><th>Aktionen</th></tr></thead>
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
            <select name="catID" class="form-select">
                <?php while ($cat = mysqli_fetch_assoc($cat_res)): ?>
                    <option value="<?=$cat['catID']?>" <?=($cat['catID']==$thread['catID'])?'selected':''?>><?=htmlspecialchars($cat['title'])?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-2">
            <input type="text" class="form-control" name="title" value="<?=htmlspecialchars($thread['title'])?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>




<?php elseif ($action === 'user'):

    // 1. Hole alle User
    $resUsers = safe_query("SELECT userID, username, email, registerdate, is_locked FROM users ORDER BY username ASC");
    $users = [];
    while ($row = mysqli_fetch_assoc($resUsers)) {
        $users[$row['userID']] = $row;
    }

    // 2. Hole alle Rollen-Zuweisungen für alle Nutzer
    $resRoles = safe_query("SELECT userID, roleID FROM user_role_assignments");
    $rolesByUser = [];
    while ($row = mysqli_fetch_assoc($resRoles)) {
        $rolesByUser[$row['userID']][] = intval($row['roleID']);
    }

    // 3. Lade alle Rollen aus user_roles in eine Map
    $resRoleNames = safe_query("SELECT roleID, role_name FROM user_roles");
    $roleMap = [];
    while ($row = mysqli_fetch_assoc($resRoleNames)) {
        $roleMap[intval($row['roleID'])] = $row['role_name'];
    }

    // 4. Funktion zur Rollennamen-Auflösung
    function getRoleName($roleID, $roleMap) {
        return $roleMap[$roleID] ?? 'Unbekannt';
    }
?>
    <h2>Benutzer</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Email</th>
                <th>Registriert</th>
                <th>Status</th>
                <th>Rollen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $userID => $user): ?>
            <tr>
                <td><?= intval($user['userID']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($user['registerdate'])) ?></td>
                <td>
                    <?= $user['is_locked'] ? '<span class="badge bg-danger">gesperrt</span>' : '<span class="badge bg-success">aktiv</span>' ?>
                </td>
                <td>
                    <?php
                    if (isset($rolesByUser[$userID])) {
                        $roleNames = array_map(function($rid) use ($roleMap) {
                            return getRoleName($rid, $roleMap);
                        }, $rolesByUser[$userID]);

                        echo '<span class="badge bg-primary">' . implode('</span> <span class="badge bg-secondary">', $roleNames) . '</span>';
                    } else {
                        echo '<span class="badge bg-warning">Keine Rolle</span>';
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>



















<?php else: ?>







<?php
$boards = [];
$res_boards = safe_query("SELECT id, title, description FROM plugins_forum_boards ORDER BY title ASC");

while ($board = mysqli_fetch_assoc($res_boards)) {
    $boardID = (int)$board['id'];

    // Kategorien zum aktuellen Board laden
    $res_cats = safe_query("SELECT catID, title, description FROM plugins_forum_categories WHERE group_id = $boardID ORDER BY position ASC");
    $categories = [];

    while ($cat = mysqli_fetch_assoc($res_cats)) {
        $catID = (int)$cat['catID'];

        $res_threads = safe_query("
            SELECT t.threadID, t.title, t.userID, u.username, t.created_at 
            FROM plugins_forum_threads t
            LEFT JOIN users u ON t.userID = u.userID
            WHERE t.catID = $catID
            ORDER BY t.created_at DESC
        ");
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

    $boards[] = [
        'id' => $boardID,
        'title' => $board['title'],
        'description' => $board['description'],
        'categories' => $categories
    ];
}
?>

<?php foreach ($boards as $board): ?>
    <div class="card mb-3 ms-3">
        <div class="card-header">
            <h3><?= htmlspecialchars($board['title']) ?></h3>
            <p><?= nl2br(htmlspecialchars($board['description'])) ?></p>
        </div>
        <div class="card-body">
        <?php if (!empty($board['categories'])): ?>
            <?php foreach ($board['categories'] as $cat): ?>
                <div class="card mb-3 ms-3">
                    <div class="card-header">
                        <h5><?= htmlspecialchars($cat['title']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p><?= nl2br(htmlspecialchars($cat['description'])) ?></p>

                        <?php if (!empty($cat['threads'])): ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 33%;">Titel</th>
                                        <th style="width: 33%;">Autor</th>
                                        <th style="width: 33%;">Erstellt am</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cat['threads'] as $thread): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($thread['title']) ?></td>
                                            <td><?= htmlspecialchars($thread['username'] ?? 'Unbekannt') ?></td>
                                            <td><?= date('d.m.Y H:i', (int)$thread['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">Keine Threads in dieser Kategorie.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info ms-3">Keine Kategorien in diesem Board.</div>
        <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>


<?php endif; ?>

