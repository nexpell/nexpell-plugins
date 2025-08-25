<?php
header('Content-Type: application/json');

$response = ['success' => false];

// Prüfen, ob Datei hochgeladen wurde
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Kein Bild erhalten oder Upload-Fehler.';
    echo json_encode($response);
    exit;
}

// Upload-Verzeichnis (physischer Pfad)
$uploadDir = __DIR__ . '/uploads/forum_images/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// Dateiendung prüfen (nur sichere Bildformate)
$allowedExtensions = ['jpg','jpeg','png','gif','webp'];
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
    $response['message'] = 'Ungültiger Dateityp. Nur jpg, jpeg, png, gif, webp erlaubt.';
    echo json_encode($response);
    exit;
}

// Eindeutigen Dateinamen generieren (ohne Dezimal-Anhang)
$filename = uniqid('img_') . '.' . $ext;
$filepath = $uploadDir . $filename;

// Datei speichern
if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    $response['message'] = 'Fehler beim Speichern.';
    echo json_encode($response);
    exit;
}

// Pfad relativ zum Webroot
$relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $uploadDir);
$relativePath = rtrim($relativePath, '/');

// Fertige relative URL
$response['url'] = $relativePath . '/' . $filename;
$response['success'] = true;

// JSON zurückgeben
echo json_encode($response);
