<?php

$configPath = __DIR__ . '/../../../system/config.inc.php';
if (!file_exists($configPath)) {
    die("Fehler: Konfigurationsdatei nicht gefunden.");
}
require_once $configPath;

// Datenbankverbindung aufbauen
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Partner-URL holen
    $stmt = $_database->prepare("SELECT url FROM plugins_partners WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $_database->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($url);
    
    if ($stmt->fetch()) {
        $stmt->close();

        // URL formatieren
        $fullUrl = (stripos($url, 'http') === 0) ? $url : 'http://' . $url;

        // Klick in zentrale Tabelle speichern
        $clickedAt = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        $insert = $_database->prepare("
            INSERT INTO link_clicks (plugin, itemID, url, clicked_at, ip_address, user_agent, referrer)
            VALUES ('partners', ?, ?, ?, ?, ?, ?)
        ");
        if (!$insert) {
            die("Prepare failed: " . $_database->error);
        }
        $insert->bind_param("isssss", $id, $fullUrl, $clickedAt, $ip, $userAgent, $referrer);
        $insert->execute();
        $insert->close();

        // Weiterleitung
        header("Location: " . $fullUrl);
        exit;
    } else {
        $stmt->close();
        echo "Partner nicht gefunden.";
    }
} else {
    echo "Ungültige ID.";
}