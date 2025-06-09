<?php
use webspell\LanguageService;

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
$languageService->readPluginModule('clan_rules');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('clan_rules');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

// Initialisiere Captcha-Klasse
$CAPCLASS = new \webspell\Captcha;

// Speichern: Neue Regel
if (isset($_POST["save"])) {

    $title = $_POST["title"];
    $text = $_POST["message"];
    $displayed = isset($_POST["displayed"]) ? 1 : 0;
    $date = date("Y-m-d H:i:s", time());

    if ($CAPCLASS->checkCaptcha(0, $_POST["captcha_hash"])) {

        // Eintrag erstellen
        safe_query("INSERT INTO plugins_clan_rules (title, text, date, userID, displayed, sort)
                    VALUES ('" . $title . "', '" . $text . "', '" . $date . "', '" . $userID . "', '$displayed', '1')");

/////////////////////////////////////////////////////////////
 /*       // Neue ID holen
        $new_ruleID = $_database->insert_id;

        // Logging-Daten vorbereiten
        $new_data = json_encode([
            'title' => $title,
            'text' => $text,
            'displayed' => $displayed
        ]);

        // Admin-Log schreiben
        write_admin_log(
            $userID,
            'Erstellen',
            'Clan Rules',
            $new_ruleID,
            null,
            $new_data,
            $_SERVER['REMOTE_ADDR'],
            time(),
            'plugins_clan_rules'
        );*/
/////////////////////////////////////////////////////////////

        redirect("admincenter.php?site=admin_clan_rules", "", 0);
    } else {
        echo $languageService->get('transaction_invalid');
    }
}



// Speichern: Bearbeitung
if (isset($_POST["saveedit"]) && $CAPCLASS->checkCaptcha(0, $_POST["captcha_hash"])) {

    $id = (int)$_POST["id"];
    $title = $_POST["title"] ?? '';
    $text = $_POST["message"] ?? '';
    $displayed = isset($_POST["displayed"]) ? 1 : 0;
    $date = date("Y-m-d H:i:s");  // Für DATETIME-Spalte
    $userID = $userID;

    // Speichern mit safe_query (nicht ändern)
    safe_query(
        "UPDATE plugins_clan_rules 
         SET title='" . $title . "',
             text='" . $text . "',
             date='" . $date . "',
             userID='" . (int)$userID . "',
             displayed='" . (int)$displayed . "' 
         WHERE id='" . (int)$id . "'"
    );

    // Logging (separat, korrekt nach safe_query)
    /*AdminLogger::updateWithLog(
        'plugins_clan_rules',
        'id',
        $id,
        ['title' => $title, 'text' => $text],
        'Bearbeiten',
        'Clanrules',
        $userID
    );*/

    redirect("admincenter.php?site=admin_clan_rules", "", 3);
}
// Sortierung
elseif (isset($_POST['sortieren'])) {

    if ($CAPCLASS->checkCaptcha(0, $_POST["captcha_hash"])) {

        foreach ($_POST['sort'] as $sortstring) {
            [$id, $sortval] = explode("-", $sortstring);
            safe_query("UPDATE plugins_clan_rules SET sort='$sortval' WHERE id='$id'");
        }

        redirect("admincenter.php?site=admin_clan_rules", "", 0);
    } else {
        echo $languageService->get('transaction_invalid');
    }
}

// Löschen
elseif (isset($_GET["delete"], $_GET["captcha_hash"], $_GET["id"])) {

    if ($CAPCLASS->checkCaptcha(0, $_GET["captcha_hash"])) {
        $id = (int)$_GET["id"];

        if (safe_query("DELETE FROM plugins_clan_rules WHERE id='$id'")) {
            redirect("admincenter.php?site=admin_clan_rules", "", 0);
        } else {
            echo "Fehler beim Löschen!";
        }
    } else {
        echo $languageService->get('transaction_invalid');
    }
} elseif (isset($_POST[ 'clan_rules_settings_save' ])) {  

   
    $CAPCLASS = new \webspell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST[ 'captcha_hash' ])) {
        safe_query(
            "UPDATE
                plugins_clan_rules_settings
            SET
                
                clan_rules='" . $_POST[ 'clan_rules' ] . "' "
        );
        
        redirect("admincenter.php?site=admin_clan_rules&action=admin_clan_rules_settings", "", 0);
    } else {
        redirect("admincenter.php?site=admin_clan_rules&action=admin_clan_rules_settings", $languageService->get('transaction_invalid'), 3);
    }
}  



