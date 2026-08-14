<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$testUrl = 'https://tv10.egydead.live/episode/the-mentalist-1-season/episode-1/';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['View' => '1']),
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_REFERER        => $testUrl,
]);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');

// Look for the content area - likely has episode data
echo "=== Looking for episode content ===\n\n";

// Search for common WP theme patterns for episode display
$patterns = [
    'episode-post' => '/class="[^"]*episode[^"]*"/i',
    'player-area' => '/class="[^"]*player[^"]*"/i',
    'watch-area' => '/class="[^"]*watch[^"]*"/i',
    'download-area' => '/class="[^"]*download[^"]*"/i',
    'view-button' => '/view|View|مشاهد/i',
    'servers-area' => '/servers|سيرفرات|السيرفرات/i',
    'data-id' => '/data-id="([^"]+)"/i',
    'post-id' => '/post-(\d+)/i',
    'wp-json' => '/wp-json|rest_route/i',
    'nonce' => '/nonce|_wpnonce/i',
];

foreach ($patterns as $label => $pattern) {
    if (preg_match_all($pattern, $response, $m, PREG_OFFSET_CAPTURE)) {
        echo "--- $label (" . count($m[0]) . " matches) ---\n";
        foreach (array_slice($m[0], 0, 3) as $match) {
            $pos = $match[1];
            $start = max(0, $pos - 100);
            $end = min(strlen($response), $pos + 200);
            echo "  @$pos: " . substr($response, $start, $end - $start) . "\n\n";
        }
    }
}

// Look for the theme's Ajax references
echo "\n=== Theme Ajax references ===\n";
if (preg_match_all('/(?:data-ajax|data-admin-ajax|Ajax\/)([^"\'<>\s]*)/i', $response, $m)) {
    foreach (array_unique($m[0]) as $url) {
        echo "  $url\n";
    }
}

// Look for data attributes on main content elements
echo "\n=== data- attributes with IDs or actions ===\n";
preg_match_all('/data-(?:id|action|type|post|nonce|token|key|url|src|ajax)[^=]*="([^"]+)"/i', $response, $m);
foreach (array_unique($m[0]) as $attr) {
    echo "  $attr\n";
}

// Find the main content area
echo "\n=== Content around 'episode' or 'server' in body ===\n";
$bodyStart = strpos($response, '<body');
if ($bodyStart) {
    $body = substr($response, $bodyStart);
    // Look for the section that contains the episode player
    if (preg_match('/class="[^"]*(?:single-content|entry-content|post-content|article-content|movie-content)[^"]*"/i', $body, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[1];
        echo "Found content area at offset $pos:\n";
        echo substr($body, $pos, min(2000, strlen($body) - $pos)) . "\n";
    }
}

// Look for any hidden or dynamic elements
echo "\n=== Hidden inputs / data containers ===\n";
preg_match_all('/<input[^>]+type=["\']hidden["\'][^>]*>/i', $response, $m);
foreach ($m[0] as $input) {
    echo "  $input\n";
}

// Look for any JavaScript that loads content
echo "\n=== JS that might load servers (fetch/XMLHttpRequest/$.ajax/$.get/$.post) ===\n";
preg_match_all('/\$\.(?:ajax|get|post|getJSON)\s*\(\s*["\']([^"\']+)["\']/', $response, $m);
foreach ($m[1] as $url) {
    echo "  AJAX URL: $url\n";
}

preg_match_all('/fetch\s*\(\s*["\']([^"\']+)["\']/', $response, $m);
foreach ($m[1] as $url) {
    echo "  Fetch URL: $url\n";
}
