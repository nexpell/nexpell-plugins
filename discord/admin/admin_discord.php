<?php



// Datenbankverbindung vorhanden? Nexpell benötigt meist $_database
global $_database;

function setPluginConfig($key, $value) {
    $check = safe_query("SELECT name FROM plugins_discord WHERE name = '" . escape($key) . "'");
    if (mysqli_num_rows($check)) {
        safe_query("UPDATE plugins_discord SET value = '" . escape($value) . "' WHERE name = '" . escape($key) . "'");
    } else {
        safe_query("INSERT INTO plugins_discord (name, value) VALUES ('" . escape($key) . "', '" . escape($value) . "')");
    }
}

function getPluginConfig($key) {
    $res = safe_query("SELECT value FROM plugins_discord WHERE name = '" . escape($key) . "'");
    if (mysqli_num_rows($res)) {
        $row = mysqli_fetch_assoc($res);
        return $row['value'];
    }
    return '';
}

// Speichern
if (isset($_POST['save'])) {
    $serverID = trim($_POST['discord_server_id']);
    setPluginConfig('server_id', $serverID);
    echo '<div class="alert alert-success">Discord Server-ID gespeichert.</div>';
}

// Aktuelle ID laden
$serverID = getPluginConfig('server_id');
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-journal-text"></i> Discord Widget
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_sponsors">Discord Widget</a>
            </li>
            <li class="breadcrumb-item is_active" aria-current="page">
                ADD / EDIT
            </li>
        </ol>
    </nav>

    <div class="card-body p-0">
        <div class="container py-5">

<h4>Discord Widget Konfiguration</h4>
<form method="post">
    <div class="form-group">
        <label for="discord_server_id">Discord Server-ID</label>
        <input type="text" name="discord_server_id" id="discord_server_id" class="form-control" value="<?= htmlspecialchars($serverID) ?>" required>
        <small class="form-text text-muted">Die Server-ID findest du in Discord unter Servereinstellungen → Widget → „Server ID“.</small>
    </div>
    <button type="submit" name="save" class="btn btn-primary mt-2">Speichern</button>
</form>
</div></div>
