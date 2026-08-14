<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

$config = require __DIR__ . '/../config.php';

$embedUrls = [
    'https://hgcloud.to/e/9gkmfuju8bhl',
    'https://mixdrop.top/e/xwj3wezvb4wj7z',
];

foreach ($embedUrls as $url) {
    echo "=== {$url} ===\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        ],
        CURLOPT_REFERER        => 'https://tv10.egydead.live/',
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP: {$httpCode}, Length: " . strlen($html) . "\n";

    // Check for iframes
    preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/', $html, $m);
    echo "iframes: " . count($m[1]) . "\n";
    foreach ($m[1] as $i => $u) echo "  [{$i}] {$u}\n";

    // Check for video sources
    preg_match_all('/(https?:\/\/[^\s"\'<>]+\.(?:m3u8|mp4)[^\s"\'<>]*)/i', $html, $m);
    echo "direct video: " . count($m[1]) . "\n";
    foreach ($m[1] as $i => $u) echo "  [{$i}] " . str_replace('\\/', '/', $u) . "\n";

    // Check for packer
    preg_match_all('/eval\(function\(p,a,c,k,e,d\)/', $html, $m);
    echo "packer blocks: " . count($m[0]) . "\n";

    // Check for sources variable
    if (preg_match('/sources\s*[=:]\s*\[(.*?)\]/si', $html, $m)) {
        echo "sources: " . substr($m[1], 0, 500) . "\n";
    }

    echo "\n--- First 3000 chars of body ---\n";
    // Skip head section
    if (preg_match('/<body[^>]*>(.*)/si', $html, $m)) {
        echo substr(strip_tags($m[1]), 0, 3000) . "\n";
    }

    echo "\n\n";
}
