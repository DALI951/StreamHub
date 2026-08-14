<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$embedUrls = [
    'StreamHG' => 'https://hgcloud.to/e/sda8hn53rrp8',
    'EarnVids' => 'https://morencius.com/v/mx7p6yocdl1t',
    'Mixdrop'  => 'https://mixdrop.top/e/rwlzjozjbgx3kl',
];

$referers = [
    'StreamHG' => 'https://tv10.egydead.live/episode/test/',
    'EarnVids' => 'https://tv10.egydead.live/episode/test/',
    'Mixdrop'  => 'https://tv10.egydead.live/episode/test/',
];

foreach ($embedUrls as $name => $url) {
    echo "=== $name: $url ===\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_REFERER        => $referers[$name],
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    echo "HTTP: $httpCode\n";
    echo "Final URL: $finalUrl\n";
    echo "Length: " . strlen($html) . "\n";
    
    if (!$html || strlen($html) < 100) {
        echo "Content: " . ($html ?: '(empty)') . "\n\n";
        continue;
    }
    
    // Check for packer blocks
    preg_match_all('/eval\(function\(p,a,c,k,e,d\)\{/si', $html, $packerMatches);
    echo "Packer blocks: " . count($packerMatches[0]) . "\n";
    
    // Check for video URLs directly
    preg_match_all('#(https?://[^\s"\'<>\[\]]+\.(?:m3u8|mp4)[^\s"\'<>\[\]]*)#i', $html, $urlMatches);
    echo "Direct video URLs: " . count($urlMatches[1]) . "\n";
    foreach ($urlMatches[1] as $u) {
        echo "  $u\n";
    }
    
    // Check for wurl
    preg_match_all('/wurl\s*[=:]\s*["\']?([^\s"\'<>]+)["\']?/i', $html, $wurlMatches);
    echo "wurl matches: " . count($wurlMatches[1]) . "\n";
    foreach ($wurlMatches[1] as $u) {
        echo "  $u\n";
    }
    
    // Check for MDCore
    if (preg_match('/MDCore\s*=\s*\{([^}]+)\}/i', $html, $mdMatch)) {
        echo "MDCore: " . substr($mdMatch[1], 0, 300) . "\n";
    }
    if (preg_match('/MDCore\.ref\s*=\s*["\']([^"\']+)["\']/i', $html, $mdRef)) {
        echo "MDCore.ref: " . $mdRef[1] . "\n";
    }
    if (preg_match('/MDCore\.wurl\s*=\s*["\']([^"\']+)["\']/i', $html, $mwUrl)) {
        echo "MDCore.wurl: " . $mwUrl[1] . "\n";
    }
    
    // Check for file/src/source patterns
    preg_match_all('#\b(?:file|src|source)\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']#i', $html, $fileMatches);
    echo "file/src/source patterns: " . count($fileMatches[1]) . "\n";
    foreach ($fileMatches[1] as $u) {
        echo "  $u\n";
    }
    
    // Check for nurl
    preg_match_all('#nurl\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']#i', $html, $nurlMatches);
    echo "nurl patterns: " . count($nurlMatches[1]) . "\n";
    foreach ($nurlMatches[1] as $u) {
        echo "  $u\n";
    }
    
    // Show snippet of HTML
    if (strlen($html) < 3000) {
        echo "\n--- Full HTML ---\n";
        echo $html . "\n";
    } else {
        echo "\n--- HTML snippet (first 2000) ---\n";
        echo substr($html, 0, 2000) . "\n";
        
        // Look for the body/end of head
        if (preg_match('/<body[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[1];
            echo "\n--- HTML around <body> ---\n";
            echo substr($html, $pos, 2000) . "\n";
        }
    }
    
    echo "\n\n";
}