// ADD-Formular anzeigen
if ($action == "add") {

    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    

    echo '<div class="card">
            <div class="card-header"><i class="bi bi-paragraph"></i> ' . $languageService->get('clan_rules') . '</div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item"><a href="admincenter.php?site=admin_clan_rules">' . $languageService->get('clan_rules') . '</a></li>
                    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('add_clan_rules') . '</li>
                </ol>
            </nav>
            <div class="card-body">
            <div class="container py-5">
            <form method="post" action="admincenter.php?site=admin_clan_rules" enctype="multipart/form-data" onsubmit="return chkFormular();">
                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('clan_rules_name') . '</label>
                    <input class="form-control" type="text" name="title" maxlength="255" />
                </div>
                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('description') . '</label>
                    <textarea class="ckeditor form-control" name="message" rows="10" style="width: 100%;"></textarea>
                </div>
                <div class="mb-3">
                    <label class="control-label">' . $languageService->get('is_displayed') . '</label>
                    <input class="form-check-input" type="checkbox" name="displayed" value="1" checked />
                </div>
                <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                <button class="btn btn-success btn-sm" type="submit" name="save">' . $languageService->get('add_clan_rules') . '</button>
            </form>
            </div>
        </div></div>';
}

// EDIT-Formular anzeigen
elseif ($action == "edit" && isset($_GET["id"])) {

    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();



    $id = (int)$_GET["id"];
    $ds = mysqli_fetch_array(safe_query("SELECT * FROM plugins_clan_rules WHERE id='$id'"));

    $displayed = $ds['displayed'] == 1 ? 'checked' : '';

    echo '<div class="card">
            <div class="card-header"><i class="bi bi-paragraph"></i> ' . $languageService->get('clan_rules') . '</div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item"><a href="admincenter.php?site=admin_clan_rules">' . $languageService->get('clan_rules') . '</a></li>
                    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('edit_clan_rules') . '</li>
                </ol>
            </nav>
            <div class="card-body">
            <div class="container py-5">
            <form method="post" action="admincenter.php?site=admin_clan_rules" enctype="multipart/form-data" onsubmit="return chkFormular();">
                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('clan_rules_name') . '</label>
                    <input class="form-control" type="text" name="title" maxlength="255" value="' . htmlspecialchars($ds['title']) . '" />
                </div>
                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('description') . '</label>
                    <textarea class="ckeditor form-control" name="message" rows="10">' . htmlspecialchars($ds[ 'text' ]) . '</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">' . $languageService->get('is_displayed') . '</label>
                    <input class="form-check-input" type="checkbox" name="displayed" value="1" ' . $displayed . ' />
                </div>
                <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                <input type="hidden" name="id" value="' . $id . '" />
                <button class="btn btn-warning btn-sm" type="submit" name="saveedit">' . $languageService->get('edit_clan_rules') . '</button>
            </form>
            </div>
        </div></div>';
}

