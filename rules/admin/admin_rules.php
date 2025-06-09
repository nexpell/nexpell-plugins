<?php

use webspell\LanguageService;
use webspell\AccessControl;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['language'] = $_SESSION['language'] ?? 'de';

global $languageService;
$languageService = new LanguageService($_database);
$languageService->readPluginModule('rules');

AccessControl::checkAdminAccess('rules');

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $_database->query("DELETE FROM plugins_rules WHERE id = $id");
    header("Location: admincenter.php?site=admin_rules");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rule'])) {
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title       = trim($_POST['title'] ?? '');
    $text        = $_POST['message'];
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $sort_order  = intval($_POST['sort_order'] ?? 0);
    $userID      = (int)$_SESSION['userID'];

    if (empty($title) || empty($text)) {
        $error = $languageService->get('rules_error_required');
    } else {
        

        if ($id > 0) {
            safe_query("
                UPDATE plugins_rules 
                SET title = '" . $title . "', 
                    text = '" . $text . "', 
                    is_active = " . $is_active . ", 
                    sort_order = " . $sort_order . " 
                WHERE id = " . $id
            );
        } else {
            safe_query("
                INSERT INTO plugins_rules (title, text, userID, sort_order, is_active) 
                VALUES ('" . $title . "', '" . $text . "', " . $userID . ", " . $sort_order . ", " . $is_active . ")
            ");
        }

        header("Location: admincenter.php?site=admin_rules");
        exit;
    }
}


if ($action === 'add' || $action === 'edit') {
    $editrule = null;
    if ($action === 'edit' && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $res = $_database->query("SELECT * FROM plugins_rules WHERE id = $id");
        $editrule = $res->fetch_assoc();
    }
    ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-card-text"></i> <?= $languageService->get('rules_' . ($editrule ? 'edit' : 'add')) ?></div>
        <a href="admincenter.php?site=admin_rules" class="btn btn-secondary btn-sm"><?= $languageService->get('rules_cancel') ?></a>
    </div>
    <div class="card-body">
        <div class="container py-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="admincenter.php?site=admin_rules&action=<?= $action ?><?= $editrule ? '&edit=' . (int)$editrule['id'] : '' ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editrule['id'] ?? '') ?>">

            <div class="mb-3">
                <label for="title" class="form-label"><?= $languageService->get('rules_title') ?> *</label>
                <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($editrule['title'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="text" class="form-label"><?= $languageService->get('rules_text') ?> *</label>
                <textarea class="ckeditor" name="message" rows="10"><?= $editrule['text'] ?></textarea>
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label"><?= $languageService->get('rules_sort_order') ?></label>
                <input type="number" class="form-control" name="sort_order" id="sort_order" value="<?= (int)($editrule['sort_order'] ?? 0) ?>">
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $editrule['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active"><?= $languageService->get('rules_active') ?></label>
            </div>

            <button type="submit" name="save_rule" class="btn btn-primary"><?= $languageService->get('rules_' . ($editrule ? 'save' : 'add')) ?></button>
        </form>
    </div>
    </div>
</div>

<?php } else {
    $resrules = $_database->query("SELECT * FROM plugins_rules ORDER BY sort_order ASC, date DESC");
    ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-card-text"></i> <?= $languageService->get('rules_admin_title') ?></div>
        <a href="admincenter.php?site=admin_rules&action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> <?= $languageService->get('rules_add') ?></a>
    </div>
    <div class="card-body p-0">
        <div class="container py-5">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th><?= $languageService->get('rules_title') ?></th>
                        <th><?= $languageService->get('rules_date') ?></th>
                        <th><?= $languageService->get('rules_active') ?></th>
                        <th><?= $languageService->get('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($resrules->num_rows > 0): ?>
                    <?php while ($rule = $resrules->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($rule['title']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($rule['date'])) ?></td>
                        <td>
                            <span class="badge <?= $rule['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $languageService->get($rule['is_active'] ? 'yes' : 'no') ?>
                            </span>
                        </td>
                        <td>
                            <a href="admincenter.php?site=admin_rules&action=edit&edit=<?= $rule['id'] ?>" class="btn btn-sm btn-warning"><?= $languageService->get('rules_edit') ?></a>
                            <form method="post" style="display:inline-block;" onsubmit="return confirm('<?= $languageService->get('rules_delete_confirm') ?>');">
                                <input type="hidden" name="delete_id" value="<?= $rule['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><?= $languageService->get('delete') ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center"><?= $languageService->get('rules_no_entries') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php } ?>
