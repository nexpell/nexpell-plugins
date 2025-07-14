<?php
global $_database; // global verfügbar machen

$statusMsg = "";

// Beim POST speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $main = $_database->real_escape_string($_POST['main_channel'] ?? '');
  $extra = $_database->real_escape_string($_POST['extra_channels'] ?? '');

  $sql = "UPDATE plugins_twitch_settings SET main_channel='$main', extra_channels='$extra' WHERE id = 1";
  if ($_database->query($sql)) {
    $statusMsg = "<p style='color:green;'>✅ Einstellungen gespeichert!</p>";
  } else {
    $statusMsg = "<p style='color:red;'>Fehler: " . $_database->error . "</p>";
  }
}

// Aktuelle Werte holen
$result = $_database->query("SELECT main_channel, extra_channels FROM plugins_twitch_settings WHERE id = 1");
if ($result && $row = $result->fetch_assoc()) {
  $main_channel = $row['main_channel'];
  $extra_channels = $row['extra_channels'];
} else {
  $main_channel = "";
  $extra_channels = "";
}
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-journal-text me-2"></i> Twitch Admin
    </div>

    <nav aria-label="breadcrumb" class="px-4 pt-3">
        <ol class="breadcrumb bg-light rounded px-3 py-2">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=admin_twitch">Twitch Admin</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                ADD / EDIT
            </li>
        </ol>
    </nav>

    <div class="card-body">
        <div class="container">

            <h4 class="mb-4">Twitch-Kanäle verwalten</h4>

           

            <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="main_channel" class="form-label">Hauptkanal (mit Chat)</label>
                    <input type="text" class="form-control" id="main_channel" name="main_channel" value="<?= htmlspecialchars($main_channel) ?>" required>
                    <div class="invalid-feedback">
                        Bitte gib den Hauptkanal ein.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="extra_channels" class="form-label">Weitere Kanäle (kommagetrennt)</label>
                    <textarea class="form-control" id="extra_channels" name="extra_channels" rows="3"><?= htmlspecialchars($extra_channels) ?></textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save me-1"></i> Speichern
                </button>  <?= $statusMsg ?>
            </form>
        </div>


