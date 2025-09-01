<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('userlist');

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('userlist');

$result = $_database->query("SELECT * FROM plugins_userlist_settings LIMIT 1");
if ($result && $result->num_rows > 0) {
    $config = $result->fetch_assoc();
} else {
    // Defaults
    $config = [
        'users_per_page' => 20,
        'users_widget_count' => 5,
        'widget_show_online' => 1,
        'widget_sort' => 'lastlogin',
        'show_avatars' => 1,
        'show_roles' => 1,
        'show_website' => 1,
        'show_lastlogin' => 1,
        'show_online_status' => 1,
        'show_registerdate' => 1,
        'default_sort' => 'username',
        'default_order' => 'ASC',
        'enable_search' => 1,
        'enable_role_filter' => 1,
        'default_role' => '',
        'pagination_style' => 'full',
        'table_style' => 'striped',
        'avatar_size' => 'small',
        'highlight_online_users' => 1,
    ];

    // Defaults in DB speichern
    $columns = implode(", ", array_keys($config));
    $values  = implode(", ", array_map(fn($v) => "'".$v."'", $config));
    $_database->query("INSERT INTO plugins_userlist_settings ($columns) VALUES ($values)");
}

// 🔐 Captcha vorbereiten
$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

// 💾 Speichern, falls abgeschickt
if (isset($_POST['save_settings'])) {
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

        $fields = [
            'users_per_page', 'users_widget_count', 'widget_show_online', 'widget_sort',
            'show_avatars', 'show_roles', 'show_website', 'show_lastlogin', 'show_online_status',
            'show_registerdate', 'default_sort', 'default_order', 'enable_search',
            'enable_role_filter', 'default_role', 'pagination_style', 'table_style',
            'avatar_size', 'highlight_online_users',
        ];

        $updates = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $val = $_POST[$field];
                // Checkboxen auf 0/1 casten
                if (in_array($field, ['widget_show_online','show_avatars','show_roles','show_website','show_lastlogin','show_online_status','show_registerdate','enable_search','enable_role_filter','highlight_online_users'])) {
                    $val = $val ? 1 : 0;
                } elseif (is_numeric($val)) {
                    $val = intval($val);
                } else {
                    $val = mysqli_real_escape_string($_database, $val);
                }
            } else {
                $val = 0; // für nicht gesetzte Checkboxen
            }
            $updates[] = "$field = '$val'";
        }

        $sql = "UPDATE plugins_userlist_settings SET ".implode(", ", $updates)." LIMIT 1";
        $_database->query($sql);

        redirect("admincenter.php?site=admin_userlist", "", 2);
        echo '<div class="alert alert-success">' . $languageService->get('settings_saved_success') . '</div>';
    } else {
        redirect("admincenter.php?site=admin_userlist", "", 3);
        echo '<div class="alert alert-danger">' . $languageService->get('transaction_invalid') . '</div>';
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-journal-text"></i> <?= $languageService->get('userlist_settings_title') ?>
    </div>
    <nav class="breadcrumb bg-light p-2">
        <a class="breadcrumb-item" href="admincenter.php?site=admin_userlist"><?= $languageService->get('userlist_settings_title') ?></a>
        <span class="breadcrumb-item active"><?= $languageService->get('userlist_settings_edit') ?></span>
    </nav>
    <div class="card-body">
        <div class="container">

            <form method="post" action="admincenter.php?site=admin_userlist" class="needs-validation" novalidate>

                <input type="hidden" name="captcha_hash" value="<?= $hash ?>">

                <div class="card mb-4">
                    <div class="card-header"><?= $languageService->get('general_settings_header') ?></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label"><?= $languageService->get('users_per_page_label') ?></label>
                                <input type="number" name="users_per_page" class="form-control" value="<?= $config['users_per_page'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= $languageService->get('users_widget_count_label') ?></label>
                                <input type="number" name="users_widget_count" class="form-control" value="<?= $config['users_widget_count'] ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-center">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="widget_show_online" value="1" <?= $config['widget_show_online'] ? 'checked' : '' ?>>
                                    <label class="form-check-label"><?= $languageService->get('widget_show_online_label') ?></label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= $languageService->get('widget_sort_label') ?></label>
                                <select name="widget_sort" class="form-select">
                                    <option value="lastlogin" <?= $config['widget_sort'] == 'lastlogin' ? 'selected' : '' ?>><?= $languageService->get('sort_last_login_option') ?></option>
                                    <option value="registerdate" <?= $config['widget_sort'] == 'registerdate' ? 'selected' : '' ?>><?= $languageService->get('sort_registered_option') ?></option>
                                    <option value="username" <?= $config['widget_sort'] == 'username' ? 'selected' : '' ?>><?= $languageService->get('sort_username_option') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><?= $languageService->get('display_settings_header') ?></div>
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_avatars" value="1" <?= $config['show_avatars'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('show_avatars_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_roles" value="1" <?= $config['show_roles'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('show_roles_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_website" value="1" <?= $config['show_website'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('show_website_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_lastlogin" value="1" <?= $config['show_lastlogin'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('show_last_login_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_online_status" value="1" <?= $config['show_online_status'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('show_online_status_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_registerdate" value="1" <?= $config['show_registerdate'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('show_register_date_label') ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><?= $languageService->get('sort_filter_header') ?></div>
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><?= $languageService->get('default_sort_label') ?></label>
                            <select name="default_sort" class="form-select">
                                <option value="username" <?= $config['default_sort'] == 'username' ? 'selected' : '' ?>><?= $languageService->get('sort_username_option') ?></option>
                                <option value="registerdate" <?= $config['default_sort'] == 'registerdate' ? 'selected' : '' ?>><?= $languageService->get('sort_registered_option') ?></option>
                                <option value="lastlogin" <?= $config['default_sort'] == 'lastlogin' ? 'selected' : '' ?>><?= $languageService->get('sort_last_login_option') ?></option>
                                <option value="is_online" <?= $config['default_sort'] == 'is_online' ? 'selected' : '' ?>><?= $languageService->get('sort_online_status_option') ?></option>
                                <option value="website" <?= $config['default_sort'] == 'website' ? 'selected' : '' ?>><?= $languageService->get('sort_website_option') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= $languageService->get('default_order_label') ?></label>
                            <select name="default_order" class="form-select">
                                <option value="ASC" <?= $config['default_order'] == 'ASC' ? 'selected' : '' ?>><?= $languageService->get('order_asc_option') ?></option>
                                <option value="DESC" <?= $config['default_order'] == 'DESC' ? 'selected' : '' ?>><?= $languageService->get('order_desc_option') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="enable_search" value="1" <?= $config['enable_search'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('enable_search_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="enable_role_filter" value="1" <?= $config['enable_role_filter'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('enable_role_filter_label') ?></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= $languageService->get('default_role_label') ?></label>
                            <select name="default_role" class="form-select">
                                <option value=""><?= $languageService->get('no_role_option') ?></option>
                                <?php
                                $rolesResult = safe_query("SELECT role_name FROM user_roles ORDER BY role_name ASC");
                                while ($r = mysqli_fetch_assoc($rolesResult)) :
                                ?>
                                    <option value="<?= htmlspecialchars($r['role_name']) ?>" <?= $config['default_role'] == $r['role_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['role_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><?= $languageService->get('pagination_design_header') ?></div>
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><?= $languageService->get('pagination_style_label') ?></label>
                            <select name="pagination_style" class="form-select">
                                <option value="simple" <?= $config['pagination_style'] == 'simple' ? 'selected' : '' ?>><?= $languageService->get('pagination_simple_option') ?></option>
                                <option value="full" <?= $config['pagination_style'] == 'full' ? 'selected' : '' ?>><?= $languageService->get('pagination_full_option') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= $languageService->get('table_style_label') ?></label>
                            <select name="table_style" class="form-select">
                                <option value="striped" <?= $config['table_style'] == 'striped' ? 'selected' : '' ?>><?= $languageService->get('table_striped_option') ?></option>
                                <option value="bordered" <?= $config['table_style'] == 'bordered' ? 'selected' : '' ?>><?= $languageService->get('table_bordered_option') ?></option>
                                <option value="compact" <?= $config['table_style'] == 'compact' ? 'selected' : '' ?>><?= $languageService->get('table_compact_option') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= $languageService->get('avatar_size_label') ?></label>
                            <select name="avatar_size" class="form-select">
                                <option value="small" <?= $config['avatar_size'] == 'small' ? 'selected' : '' ?>><?= $languageService->get('avatar_small_option') ?></option>
                                <option value="medium" <?= $config['avatar_size'] == 'medium' ? 'selected' : '' ?>><?= $languageService->get('avatar_medium_option') ?></option>
                                <option value="large" <?= $config['avatar_size'] == 'large' ? 'selected' : '' ?>><?= $languageService->get('avatar_large_option') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="highlight_online_users" value="1" <?= $config['highlight_online_users'] ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $languageService->get('highlight_online_users_label') ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_settings" class="btn btn-primary btn-lg"><?= $languageService->get('save_button') ?></button>
            </form>
        </div>
    </div>
</div>