<?php
if (empty($_GET['url'])) {
    echo json_encode(['image' => null]);
    exit;
}

$url = $_GET['url'];

// OG-Image extrahieren
function get_og_image($url) {
    $html = @file_get_contents($url);
    if (!$html) return null;

    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
        return $matches[1];
    }
    if (preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

$image = get_og_image($url);
echo json_encode(['image' => $image]);
exit;
