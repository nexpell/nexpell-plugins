<?php

use webspell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('about');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('about');

if (isset($_POST['save'])) {
    $title = htmlspecialchars($_POST['title']);
    $intro = htmlspecialchars($_POST['intro']);
    $history = htmlspecialchars($_POST['history']);
    $core_values = htmlspecialchars($_POST['core_values']);
    $team = htmlspecialchars($_POST['team']);
    $cta = htmlspecialchars($_POST['cta']);

    $upload_path = ''.$plugin_path . 'images/';
    $image_fields = ['image1', 'image2', 'image3'];
    $image_names = [];

    foreach ($image_fields as $index => $field) {
        if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $filename = basename($_FILES[$field]['name']);
            $target_path = $upload_path . $filename;
            move_uploaded_file($_FILES[$field]['tmp_name'], $target_path);
            $image_names[$field] = '' . $filename;
        } else {
            $image_names[$field] = $_POST['existing_' . $field] ?? '';
        }
    }

    // Update oder Insert (je nach vorhandener Zeile)
    $result = safe_query("SELECT id FROM plugins_about");
    if (mysqli_num_rows($result)) {
        safe_query("
            UPDATE plugins_about SET
                title = '$title',
                intro = '$intro',
                history = '$history',
                core_values = '$core_values',
                team = '$team',
                cta = '$cta',
                image1 = '" . htmlspecialchars($image_names['image1']) . "',
                image2 = '" . htmlspecialchars($image_names['image2']) . "',
                image3 = '" . htmlspecialchars($image_names['image3']) . "'
        ");
    } else {
        safe_query("
            INSERT INTO plugins_about
            (title, intro, history, core_values, team, cta, image1, image2, image3)
            VALUES (
                '$title', '$intro', '$history', '$core_values', '$team', '$cta',
                '" . htmlspecialchars($image_names['image1']) . "',
                '" . htmlspecialchars($image_names['image2']) . "',
                '" . htmlspecialchars($image_names['image3']) . "'
            )
        ");
    }

    echo '<div class="alert alert-success">✔️ Daten erfolgreich gespeichert.</div>';
}

// Daten laden
$result = safe_query("SELECT * FROM plugins_about");
$data = mysqli_fetch_array($result);
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> Über uns – Verwaltung
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_gallery">Über uns – Verwaltung</a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>  

    <div class="card-body">

        <div class="container py-5">
    
    <form method="post" enctype="multipart/form-data" class="row g-4">
        <?php
        $fields = [
            'title' => 'Titel',
            'intro' => 'Intro',
            'history' => 'Unsere Geschichte',
            'core_values' => 'Was uns auszeichnet',
            'team' => 'Das Team',
            'cta' => 'Call to Action'
        ];
        foreach ($fields as $key => $label) {
            echo '<div class="col-md-12">';
            echo "<label class='form-label'>$label</label>";
            echo "<textarea name='$key' class='form-control' rows='3'>" . htmlspecialchars($data[$key] ?? '') . "</textarea>";
            echo '</div>';
        }
        ?>

        <?php
        $images = [
            'image1' => 'Intro-Bild',
            'image2' => 'Geschichte-Bild',
            'image3' => 'Team-Bild'
        ];
        foreach ($images as $key => $label) {
            $img_src = '../'.$plugin_path . 'images/' . ($data[$key] ?? '');
            echo '<div class="col-md-4">';
            echo "<label class='form-label'>$label</label>";
            echo "<input type='file' name='$key' class='form-control'>";
            if (!empty($data[$key])) {
                echo "<div class='mt-2'><img src='$img_src' alt='$key' style='max-width: 100%; max-height: 200px;' class='img-thumbnail'></div>";
                echo "<input type='hidden' name='existing_$key' value='" . htmlspecialchars($data[$key]) . "'>";
            }
            echo '</div>';
        }
        ?>

        <div class="col-12">
            <button type="submit" name="save" class="btn btn-primary">Speichern</button>
        </div>
    </form>
</div>
</div>