elseif ($action == "admin_clan_rules_settings") {

    $settings = safe_query("SELECT * FROM plugins_clan_rules_settings");
    $ds = mysqli_fetch_array($settings);

    $maxshownclan_rules = $ds['clan_rules'];
    if (empty($maxshownclan_rules)) {
        $maxshownclan_rules = 10;
    }

    $CAPCLASS = new \webspell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    echo '<div class="card">
            <div class="card-header">
                ' . $languageService->get('settings') . '
            </div>

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb t-5 p-2 bg-light">
                        <li class="breadcrumb-item"><a href="admincenter.php?site=admin_clan_rules">' . $languageService->get('clan_rules') . '</a></li>
                        <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('settings') . '</li>
                    </ol>
                </nav>

            <div class="card-body">  

                <div class="container py-5">
                    <form method="post" action="admincenter.php?site=admin_clan_rules&action=admin_clan_rules_settings">

                        <div class="mb-3 row">
                            <label class="col-sm-2 control-label">' . $languageService->get('max_clan_rules') . ':</label>
                            <div class="col-sm-1">
                                <input type="number" class="form-control" name="clan_rules" value="' . htmlspecialchars($ds['clan_rules']) . '" min="1" />
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <div class="col-sm-offset-2 col-sm-10">
                                <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                                <button class="btn btn-primary btn-sm" type="submit" name="clan_rules_settings_save">' . $languageService->get('update') . '</button>
                            </div>
                        </div>                    
                    </form>
                </div>
            </div>
        </div>
    ';
}
 else {


echo '<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> ' . $languageService->get('title') . '
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_clan_rules">' . $languageService->get('clan_rules') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>  

    <div class="card-body">

        <div class="form-group row">
            <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
            <div class="col-md-8">
                <a href="admincenter.php?site=admin_clan_rules&amp;action=add" class="btn btn-primary btn-sm" type="button">' . $languageService->get('new_clan_rules') . '</a>      
                <a href="admincenter.php?site=admin_clan_rules&action=admin_clan_rules_settings" class="btn btn-primary btn-sm" type="button">' . $languageService->get('settings') . '</a>
            </div>
        </div>

        <div class="container py-5">
            <div class="table-responsive">
                <form method="post" action="admincenter.php?site=admin_clan_rules">

                    <table class="table table-bordered table-striped bg-white shadow-sm">
                        <thead class="table-light">
                            <tr>
                                <th width="29%"><b>' . $languageService->get('clan_rules') . '</b></th>
                                <th width="15%"><b>' . $languageService->get('is_displayed') . '</b></th>
                                <th width="20%"><b>' . $languageService->get('actions') . '</b></th>
                                <th width="8%"><b>' . $languageService->get('sort') . '</b></th>
                            </tr>
                        </thead>
                        <tbody>';

    $CAPCLASS = new \webspell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    $qry = safe_query("SELECT * FROM plugins_clan_rules ORDER BY sort");
    $anz = mysqli_num_rows($qry);

    if ($anz) {
        $i = 1;
        while ($ds = mysqli_fetch_array($qry)) {
            $td = ($i % 2) ? 'td1' : 'td2';

            $displayed = ($ds['displayed'] == 1)
                ? '<font color="green"><b>' . $languageService->get('yes') . '</b></font>'
                : '<font color="red"><b>' . $languageService->get('no') . '</b></font>';

            $title = $ds['title'];
            $translate = new multiLanguage($lang);
            $translate->detectLanguages($title);
            $title = $translate->getTextByLanguage($title);

            echo '<tr>
                <td width="29%" class="' . $td . '">' . htmlspecialchars($title) . '</td>
                <td width="15%" class="' . $td . '">' . $displayed . '</td>
                <td width="20%" class="' . $td . '">
                    <a class="btn btn-warning btn-sm" href="admincenter.php?site=admin_clan_rules&amp;action=edit&amp;id=' . $ds['id'] . '" class="input">' . $languageService->get('edit') . '</a>
                    
                    <!-- Button trigger modal -->
<button 
    type="button" 
    class="btn btn-danger btn-sm" 
    data-bs-toggle="modal" 
    data-bs-target="#confirm-delete" 
    data-href="admincenter.php?site=admin_clan_rules&delete=true&id=' . $ds['id'] . '&captcha_hash=' . $hash . '">
    ' . $languageService->get('delete') . '
</button>
                    
                    <!-- Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteLabel">' . $languageService->get('clan_rules') . '</h5>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="' . $languageService->get('close') . '"></button>
            </div>
            <div class="modal-body">
                <p>' . $languageService->get('really_delete') . '</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">' . $languageService->get('close') . '</button>
                <a class="btn btn-danger btn-ok btn-sm" href="#">' . $languageService->get('delete') . '</a>
            </div>
        </div>
    </div>
</div>
                </td>
                <td width="8%" class="' . $td . '" align="center">
                    <select name="sort[]">';
            for ($j = 1; $j <= $anz; $j++) {
                $selected = ($ds['sort'] == $j) ? 'selected="selected"' : '';
                echo '<option value="' . $ds['id'] . '-' . $j . '" ' . $selected . '>' . $j . '</option>';
            }
            echo '</select>
                </td>
            </tr>';

            $i++;
        }
    } else {
        echo '<tr><td class="td1" colspan="6">' . $languageService->get('no_entries') . '</td></tr>';
    }

    echo '<tr>
            <td class="td_head" colspan="6" align="right">
                <input type="hidden" name="captcha_hash" value="' . $hash . '">
                <br>
                <input class="btn btn-primary btn-sm" type="submit" name="sortieren" value="' . $languageService->get('to_sort') . '" />
            </td>
        </tr>
        </tbody>
    </table>
    </form>
    </div>
    </div>
</div>
</div>';

}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var confirmDeleteModal = document.getElementById('confirm-delete');
    confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var href = button.getAttribute('data-href');
        var modalDeleteBtn = confirmDeleteModal.querySelector('.btn-ok');
        modalDeleteBtn.setAttribute('href', href);
    });
});
</script>