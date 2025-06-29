<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('todo');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('title'),
        'subtitle' => 'Todo'
    ];
    
    echo $tpl->loadTemplate("todo", "head", $data_array, 'plugin');


// Alle Todos inkl. Benutzer laden
$result = $_database->query("SELECT plugins_todo.*, users.username FROM plugins_todo LEFT JOIN users ON plugins_todo.userID = users.userID ORDER BY created_at DESC");

$todos = [];
while ($row = $result->fetch_assoc()) {
    $todos[] = $row;
}


// Sortiere todos so, dass alle mit 100% Fortschritt ans Ende wandern
usort($todos, function($a, $b) {
    if ((int)$a['progress'] === 100 && (int)$b['progress'] !== 100) {
        return 1;
    }
    if ((int)$a['progress'] !== 100 && (int)$b['progress'] === 100) {
        return -1;
    }
    return (int)$a['progress'] <=> (int)$b['progress'];
});
?>

<div class="row row-cols-1 row-cols-md-2 g-4 mt-4">
<?php foreach ($todos as $todo): ?>
    <?php
        $priorityColor = match($todo['priority']) {
            'high' => 'danger',
            'low' => 'success',
            default => 'warning'
        };
    ?>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-2"><?= htmlspecialchars($todo['task']) ?></h5>
                <p class="card-text small text-muted mb-2"><?= nl2br(htmlspecialchars($todo['description'])) ?></p>

                <div class="row">
                    <div class="col-6">
                        <ul class="list-unstyled small">
                            <li><strong>Benutzer:</strong> <?= htmlspecialchars($todo['username'] ?? 'Unbekannt') ?></li>
                            <li><strong>Priorität:</strong> <span class="badge bg-<?= $priorityColor ?>"><?= htmlspecialchars($todo['priority']) ?></span></li>
                            <li><strong>Fällig:</strong> <?= !empty($todo['due_date']) ? date("d.m.Y", strtotime($todo['due_date'])) : '<span class="text-muted fst-italic">Nicht gesetzt</span>' ?>
</li>
                            <li><strong>Erledigt:</strong>
                                <?php if ($todo['done']): ?>
                                    <span class="badge bg-success">Ja</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Nein</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <ul class="list-unstyled small">
                            <li><strong>Fortschritt:</strong>
                                <?php
                                $progress = (int)$todo['progress']; // Wert 0-100
                                if ($progress >= 80) {
                                    $color = 'bg-success'; // grün
                                } elseif ($progress >= 50) {
                                    $color = 'bg-warning'; // gelb
                                } else {
                                    $color = 'bg-danger';  // rot
                                }
                                ?>

                                <div class="progress my-1" style="height: 6px;">
                                    <div class="progress-bar <?= $color ?>" style="width: <?= $progress ?>%;"></div>
                                </div>
                                <?= $todo['progress'] ?>%
                            </li>
                            <li><strong>Erstellt:</strong> <?= date("d.m.Y H:i", strtotime($todo['created_at'])) ?></li>
                            <li><strong>Bearbeitet:</strong> <?= date("d.m.Y H:i", strtotime($todo['updated_at'])) ?></li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>



<?
/*
<!-- Beispiel: nur 6 Spalten -->
<thead>
<tr>
    <th>ID</th>
    <th>Aufgabe</th>
    <th>Prio</th>
    <th>Fortschritt</th>
    <th>Erledigt</th>
    <th>Fällig</th>
</tr>
</thead>
<tbody>
<?php foreach ($todos as $todo): ?>
<tr title="Erstellt: <?= date("d.m.Y H:i", strtotime($todo['created_at'])) ?>&#10;Bearbeitet: <?= date("d.m.Y H:i", strtotime($todo['updated_at'])) ?>&#10;Beschreibung: <?= htmlspecialchars($todo['description']) ?>">
    <td><?= $todo['id'] ?></td>
    <td><strong><?= htmlspecialchars($todo['task']) ?></strong></td>
    <td><span class="badge bg-<?= $priorityColor ?>"><?= htmlspecialchars($todo['priority']) ?></span></td>
    <td>
        <div class="progress" style="height: 6px;">
            <div class="progress-bar" role="progressbar" style="width: <?= $todo['progress'] ?>%;"></div>
        </div>
        <small><?= $todo['progress'] ?>%</small>
    </td>
    <td><?= $todo['done'] ? '✅' : '❌' ?></td>
    <td><?= $todo['due_date'] ? date("d.m.Y", strtotime($todo['due_date'])) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody>


// Todo Plugin für nexpell

// DB-Verbindung
global $_database;

// Todo hinzufügen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task']) && trim($_POST['task']) !== '') {
    $task = $_database->real_escape_string(trim($_POST['task']));
    $userID = (int)($_SESSION['userID'] ?? 0); // aktueller User

    if ($userID > 0) {
        $_database->query("INSERT INTO plugins_todo (userID, task) VALUES ($userID, '$task')");
    }

    header("Location: ?site=todo");
    exit;
}

// Todo als erledigt markieren (GET)
if (isset($_GET['complete'])) {
    $todoID = (int)$_GET['complete'];
    $userID = (int)($_SESSION['userID'] ?? 0);

    if ($userID > 0) {
        $_database->query("UPDATE plugins_todo SET done = 1 WHERE todoID = $todoID AND userID = $userID");
    }

    header("Location: ?site=todo");
    exit;
}

// Todos für aktuellen User laden
$userID = (int)($_SESSION['userID'] ?? 0);
$todos = [];

if ($userID > 0) {
    $result = $_database->query("SELECT * FROM plugins_todo WHERE userID = $userID ORDER BY created_at DESC");
    while ($row = $result->fetch_assoc()) {
        $todos[] = $row;
    }
} else {
    echo '<p>Bitte melde dich an, um deine Todos zu sehen.</p>';
    return;
}

// Template-Ausgabe vorbereiten
?>

<h2>Deine Todo-Liste</h2>

<form method="post" action="?site=todo" class="mb-3">
    <input type="text" name="task" placeholder="Neue Aufgabe" required class="form-control" />
    <button type="submit" class="btn btn-primary mt-2">Hinzufügen</button>
</form>

<ul class="list-group">
    <?php foreach ($todos as $todo): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center <?= $todo['done'] ? 'list-group-item-success' : '' ?>">
            <?= htmlspecialchars($todo['task']) ?>
            <?php if (!$todo['done']): ?>
                <a href="" class="btn btn-sm btn-success">Erledigt</a>
            <?php else: ?>
                <span class="badge bg-success">Erledigt</span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
*/