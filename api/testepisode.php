<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/../config.php';
$url = 'https://tv10.egydead.live/episode/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%b3%d8%a7%d8%a8%d8%b9-%d8%a7%d9%84%d8%ad%d9%84%d9%82%d8%a9-1/';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['View' => '1']),
    CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
    CURLOPT_REFERER        => $url,
]);
$html = curl_exec($ch);

preg_match_all('/data-link="([^"]+)"/si', $html, $m);
echo "data-link URLs: " . count($m[1]) . "\n";

foreach ($m[1] as $i => $embedUrl) {
    echo "\n--- Server " . ($i+1) . ": {$embedUrl} ---\n";
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL            => $embedUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
        CURLOPT_REFERER        => $url,
    ]);
    $embedHtml = curl_exec($ch2);
    $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo "HTTP: {$httpCode}, Length: " . strlen($embedHtml) . "\n";
    
    preg_match_all('/(https?:\/\/[^\s"\'<>]+\.(?:m3u8|mp4)[^\s"\'<>]*)/i', $embedHtml, $sm);
    echo "Direct URLs found: " . count($sm[1]) . "\n";
    foreach ($sm[1] as $su) {
        echo "  {$su}\n";
    }
    
    preg_match_all('/(\/\/[^\s"\'<>]+\.(?:m3u8|mp4)[^\s"\'<>]*)/i', $embedHtml, $pm);
    echo "Protocol-relative URLs found: " . count($pm[1]) . "\n";
    foreach ($pm[1] as $pu) {
        echo "  {$pu}\n";
    }
    
    if (preg_match('/wurl\s*[=:]\s*["\']?(\/\/[^\s"\'<>]+)["\']?/i', $embedHtml, $wm)) {
        echo "wurl found: {$wm[1]}\n";
    }
    
    if (preg_match('/MDCore\.wurl\s*=\s*["\']([^"\']+)["\']/i', $embedHtml, $mw)) {
        echo "MDCore.wurl found: {$mw[1]}\n";
    }
}
