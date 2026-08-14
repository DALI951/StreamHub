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

echo "HTML length: " . strlen($html) . "\n\n";

// Extract the packer block directly
if (preg_match('/eval\(function\(p,a,c,k,e,d\)\{.*?\}\((.*?)\)\s*\)/s', $html, $m)) {
    $block = $m[0];
    echo "=== Packer block (first 2000 chars) ===\n";
    echo substr($block, 0, 2000) . "\n\n";

    // Try to extract the regex components
    if (preg_match('/}\((\'[^\']+\')\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\'[^\']+\')\s*\)/', $block, $m2)) {
        echo "=== Regex match ===\n";
        echo "Payload: " . substr($m2[1], 0, 500) . "\n";
        echo "Base: {$m2[2]}\n";
        echo "Count: {$m2[3]}\n";
        echo "Dict: " . substr($m2[4], 0, 500) . "\n\n";

        $payload = $m2[1];
        $base = (int)$m2[2];
        $count = (int)$m2[3];
        $dict = trim($m2[4], "'");

        $words = explode('|', $dict);
        echo "Dictionary word count: " . count($words) . "\n";
        echo "First 20 words:\n";
        for ($i = 0; $i < min(20, count($words)); $i++) {
            echo "  [{$i}] {$words[$i]}\n";
        }
        echo "\n";

        $out = [];
        for ($j = 0; $j < $count; $j++) {
            $out[$j] = $words[$j] ?? '';
        }
        $payloadClean = substr($payload, 1, -1);
        echo "Payload to decode (first 500): " . substr($payloadClean, 0, 500) . "\n\n";

        $decoded = preg_replace_callback('/\b(\w+)\b/', function ($m3) use ($out) {
            return $out[$m3[1]] ?? $m3[0];
        }, $payloadClean);

        echo "=== DECODED (first 2000) ===\n";
        echo substr($decoded, 0, 2000) . "\n\n";

        // Look for URLs
        preg_match_all('/(https?:\/\/[^\s"\'<>;]+)/i', $decoded, $urls);
        echo "URLs found: " . count($urls[1]) . "\n";
        foreach ($urls[1] as $u) echo "  " . str_replace('\\/', '/', $u) . "\n";
    } else {
        echo "REGEX DID NOT MATCH!\n";
        // Try to see what's at the end
        echo "Last 300 chars of block:\n";
        echo substr($block, -300) . "\n";
    }
} else {
    echo "NO packer found\n";
}
