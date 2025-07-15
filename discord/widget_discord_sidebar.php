<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('discord');

$tpl = new Template();
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style'] ?? '');

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
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

<?php if (!empty($serverID)): ?>
  <!-- Discord Widget anzeigen -->
  
    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0">🎧 Unser Discord-Server</h5>
      </div>
      <div class="card-body p-0 bg-dark">
        <iframe
          src="https://discord.com/widget?id=<?= htmlspecialchars($serverID) ?>&theme=dark"
          width="100%"
          height="500"
          allowtransparency="true"
          frameborder="0"
          class="w-100 border-0 bg-dark"
          sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts">
        </iframe>
      </div>
    </div>
  
<?php else: ?>
  <!-- Hinweis, falls keine Server-ID gespeichert ist -->
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