<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/../config.php';

$urls = [
    'StreamHG'  => 'https://hgcloud.to/e/4c9bxsdo0aue',
    'EarnVids'  => 'https://morencius.com/v/q1vd5a7t0jdu',
    'Mixdrop'   => 'https://mixdrop.top/e/3nldg9rlbq1qg8',
    'DoodStream'=> 'https://playmogo.com/e/ul4mnvzrhts7',
];

foreach ($urls as $name => $url) {
    echo "=== {$name}: {$url} ===\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
        CURLOPT_REFERER        => 'https://tv10.egydead.live/',
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP: {$httpCode}, Length: " . strlen($html) . "\n";
    
    // Check for packer
    preg_match_all('/eval\(function\(p,a,c,k,e,d\)/si', $html, $e);
    echo "Packer evals: " . count($e[0]) . "\n";
    
    // Check for wurl
    if (preg_match('/wurl/i', $html)) echo "wurl: YES\n"; else echo "wurl: NO\n";
    
    // Check for .mp4/.m3u8 in raw text
    preg_match_all('/https?:\/\/[^\s"\'<>]+\.(?:m3u8|mp4)/i', $html, $urls2);
    echo "Direct URLs: " . count($urls2[0]) . "\n";
    foreach ($urls2[0] as $u) echo "  {$u}\n";
    
    // Check for MDCore
    if (preg_match('/MDCore/i', $html)) echo "MDCore: YES\n"; else echo "MDCore: NO\n";
    
    echo "\n";
}
