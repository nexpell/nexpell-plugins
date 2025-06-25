<?php

use webspell\LanguageService;
use webspell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $_database,$languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('todo');

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('todo');

// UserID aus Session holen (muss definiert sein)
$userID = $_SESSION['userID'] ?? 0;
if ($userID <= 0) {
    // Kein gültiger User, ggf. Zugriff verweigern oder umleiten
    die("Kein gültiger Benutzer angemeldet.");
}

// Neues Todo hinzufügen


// Neues Todo hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task']) && trim($_POST['task']) !== '' && !isset($_POST['edit_id'])) {
    $task = trim($_POST['task']);
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    // due_date: Wenn leer, dann NULL, sonst Wert übernehmen
    $due_date_input = $_POST['due_date'] ?? '';
    $due_date = !empty($due_date_input) ? $due_date_input : null;

    $progress = (int)($_POST['progress'] ?? 0);

    $stmt = $_database->prepare("INSERT INTO plugins_todo (userID, task, description, priority, due_date, progress) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $userID, $task, $description, $priority, $due_date, $progress);
    $stmt->execute();
    $stmt->close();

    echo '<div class="alert alert-success" role="alert">Erfolgreich erstellt!</div>';
    redirect('admincenter.php?site=admin_todo', "", 3);
    exit;
}

// Todo bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['task_edit'])) {
    $edit_id = (int)$_POST['edit_id'];
    $task_edit = trim($_POST['task_edit']);
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    // due_date: Wenn leer, dann NULL, sonst Wert übernehmen
    $due_date_input = $_POST['due_date'] ?? '';
    $due_date = !empty($due_date_input) ? $due_date_input : null;

    $progress = (int)($_POST['progress'] ?? 0);

    $stmt = $_database->prepare("UPDATE plugins_todo SET task = ?, description = ?, priority = ?, due_date = ?, progress = ?, updated_at = NOW() WHERE id = ? AND userID = ?");
    $stmt->bind_param("ssssiii", $task_edit, $description, $priority, $due_date, $progress, $edit_id, $userID);
    $stmt->execute();
    $stmt->close();

    echo '<div class="alert alert-info" role="alert">Aufgabe bearbeitet!</div>';
    redirect('admincenter.php?site=admin_todo', "", 3);
    exit;
}


// Todo erledigen
if (isset($_GET['done_id'])) {
    $done_id = (int)$_GET['done_id'];

    $stmt = $_database->prepare("UPDATE plugins_todo SET done = 1 WHERE id = ? AND userID = ?");
    $stmt->bind_param("ii", $done_id, $userID);
    $stmt->execute();
    $stmt->close();

    echo '<div class="alert alert-success" role="alert">Erfolgreich erledigt!</div>';
    redirect('admincenter.php?site=admin_todo', "", 3);
    exit;
}

// Todo löschen
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];

    $stmt = $_database->prepare("DELETE FROM plugins_todo WHERE id = ? AND userID = ?");
    $stmt->bind_param("ii", $del_id, $userID);
    $stmt->execute();
    $stmt->close();

    echo '<div class="alert alert-danger" role="alert">Aufgabe gelöscht!</div>';
    redirect('admincenter.php?site=admin_todo', "", 3);
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

// Todos laden
$stmt = $_database->prepare("SELECT * FROM plugins_todo WHERE userID = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$todos = [];
while ($row = $result->fetch_assoc()) {
    $todos[] = $row;
}
$stmt->close();
?>

<div class="container todo-list mt-4">
    <h1><?=$languageService->get('todo_title')?></h1>

    <form method="post" class="mb-4">
        <?php if ($todo_edit): ?>
            <input type="hidden" name="edit_id" value="<?=$todo_edit['id']?>">
        <?php endif; ?>

        <input type="text" name="<?= $todo_edit ? 'task_edit' : 'task' ?>" class="form-control mb-2" placeholder="<?=$languageService->get('new_task_placeholder')?>" value="<?=htmlspecialchars($todo_edit['task'] ?? '')?>" required>
        <textarea name="description" class="form-control mb-2" placeholder="Beschreibung"><?=htmlspecialchars($todo_edit['description'] ?? '')?></textarea>

        <select name="priority" class="form-select mb-2">
            <option value="low" <?=($todo_edit['priority'] ?? '') === 'low' ? 'selected' : ''?>>Niedrig</option>
            <option value="medium" <?=($todo_edit['priority'] ?? 'medium') === 'medium' ? 'selected' : ''?>>Mittel</option>
            <option value="high" <?=($todo_edit['priority'] ?? '') === 'high' ? 'selected' : ''?>>Hoch</option>
        </select>

        <input type="date" name="due_date" class="form-control mb-2" value="<?=htmlspecialchars($todo_edit['due_date'] ?? '')?>" />

        <label class="form-label">Fortschritt: <span id="progressValue"><?=htmlspecialchars($todo_edit['progress'] ?? 0)?></span>%</label>
        <input type="range" name="progress" min="0" max="100" value="<?=htmlspecialchars($todo_edit['progress'] ?? 0)?>" class="form-range mb-3" oninput="document.getElementById('progressValue').textContent = this.value;">

        <button type="submit" class="btn <?= $todo_edit ? 'btn-warning' : 'btn-primary' ?>">
            <?= $todo_edit ? $languageService->get('button_edit') : $languageService->get('button_add') ?>
        </button>
        <?php if ($todo_edit): ?>
            <a href="admincenter.php?site=admin_todo" class="btn btn-secondary"><?=$languageService->get('cancel')?></a>
        <?php endif; ?>
    </form>

    <ul class="list-group">
        <?php foreach($todos as $todo): ?>
            <?php
                $priorityClass = match($todo['priority']) {
                    'high' => 'border-danger',
                    'low' => 'border-success',
                    default => 'border-secondary'
                };
            ?>
            <li class="list-group-item border-start <?= $priorityClass ?> d-flex justify-content-between align-items-start <?= $todo['done'] ? 'text-muted text-decoration-line-through' : '' ?>">
                <div class="w-100">
                    <strong><?=htmlspecialchars($todo['task'])?></strong>
                    <div class="small text-muted">
                        Priorität: <?=htmlspecialchars($todo['priority'])?> |
                        Fällig: <?=htmlspecialchars((string)($todo_edit['due_date'] ?? ''))?> |
                        Bearbeitet: <?=htmlspecialchars($todo['updated_at'])?>
                    </div>
                    <?php if (!empty($todo['description'])): ?>
                        <div class="mt-1"><?=htmlspecialchars((string)$todo['description'])?></div>
                    <?php endif; ?>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: <?=$todo['progress']?>%;" aria-valuenow="<?=$todo['progress']?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="ms-3 d-flex flex-nowrap">
                    <?php if (!$todo['done']): ?>
                        <a href="admincenter.php?site=admin_todo&done_id=<?= $todo['id'] ?>" class="btn btn-success btn-sm me-1"><?=$languageService->get('mark_done')?></a>
                    <?php endif; ?>
                    <a href="admincenter.php?site=admin_todo&edit_id=<?= $todo['id'] ?>" class="btn btn-warning btn-sm me-1"><?=$languageService->get('edit')?></a>
                    <a href="admincenter.php?site=admin_todo&del_id=<?= $todo['id'] ?>" onclick="return confirm('<?=$languageService->get('confirm_delete')?>')" class="btn btn-danger btn-sm"><?=$languageService->get('delete')?></a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
