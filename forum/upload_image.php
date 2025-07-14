<?php
header('Content-Type: application/json');

$response = ['success' => false];

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Kein Bild erhalten oder Upload-Fehler.';
    echo json_encode($response);
    exit;
}

$uploadDir = __DIR__ . '/uploads/forum_images/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$filename = uniqid('img_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filepath = $uploadDir . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    $response['success'] = true;
    $response['url'] = '/uploads/forum_images/' . $filename;
} else {
    $response['message'] = 'Fehler beim Speichern.';
}

echo json_encode($response);
