<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database, $languageService, $tpl;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('gametracker');

// Stilklasse laden
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class' => $class,
    'title' => $languageService->get('server_preview'),
    'subtitle' => 'Server Preview'
];
echo $tpl->loadTemplate("gametracker", "head", $data_array, 'plugin');


echo '<div class="card mb-4">';
echo '<div class="card-header">';
echo '<strong><i class="bi bi-controller"></i> GameServer</strong>';
echo '</div>';
echo '<ul class="list-group list-group-flush">';

require_once(__DIR__ . '/GameQ/Autoloader.php');
require_once(__DIR__ . '/GameQ/GameQ.php');

use GameQ\GameQ;

function stripColorCodes(?string $text): string {
    // $text kann jetzt null sein, wird mit '' versehen
    return preg_replace('/\^\d/', '', $text ?? '');
}

function colorizeName(string $text): string {
    $colors = [
        '0' => '#000000',
        '1' => '#FF0000',
        '2' => '#00FF00',
        '3' => '#FFFF00',
        '4' => '#0000FF',
        '5' => '#00FFFF',
        '6' => '#FF00FF',
        //'7' => '#FFFFFF',
        '8' => '#FFA500',
        '9' => '#A52A2A',
    ];

    $text = htmlspecialchars($text, ENT_QUOTES);

    $text = preg_replace_callback('/\^([0-9])/', function($matches) use ($colors) {
        $code = $matches[1];
        $color = $colors[$code] ?? 'inherit';
        return '<span style="color:' . $color . '">';
    }, $text);

    $openCount = substr_count($text, '<span');
    $closeCount = substr_count($text, '</span>');
    $text .= str_repeat('</span>', max(0, $openCount - $closeCount));

    return $text;
}

$servers = safe_query("SELECT * FROM plugins_gametracker_servers WHERE active = 1 ORDER BY sort_order LIMIT 6");

if (mysqli_num_rows($servers)) {
    $queryList = [];
    while ($ds = mysqli_fetch_array($servers)) {
        $queryList[] = [
            'id'   => 'server_' . (int)$ds['id'],
            'type' => strtolower($ds['game']),
            'host' => $ds['ip'] . ':' . $ds['port'],
            'game' => strtolower($ds['game'])
        ];
    }

    $gq = new GameQ();
    $gq->addServers($queryList);
    $results = $gq->process();

    function truncateText(string $text, int $maxLength): string {
    if (mb_strlen($text) > $maxLength) {
        return mb_substr($text, 0, $maxLength) . '…';
    }
    return $text;
}

$maxLength = 28;

foreach ($queryList as $server) {
    $id = $server['id'];
    $info = $results[$id] ?? null;

    $hostnameRaw = $info['gq_hostname'] ?? $server['name'];
    $hostnamePlain = stripColorCodes($hostnameRaw);
    $hostnameCut = truncateText($hostnamePlain, $maxLength);

    // Ohne Farben:
    echo '<li class="list-group-item small">';
    echo '<div class="d-flex justify-content-between">';
    echo '<strong>' . htmlspecialchars($hostnameCut) . '</strong>';

    
    // Spieleranzeige (immer)
    $players = (int)($info['gq_numplayers'] ?? 0);
    $maxPlayers = (int)($info['gq_maxplayers'] ?? 0);

    // Farbe der Badge je nach Spieleranzahl
    $badgeClass = ($players > 0) ? 'text-bg-success' : 'text-bg-secondary';

    echo '<span class="badge ' . $badgeClass . '">' . $players . '/' . $maxPlayers . '</span>';


        echo '</div>';

        $map = htmlspecialchars($info['mapname'] ?? $info['map'] ?? '-');
        echo '<div class="text-muted"><i class="bi bi-map"></i> ' . $map . '</div>';

        echo '</li>';
    }

} else {
    echo '<li class="list-group-item text-muted">Keine aktiven Server</li>';
}

echo '</ul>';
echo '<div class="card-footer text-center p-2">';
echo '<a href="index.php?site=gametracker" class="btn btn-sm btn-outline-primary w-100">Alle Server anzeigen</a>';
echo '</div>';
echo '</div>';


