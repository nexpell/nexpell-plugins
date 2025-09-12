<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database, $languageService, $tpl;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('userlist');

// Head style
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class' => $class,
    'title' => $languageService->get('registered_users'),
    'subtitle' => 'Userlist'
];
echo $tpl->loadTemplate("userlist", "head", $data_array, "plugin");


// === SETTINGS LADEN ===
$settings = mysqli_fetch_assoc(safe_query("SELECT * FROM plugins_userlist_settings WHERE id=1"));
if (!$settings) {
    safe_query("INSERT INTO plugins_userlist_settings 
        (id, users_per_page, default_sort, default_order, default_role) 
        VALUES (1, 10, 'username', 'ASC', '')");
    $settings = [
        'users_per_page'=>10,
        'default_sort'=>'username',
        'default_order'=>'ASC',
        'default_role'=>''
    ];
}

// === GET Parameter oder Fallback zu Settings ===
$perPage    = isset($_GET['perPage']) ? max(1, intval($_GET['perPage'])) : intval($settings['users_per_page']);
$page       = max(1, intval($_GET['page'] ?? 1));
$search     = trim($_GET['search'] ?? '');  // default_search existiert nicht mehr, kann leer bleiben
$roleFilter = $_GET['role'] ?? $settings['default_role'];
$sort       = $_GET['sort'] ?? $settings['default_sort'];
$order      = strtoupper($_GET['order'] ?? $settings['default_order']);
$offset     = ($page - 1) * $perPage;

// === SETTINGS SPEICHERN (immer bei Aufruf) ===
safe_query("
    UPDATE plugins_userlist_settings 
    SET users_per_page='".intval($perPage)."',
        default_sort='".mysqli_real_escape_string($_database,$sort)."',
        default_order='".mysqli_real_escape_string($_database,$order)."',
        default_role='".mysqli_real_escape_string($_database,$roleFilter)."'
    WHERE id=1
");


// Rollen Dropdown dynamisch
$rolesResult = safe_query("SELECT role_name FROM user_roles ORDER BY role_name ASC");
$roles = [];
while($r = mysqli_fetch_assoc($rolesResult)) {
    $roles[] = $r['role_name'];
}

// WHERE-Bedingungen
$where = [];
$params = [];

if($search !== '') {
    $where[] = "u.username LIKE ?";
    $params[] = "%$search%";
}
if($roleFilter !== '') {
    $where[] = "u.userID IN (
        SELECT ura.userID 
        FROM user_role_assignments ura
        JOIN user_roles r ON ura.roleID = r.roleID
        WHERE r.role_name = ?
    )";
    $params[] = $roleFilter;
}

$whereSQL = $where ? "WHERE ".implode(" AND ", $where) : "";

// SQL Abfrage inkl. Rollen + Webseite
$sqlOrder = ($sort === 'website') ? "username ASC" : "$sort $order";

$sql = "
SELECT 
    u.userID,
    u.username,
    u.registerdate,
    u.lastlogin,
    u.is_online,
    GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles,
    (SELECT website FROM user_socials WHERE userID = u.userID LIMIT 1) AS website
FROM users u
LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
LEFT JOIN user_roles r ON ura.roleID = r.roleID
$whereSQL
GROUP BY u.userID
ORDER BY $sqlOrder
LIMIT ?, ?
";

$stmt = $_database->prepare($sql);
if (!$stmt) die("SQL Error: " . $_database->error);

// Parameter binden
$allParams = array_merge($params, [$offset, $perPage]);
$bindTypes = str_repeat('s', count($params)) . 'ii';
$bindRefs = [];
foreach ($allParams as $key => $val) $bindRefs[$key] = &$allParams[$key];
call_user_func_array([$stmt, 'bind_param'], array_merge([$bindTypes], $bindRefs));

$stmt->execute();
$result = $stmt->get_result();

// Daten in Array sammeln
$rows = [];
while($row = $result->fetch_assoc()) $rows[] = $row;

// Sortieren nach Webseite in PHP, falls ausgewählt
if($sort === 'website') {
    usort($rows, function($a, $b) use ($order) {
        $cmp = strcmp($a['website'] ?? '', $b['website'] ?? '');
        return $order === 'DESC' ? -$cmp : $cmp;
    });
}

// Gesamtanzahl für Pagination
$countSQL = "SELECT COUNT(*) as total FROM users u $whereSQL";
$countStmt = $_database->prepare($countSQL);
if (!$countStmt) die("SQL Error: " . $_database->error);
if(count($params) > 0) {
    $bindRefs = [];
    for($i=0; $i<count($params); $i++) $bindRefs[$i] = &$params[$i];
    call_user_func_array([$countStmt, 'bind_param'], array_merge([str_repeat('s', count($params))], $bindRefs));
}
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $perPage);

