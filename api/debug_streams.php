<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

$config = require __DIR__ . '/../config.php';
$url = 'https://tv10.egydead.live/episode/breaking-bad-s05e01/';

echo "=== Testing egydead POST for View=1 ===\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
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
    CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
    CURLOPT_REFERER        => $url,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP Code: {$httpCode}\n";
echo "Response length: " . strlen($response) . "\n\n";

echo "--- Looking for serversList ---\n";
if (preg_match('/serversList/si', $response)) {
    echo "FOUND serversList\n";
    preg_match('/<ul\s+class="serversList">(.*?)<\/ul>/si', $response, $m);
    if (!empty($m[1])) {
        echo "Server list HTML:\n" . substr($m[1], 0, 2000) . "\n\n";
    }
} else {
    echo "NO serversList found\n\n";
}

echo "--- Looking for data-link ---\n";
preg_match_all('/data-link="([^"]+)"/si', $response, $m);
echo "data-link matches: " . count($m[1]) . "\n";
foreach ($m[1] as $i => $url) {
    echo "  [{$i}] {$url}\n";
}

echo "\n--- Looking for iframes ---\n";
preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/', $response, $m);
echo "iframe matches: " . count($m[1]) . "\n";
foreach ($m[1] as $i => $url) {
    echo "  [{$i}] {$url}\n";
}

echo "\n--- Looking for watchArea ---\n";
if (preg_match('/watchAreaMaster/si', $response)) {
    echo "FOUND watchAreaMaster\n";
} else {
    echo "NO watchAreaMaster\n";
}

echo "\n--- Looking for holder ---\n";
if (preg_match('/class="holder"/si', $response)) {
    echo "FOUND holder\n";
} else {
    echo "NO holder\n";
}
