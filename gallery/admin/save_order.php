<?php
$configPath = __DIR__ . '/../../../../system/config.inc.php';
require_once $configPath;

$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) {
    die("DB-Verbindung fehlgeschlagen: " . $_database->connect_error);
}

$data = json_decode(file_get_contents('php://input'), true);

foreach ($data as $item) {
    $id = (int)$item['id'];
    $position = (int)$item['position'];
    $sql = "UPDATE plugins_gallery SET position = $position WHERE id = $id";
    if (!$_database->query($sql)) {
        file_put_contents(__DIR__ . '/error.log', "DB-Fehler bei ID $id: " . $_database->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        exit("DB Fehler");
    }
}

echo 'OK';
