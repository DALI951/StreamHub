<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

$config = require __DIR__ . '/../config.php';

$url = 'https://mixdrop.top/e/xwj3wezvb4wj7z';
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

echo "=== Looking for packer blocks ===\n";
preg_match_all('/eval\(function\(p,a,c,k,e,d\)\{.*?\}\((.*?)\)\s*\)/s', $html, $matches);
echo "Found " . count($matches[0]) . " packer blocks\n";

foreach ($matches[0] as $i => $block) {
    echo "\n--- Packer block {$i} ---\n";
    echo substr($block, 0, 500) . "...\n\n";

    if (preg_match('/}\((\'[^\']+\')\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*\'([^\']+)\'\s*\)/', $block, $m)) {
        $payload = $m[1];
        $base = (int)$m[2];
        $count = (int)$m[3];
        $dict = $m[4];

        echo "Base: {$base}, Count: {$count}\n";
        $words = explode('|', $dict);
        echo "Dictionary words: " . count($words) . "\n";
        echo "First 10 words: " . implode(', ', array_slice($words, 0, 10)) . "\n\n";

        // Decode
        $out = [];
        for ($j = 0; $j < $count; $j++) {
            $out[$j] = $words[$j] ?? '';
        }
        $payloadClean = substr($payload, 1, -1);
        $decoded = preg_replace_callback('/\b(\w+)\b/', function ($m2) use ($out) {
            return $out[$m2[1]] ?? $m2[0];
        }, $payloadClean);

        echo "Decoded:\n{$decoded}\n\n";

        // Look for URLs in decoded content
        preg_match_all('/(https?:\/\/[^\s"\'<>]+)/i', $decoded, $urlMatches);
        echo "URLs in decoded: " . count($urlMatches[1]) . "\n";
        foreach ($urlMatches[1] as $u) echo "  {$u}\n";
    }
}
