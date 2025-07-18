<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('discord');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Discord'
];

echo $tpl->loadTemplate("discord", "head", $data_array, 'plugin');

// Funktion zum Konfig-Wert holen
function getPluginConfig($key) {
    $res = safe_query("SELECT value FROM plugins_discord WHERE name = '" . escape($key) . "'");
    if (mysqli_num_rows($res)) {
        $row = mysqli_fetch_assoc($res);
        return $row['value'];
    }
    return '';
}

// Konfig-Wert abrufen
$serverID = getPluginConfig('server_id');
$roles = $_SESSION['user']['roles'] ?? [];
$isAdmin = is_array($roles) && in_array('admin', $roles);
?>

<!-- Discord Widget (nur wenn konfiguriert) -->
<?php if (!empty($serverID)): ?>
  <div id="discord-card" style="display:none;">
    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0">🎧 Unser Discord-Server</h5>
      </div>
      <div class="card-body p-0 bg-dark" id="discord-widget">
        <!-- Widget wird per JS eingefügt -->
      </div>
    </div>
  </div>

  <!-- Fallback für Cookie-Ablehnung -->
  <div id="fallback-discord" class="alert alert-info text-center mt-3" style="display:none;">
    ⚠️ Bitte akzeptieren Sie die Cookies, um unseren Discord-Server sehen zu können.
  </div>

<?php else: ?>
  <div class="container my-4">
    <div class="alert alert-warning text-center" role="alert">
      ⚠️ Unser Discord-Widget ist derzeit nicht verfügbar.<br>
      <?php if ($isAdmin): ?>
        Es wurde noch keine Discord Server-ID konfiguriert. <a href="/admin/admincenter.php?site=admin_discord">Jetzt konfigurieren</a>.
      <?php else: ?>
        Die Verbindung zum Discord-Server konnte nicht hergestellt werden.
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Übergabe der Server-ID an JavaScript -->
<script>
  const DISCORD_CONFIG = {
    serverID: "<?= htmlspecialchars($serverID) ?>"
  };
</script>


