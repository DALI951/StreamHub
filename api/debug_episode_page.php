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

// Search for server-related patterns
echo "=== Looking for server/AJAX patterns ===\n\n";

$patterns = [
    'serversList' => '/serversList/i',
    'data-link' => '/data-link/i',
    'data-server' => '/data-server/i',
    'ajax' => '/ajax|wp-admin|wp-ajax/i',
    'server-list' => '/server.?list/i',
    'embed' => '/embed/i',
    'wp_json' => '/wp-json|rest_route/i',
    'api_endpoint' => '/api\.|\/api\//i',
    'action' => '/action\s*[=:]\s*["\'][^"\']+["\']/i',
    'admin-ajax' => '/admin-ajax/i',
];

foreach ($patterns as $label => $pattern) {
    if (preg_match_all($pattern, $response, $m, PREG_OFFSET_CAPTURE)) {
        echo "--- $label (" . count($m[0]) . " matches) ---\n";
        foreach (array_slice($m[0], 0, 5) as $match) {
            $pos = $match[1];
            $start = max(0, $pos - 80);
            $end = min(strlen($response), $pos + 120);
            $snippet = substr($response, $start, $end - $start);
            echo "  @$pos: ...$snippet...\n";
        }
        echo "\n";
    }
}

// Look for script blocks that reference server loading
echo "\n=== Script blocks containing 'server' or 'embed' or 'view' ===\n";
preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $response, $scripts);
foreach ($scripts[1] as $i => $script) {
    if (strlen($script) > 20 && preg_match('/server|embed|view|ajax|fetch|post/i', $script)) {
        echo "\n--- Script #$i (" . strlen($script) . " chars) ---\n";
        echo substr($script, 0, 800) . "\n";
    }
}