// Gesamtanzahl aller User
$alle = safe_query("SELECT userID FROM users");
$gesamt = mysqli_num_rows($alle);

$sqlCount = "
SELECT COUNT(DISTINCT u.userID) as cnt
FROM users u
LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
LEFT JOIN user_roles r ON ura.roleID = r.roleID
$whereSQL
";
$stmtCount = $_database->prepare($sqlCount);
if ($params) {
    $bindTypes = str_repeat('s', count($params));
    $bindRefs  = [];
    foreach ($params as $k => $v) $bindRefs[$k] = &$params[$k];
    call_user_func_array([$stmtCount, 'bind_param'], array_merge([$bindTypes], $bindRefs));
}
$stmtCount->execute();
$resCnt = $stmtCount->get_result();
$gefiltert = ($row = $resCnt->fetch_assoc()) ? (int)$row['cnt'] : 0;
?>

<div class="card">
  <div class="card-body">
    <div class="container my-4">
      <h2><?= $languageService->get('user_list') ?></h2>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <form method="get" class="userlist-filter d-flex gap-2 mb-0">
                <input type="hidden" name="site" value="userlist">

                <?php if($settings['enable_search']): ?>
                    <input type="text" name="search" placeholder="<?= $languageService->get('search_placeholder') ?>" value="<?=htmlspecialchars($search)?>" class="form-control">
                <?php endif; ?>

                <?php if($settings['enable_role_filter']): ?>
                    <select name="role" class="form-select">
                        <option value=""><?= $languageService->get('all_roles') ?></option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?=htmlspecialchars($r)?>" <?= $roleFilter==$r?'selected':'' ?>><?=htmlspecialchars($r)?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select name="sort" class="form-select">
                    <option value="username" <?= $sort=='username'?'selected':'' ?>><?= $languageService->get('username') ?></option>
                    <option value="registerdate" <?= $sort=='registerdate'?'selected':'' ?>><?= $languageService->get('registered') ?></option>
                    <option value="lastlogin" <?= $sort=='lastlogin'?'selected':'' ?>><?= $languageService->get('last_login') ?></option>
                    <option value="is_online" <?= $sort=='is_online'?'selected':'' ?>><?= $languageService->get('online_status') ?></option>
                    <option value="website" <?= $sort=='website'?'selected':'' ?>><?= $languageService->get('website') ?></option>
                </select>

                <select name="order" class="form-select">
                    <option value="ASC" <?= $order=='ASC'?'selected':'' ?>><?= $languageService->get('ascending') ?></option>
                    <option value="DESC" <?= $order=='DESC'?'selected':'' ?>><?= $languageService->get('descending') ?></option>
                </select>

                <button type="submit" class="btn btn-primary"><?= $languageService->get('filter') ?></button>
            </form>



            <!-- Badges rechts -->
            <div class="d-flex gap-2">
                <span class="badge bg-secondary">
                    <?= $languageService->get('total') ?>: <?= $gesamt ?>
                </span>
                <span class="badge bg-primary">
                    <?= $languageService->get('found') ?>: <?= $gefiltert ?>
                </span>
            </div>
        </div>



        <table class="table table-<?= $settings['table_style'] ?> userlist-table">
            <thead>
                <tr>
                    <?php if($settings['show_avatars']): ?><th><?= $languageService->get('avatar') ?></th><?php endif; ?>
                    <th><?= $languageService->get('username') ?></th>
                    <?php if($settings['show_roles']): ?><th><?= $languageService->get('role') ?></th><?php endif; ?>
                    <?php if($settings['show_website']): ?><th><?= $languageService->get('homepage') ?></th><?php endif; ?>
                    <?php if($settings['show_registerdate']): ?><th><?= $languageService->get('registered') ?></th><?php endif; ?>
                    <?php if($settings['show_lastlogin']): ?><th><?= $languageService->get('last_login') ?></th><?php endif; ?>
                    <?php if($settings['show_online_status']): ?><th><?= $languageService->get('status') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $row):
                    $avatarFile = getavatar($row['userID']);
                    $avatar = $avatarFile 
                        ? '<img src="'.htmlspecialchars($avatarFile).'" width="'.($settings['avatar_size']=='small'?50:($settings['avatar_size']=='medium'?80:100)).'" height="'.($settings['avatar_size']=='small'?50:($settings['avatar_size']=='medium'?80:100)).'">' 
                        : '<img src="default-avatar.png" width="'.($settings['avatar_size']=='small'?50:($settings['avatar_size']=='medium'?80:100)).'" height="'.($settings['avatar_size']=='small'?50:($settings['avatar_size']=='medium'?80:100)).'">';
                    
                    $status = $row['is_online'] 
                        ? '<span style="color:green">' . $languageService->get('online') . '</span>' 
                        : '<span style="color:red">' . $languageService->get('offline') . '</span>';

                    $rolesText = $row['roles'] ? htmlspecialchars($row['roles']) : $languageService->get('user');
                    $profileUrl = 'index.php?site=profile&id=' . intval($row['userID']); 
                    $usernameLink = '<a href="' . $profileUrl . '">' . htmlspecialchars($row['username']) . '</a>';

                    $websiteUrl = $row['website'] ?? '';
                    $homepage = !empty($websiteUrl) 
                        ? '<a href="'.(str_starts_with($websiteUrl,'http://')||str_starts_with($websiteUrl,'https://')?'':'http://').htmlspecialchars($websiteUrl).'" target="_blank" rel="nofollow">'.$languageService->get('homepage').'</a>' 
                        : '<s>'.$languageService->get('homepage').'</s>';
                ?>
                <tr <?= $settings['highlight_online_users'] && $row['is_online'] ? 'style="font-weight:bold;"' : '' ?>>
                    <?php if($settings['show_avatars']): ?><td><?= $avatar ?></td><?php endif; ?>
                    <td><?= $usernameLink ?></td>
                    <?php if($settings['show_roles']): ?><td><?= $rolesText ?></td><?php endif; ?>
                    <?php if($settings['show_website']): ?><td><?= $homepage ?></td><?php endif; ?>
                    <?php if($settings['show_registerdate']): ?><td><?= date("d.m.Y", strtotime($row['registerdate'])) ?></td><?php endif; ?>
                    <?php if($settings['show_lastlogin']): ?><td><?= date("d.m.Y H:i", strtotime($row['lastlogin'])) ?></td><?php endif; ?>
                    <?php if($settings['show_online_status']): ?><td><?= $status ?></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


    </div>
  </div>
