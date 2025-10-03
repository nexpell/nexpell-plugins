<?php
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false];

// Datei vorhanden?
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Kein Bild erhalten oder Upload-Fehler.';
    echo json_encode($response);
    exit;
}

// Upload-Konfiguration
$uploadDir = __DIR__ . '/images/news_images/';
$webPath   = '/includes/plugins/news/images/news_images'; // URL relativ zum Webroot
$allowedExt = ['jpg','jpeg','png','gif','webp'];
$allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];
$maxSize = 5 * 1024 * 1024; // 5 MB

// Ordner anlegen
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// Dateiendung prüfen
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt)) {
    $response['message'] = 'Ungültiger Dateityp.';
    echo json_encode($response);
    exit;
}

// MIME prüfen
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowedMime)) {
    $response['message'] = 'Ungültiges Bildformat.';
    echo json_encode($response);
    exit;
}

// Größe prüfen
if ($_FILES['image']['size'] > $maxSize) {
    $response['message'] = 'Datei zu groß (max. 5 MB).';
    echo json_encode($response);
    exit;
}

// Echtes Bild?
if (false === getimagesize($_FILES['image']['tmp_name'])) {
    $response['message'] = 'Kein gültiges Bild.';
    echo json_encode($response);
    exit;
}

// Dateiname erzeugen
$filename = uniqid('img_') . '.' . $ext;
$filepath = $uploadDir . $filename;

// Datei speichern
if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    $response['message'] = 'Fehler beim Speichern.';
    echo json_encode($response);
    exit;
}

// Erfolgreiche Rückgabe
$response['success'] = true;
$response['url'] = $webPath . '/' . $filename;
echo json_encode($response);
