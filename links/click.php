<?php

$configPath = __DIR__ . '/../../../system/config.inc.php';
if (!file_exists($configPath)) {
    die("Fehler: Konfigurationsdatei nicht gefunden.");
}
require_once $configPath;

// Datenbankverbindung aufbauen
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    die("Datenbank-Verbindungsfehler: " . $_database->connect_error);
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Partner-URL holen
    $stmt = $_database->prepare("SELECT url FROM plugins_links WHERE id = ?");
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

        $insert = $_database->prepare("
            INSERT INTO link_clicks (plugin, itemID, url, clicked_at, ip_address, user_agent)
            VALUES ('links', ?, ?, ?, ?, ?)
        ");
        if (!$insert) {
            die("Prepare failed: " . $_database->error);
        }
        $insert->bind_param("issss", $id, $fullUrl, $clickedAt, $ip, $userAgent);
        $insert->execute();
        $insert->close();

        // Weiterleitung
        header("Location: " . $fullUrl);
        exit;
    } else {
        $stmt->close();
        echo "Links nicht gefunden.";
    }
} else {
    echo "Ungültige ID.";
}