</div>

<div class="userlist-pagination mt-3 d-flex flex-wrap gap-2">
<?php if($settings['pagination_style'] === 'simple'): ?>
    <?php if ($page > 1): ?>
        <a href="index.php?site=userlist&page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn btn-secondary">← <?= $languageService->get('previous_page') ?></a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
        <a href="index.php?site=userlist&page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn btn-secondary"><?= $languageService->get('next_page') ?> →</a>
    <?php endif; ?>

<?php else: // full pagination ?>
<div class="userlist-pagination-full d-flex flex-wrap">
    <!-- Previous Button -->
    <a href="<?= $page > 1 
        ? "index.php?site=userlist&page=".($page-1)."&search=".urlencode($search)."&role=".urlencode($roleFilter)."&sort=$sort&order=$order" 
        : '#' ?>" 
       class="btn btn-secondary <?= $page <= 1 ? 'disabled' : '' ?>">
        ← <?= $languageService->get('previous_page') ?>
    </a>

    <!-- Seitenzahlen -->
    <?php for($p = 1; $p <= $totalPages; $p++): ?>
        <a href="index.php?site=userlist&page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&sort=<?= $sort ?>&order=<?= $order ?>" 
           class="btn <?= $p == $page ? 'btn-primary' : 'btn-secondary' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <!-- Next Button -->
    <a href="<?= $page < $totalPages 
        ? "index.php?site=userlist&page=".($page+1)."&search=".urlencode($search)."&role=".urlencode($roleFilter)."&sort=$sort&order=$order" 
        : '#' ?>" 
       class="btn btn-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <?= $languageService->get('next_page') ?> →
    </a>
</div>
<?php endif; ?>



</div>