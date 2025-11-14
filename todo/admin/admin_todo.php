<?php
// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;

// Sprache initialisieren
$_SESSION['language'] = $_SESSION['language'] ?? 'de';
global $_database, $languageService;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('todo');

// Adminrechte prüfen
AccessControl::checkAdminAccess('todo');

// User prüfen
$userID = $_SESSION['userID'] ?? 0;
if ($userID <= 0) {
    die("Kein gültiger Benutzer angemeldet.");
}

// Action-Parameter auswerten
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $_database, $userID;

    // 🆕 Unterscheiden ob neu oder bearbeiten
    $isEdit = isset($_POST['edit_id']);

    $task        = trim($_POST[$isEdit ? 'task_edit' : 'task'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $progress    = (int)($_POST['progress'] ?? 0);
    $done        = isset($_POST['done']) ? 1 : 0;

    if ($task === '') {
        echo '<div class="alert alert-danger">⚠️ Kein Titel angegeben.</div>';
        exit;
    }

    if ($isEdit) {
        // === UPDATE ===
        $edit_id = (int)$_POST['edit_id'];

        $sql = "UPDATE plugins_todo 
                SET task = ?, description = ?, priority = ?, due_date = ?, progress = ?, done = ?, updated_at = NOW()
                WHERE id = ? AND userID = ?";
        $stmt = $_database->prepare($sql);
        $stmt->bind_param("ssssiiii", $task, $description, $priority, $due_date, $progress, $done, $edit_id, $userID);

        if ($stmt->execute()) {
            echo '<div class="alert alert-info">📝 Aufgabe aktualisiert!</div>';
            redirect('admincenter.php?site=admin_todo', "", 2);
        } else {
            echo '<div class="alert alert-danger">❌ Fehler beim Update: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();

    } else {
        // === INSERT ===
        $sql = "INSERT INTO plugins_todo 
                (userID, task, description, priority, due_date, progress, done)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $_database->prepare($sql);
        $stmt->bind_param("issssii", $userID, $task, $description, $priority, $due_date, $progress, $done);

        if ($stmt->execute()) {
            echo '<div class="alert alert-success">✅ Aufgabe hinzugefügt!</div>';
            redirect('admincenter.php?site=admin_todo', "", 2);
        } else {
            echo '<div class="alert alert-danger">❌ Fehler beim Insert: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    }
    exit;
}

if (isset($_GET['done_id'])) {
    $done_id = (int)$_GET['done_id'];
    $userID_int = (int)($_SESSION['userID'] ?? 0);

    global $_database;

    $stmt = $_database->prepare("UPDATE plugins_todo SET done = 1, updated_at = NOW() WHERE id = ? AND userID = ?");
    $stmt->bind_param("ii", $done_id, $userID_int);

    if ($stmt->execute()) {
        echo '<div class="alert alert-success">✅ Aufgabe als erledigt markiert!</div>';
        redirect('admincenter.php?site=admin_todo', "", 2);
    } else {
        echo '<div class="alert alert-danger">❌ Fehler: ' . htmlspecialchars($stmt->error) . '</div>';
    }

    $stmt->close();
    exit;
}

// Todo löschen
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    $userID_int = (int)($_SESSION['userID'] ?? 0);

    global $_database;

    $stmt = $_database->prepare("DELETE FROM plugins_todo WHERE id = ? AND userID = ?");
    $stmt->bind_param("ii", $del_id, $userID_int);

    if ($stmt->execute()) {
        echo '<div class="alert alert-danger">🗑️ Aufgabe gelöscht!</div>';
        redirect('admincenter.php?site=admin_todo', "", 2);
    } else {
        echo '<div class="alert alert-danger">❌ Fehler beim Löschen: ' . htmlspecialchars($stmt->error) . '</div>';
    }

    $stmt->close();
    exit;
}




// Einzelnes Todo zum Bearbeiten laden
$todo_edit = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $_database->prepare("SELECT * FROM plugins_todo WHERE id = ? AND userID = ?");
    $stmt->bind_param("ii", $edit_id, $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $todo_edit = $result->fetch_assoc();
    $stmt->close();
}

// Todos laden (nur für Listenansicht)
$todos = [];
if (!$action) {
    $stmt = $_database->prepare("SELECT * FROM plugins_todo WHERE userID = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $todos[] = $row;
    }
    $stmt->close();
}
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-list-check"></i> Aufgabenverwaltung</div>
            <div>
                <a href="admincenter.php?site=admin_todo" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Zurück
                </a>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light mb-0">
                <li class="breadcrumb-item"><a href="admincenter.php?site=admin_todo">Aufgabenverwaltung</a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= $action === 'edit' ? 'Bearbeiten' : 'Neu anlegen' ?>
                </li>
            </ol>
        </nav>

        <div class="card-body p-4">
            <h4 class="mb-3"><?= $action === 'edit' ? 'Aufgabe bearbeiten' : 'Neue Aufgabe hinzufügen' ?></h4>

            <form method="post">
                <?php if ($todo_edit): ?>
                    <input type="hidden" name="edit_id" value="<?= $todo_edit['id'] ?>">
                <?php endif; ?>

                <input type="text" 
                       name="<?= $todo_edit ? 'task_edit' : 'task' ?>" 
                       class="form-control mb-2" 
                       placeholder="<?=$languageService->get('new_task_placeholder')?>" 
                       value="<?= htmlspecialchars($todo_edit['task'] ?? '') ?>" required>

                <textarea name="description" class="form-control mb-2 ckeditor" placeholder="Beschreibung"><?= isset($todo_edit['description']) ? htmlspecialchars($todo_edit['description']) : '' ?></textarea>

                <select name="priority" class="form-select mb-2">
                    <option value="low" <?=($todo_edit['priority'] ?? '') === 'low' ? 'selected' : ''?>>Niedrig</option>
                    <option value="medium" <?=($todo_edit['priority'] ?? 'medium') === 'medium' ? 'selected' : ''?>>Mittel</option>
                    <option value="high" <?=($todo_edit['priority'] ?? '') === 'high' ? 'selected' : ''?>>Hoch</option>
                </select>

                <input type="date" name="due_date" class="form-control mb-2" 
                       value="<?= htmlspecialchars($todo_edit['due_date'] ?? '') ?>" />

                <label class="form-label">Fortschritt: 
                    <span id="progressValue"><?= htmlspecialchars($todo_edit['progress'] ?? 0) ?></span>%
                </label>
                <input type="range" name="progress" min="0" max="100" 
                       value="<?= htmlspecialchars($todo_edit['progress'] ?? 0) ?>" 
                       class="form-range mb-3" 
                       oninput="document.getElementById('progressValue').textContent = this.value;">

                <?php if ($action === 'edit' && $todo_edit): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="done" name="done" value="1" <?= !empty($todo_edit['done']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="done">Erledigt</label>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn <?= $action === 'edit' ? 'btn-warning' : 'btn-primary' ?>">
                    <i class="bi <?= $action === 'edit' ? 'bi-pencil' : 'bi-plus' ?>"></i>
                    <?= $action === 'edit' ? 'Speichern' : 'Hinzufügen' ?>
                </button>
                <a href="admincenter.php?site=admin_todo" class="btn btn-secondary">Abbrechen</a>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-list-check"></i> Aufgabenverwaltung</div>
            <div>
                <a href="admincenter.php?site=admin_todo&action=add" class="btn btn-success btn-sm">
                    <i class="bi bi-plus"></i> Neu
                </a>
            </div>
        </div>

        <div class="card-body p-4">
            <ul class="list-group">
                <?php foreach ($todos as $todo): ?>
                    <?php
                        $priorityClass = match($todo['priority']) {
                            'high' => 'border-danger',
                            'low' => 'border-success',
                            default => 'border-secondary'
                        };
                    ?>
                    <li class="list-group-item border-start <?= $priorityClass ?> d-flex justify-content-between align-items-start <?= $todo['done'] ? 'text-muted text-decoration-line-through' : '' ?>">
                        <div class="w-100">
                            <strong><?= htmlspecialchars($todo['task']) ?></strong>
                            <div class="small text-muted">
                                Priorität: <?= htmlspecialchars($todo['priority']) ?> |
                                Fällig: <?= htmlspecialchars((string)($todo['due_date'] ?? '')) ?> |
                                Bearbeitet: <?= htmlspecialchars($todo['updated_at']) ?>
                            </div>
                            <?php if (!empty($todo['description'])): ?>
                                <div class="mt-1"><?= $todo['description'] ?></div>
                            <?php endif; ?>

                            <?php
                                $progress = (int)$todo['progress'];
                                $color = $progress >= 80 ? 'bg-success' : ($progress >= 50 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <div class="progress my-1" style="height: 6px;">
                                <div class="progress-bar <?= $color ?>" style="width: <?= $progress ?>%;"></div>
                            </div>
                            <?= $todo['progress'] ?>%
                        </div>
                        <div class="ms-3 d-flex flex-nowrap">
                            <?php if (!$todo['done']): ?>
                                <a href="admincenter.php?site=admin_todo&done_id=<?= $todo['id'] ?>" class="btn btn-success btn-sm me-1"><?=$languageService->get('mark_done')?></a>
                            <?php endif; ?>
                            <a href="admincenter.php?site=admin_todo&action=edit&edit_id=<?= $todo['id'] ?>" class="btn btn-warning btn-sm me-1"><?=$languageService->get('edit')?></a>
                            <a href="admincenter.php?site=admin_todo&del_id=<?= $todo['id'] ?>" onclick="return confirm('<?=$languageService->get('confirm_delete')?>')" class="btn btn-danger btn-sm"><?=$languageService->get('delete')?></a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
