<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database, $languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('twitch');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Twitch'
];

echo $tpl->loadTemplate("twitch", "head", $data_array, 'plugin');




global $_database;

$sql = "SELECT * FROM plugins_twitch_settings WHERE id = 1";
$result = $_database->query($sql);
$row = $result->fetch_assoc();

$main_channel = htmlspecialchars($row['main_channel']);
$extra_channels = htmlspecialchars($row['extra_channels']);
?>



  <!-- Stream-Bereich -->
  <div id="stream-wrapper" style="display:none;">

    <h2 class="mb-4">Hauptstream:</h2>
    <div id="main-stream"></div>

    <h3 class="mt-5 mb-3">Weitere Kanäle:</h3>
    <div class="extra-streams" id="extra-streams"></div>
  </div>

<div id="fallback-message" class="alert alert-info text-center mt-3" style="display:none;">
    ⚠️ Bitte akzeptieren Sie die Cookies, um die Twitch-Streams sehen zu können.
  </div>
<script>
  const TWITCH_CONFIG = {
    main: <?= json_encode($main_channel) ?>,
    extra: <?= json_encode($extra_channels) ?>
  };
</script>