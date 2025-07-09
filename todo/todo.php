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

<?php if (empty($todos)): ?>
    <div class="alert alert-info mt-4" role="alert">
        Es sind derzeit keine Todos vorhanden.
    </div>
<?php else: ?>
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
                        <p class="card-text small text-muted mb-2"><?= $todo['description'] ?></p>

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
                                        $progress = (int)$todo['progress'];
                                        if ($progress >= 80) {
                                            $color = 'bg-success';
                                        } elseif ($progress >= 50) {
                                            $color = 'bg-warning';
                                        } else {
                                            $color = 'bg-danger';
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
<?php endif; ?>



