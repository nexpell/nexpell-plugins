<?php
use webspell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('userlist');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('userlist');

if (isset($_POST[ 'submit' ])) {
    $users_list = $_POST[ "users_list" ];
    $users_online = $_POST[ "users_online" ];
    $CAPCLASS = new \webspell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST[ 'captcha_hash' ])) {
        safe_query("UPDATE plugins_userlist SET users_list='" . $_POST[ 'users_list' ] . "', users_online='" . $_POST[ 'users_online' ] . "'");
        
        
        redirect("admincenter.php?site=admin_reg_userlist", "", 0);
    } else {
        redirect("admincenter.php?site=admin_reg_userlist", $languageService->get('transaction_invalid'), 3);
    }
} else {
    $ergebnis = safe_query("SELECT * FROM plugins_userlist");
    $ds = mysqli_fetch_array($ergebnis);
    $CAPCLASS = new \webspell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();


    echo '<div class="card">
            <div class="card-header"> <i class="bi bi-person-fills"></i> ' . $languageService->get('registered_users') . '
            </div>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item active" aria-current="page"><a href="admincenter.php?site=admin_reg_userlist" class="white">' . $languageService->get('registered_users') . '</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
              </ol>
            </nav>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <form class="form-horizontal" method="post" id="post" name="post" action="admincenter.php?site=admin_reg_userlist" onsubmit="return chkFormular();">
                        <div class="mb-3 row bt">
                            <div class="col-md-4">
                                ' . $languageService->get('max_registered_users') . ':
                            </div>
                            <div class="col-md-2">
                                <span class="pull text-muted small"><em data-toggle="tooltip" title="' . $languageService->get('tooltip_1') . '"><input class="form-control" type="text" name="users_list" value="' . $ds['users_list'] . '" size="35"></em></span>
                            </div>
                        </div>
                        <div class="mb-3 row bt">
                            <div class="col-md-4">'.$languageService->get('max_users_online') .'</label>
                            </div>
                            <div class="col-md-2">
                                <span class="pull text-muted small"><em data-toggle="tooltip" data-html="true" title="'.$languageService->get('tooltip_1') .'"><input class="form-control" type="text" name="users_online" value="'.$ds['users_online'] .'" size="35"></em></span>
                            </div>
                        </div> 
                        <input type="hidden" name="captcha_hash" value="' . $hash . '"> 
                        <button class="btn btn-warning" type="submit" name="submit"  />' . $languageService->get('update') . '</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';
}
?>

