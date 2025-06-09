<?php
if (!isset($_GET['url'])) {
    http_response_code(400);
    exit('Missing URL');
}

$url = filter_var($_GET['url'], FILTER_VALIDATE_URL);
if (!$url) {
    http_response_code(400);
    exit('Invalid URL');
}

// OG-Image auslesen
$context = stream_context_create(['http' => ['timeout' => 3]]);
$html = @file_get_contents($url, false, $context);
if (!$html) {
    http_response_code(404);
    exit('URL not reachable');
}

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($html);
$xpath = new DOMXPath($doc);
$metaTags = $xpath->query("//meta[@property='og:image']");

$image = null;
if ($metaTags->length > 0) {
    $image = $metaTags->item(0)->getAttribute('content');
}

header('Content-Type: application/json');
echo json_encode(['og_image' => $image]);
