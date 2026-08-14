<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$manager = new SourceManager();
$scraper = $manager->getScraper('egydead');

$embedUrl = 'https://morencius.com/v/q1vd5a7t0jdu';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $embedUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
    CURLOPT_REFERER        => 'https://tv10.egydead.live/',
]);
$html = curl_init(); curl_setopt($html, CURLOPT_URL, $embedUrl); curl_setopt($html, CURLOPT_RETURNTRANSFER, true); curl_setopt($html, CURLOPT_TIMEOUT, 10); curl_setopt($html, CURLOPT_FOLLOWLOCATION, true); curl_setopt($html, CURLOPT_SSL_VERIFYPEER, false); $html = curl_exec($html);

echo "HTML length: " . strlen($html) . "\n";

// Step 1: find eval(function(p,a,c,k,e,d){
$pattern = '/eval\(function\(p,a,c,k,e,d\)\{/si';
$found = preg_match_all($pattern, $html, $starts, PREG_OFFSET_CAPTURE);
echo "Step 1 - Pattern found: {$found}\n";

if ($found) {
    $start = $starts[0][0];
    echo "  Match: " . $start[0] . " at offset {$start[1]}\n";
    
    // Step 2: offset after match
    $offset = $start[1] + strlen($start[0]);
    echo "Step 2 - Offset after match: {$offset}\n";
    
    // Step 3: rest of HTML
    $rest = substr($html, $offset);
    echo "Step 3 - Rest length: " . strlen($rest) . "\n";
    echo "  First 200 chars: " . substr($rest, 0, 200) . "\n\n";
    
    // Step 4: find }(
    $found2 = preg_match('/\}\(/', $rest, $bodyEnd, PREG_OFFSET_CAPTURE);
    echo "Step 4 - }\( found: {$found2}\n";
    if ($found2) {
        echo "  Match: '" . $bodyEnd[0][0] . "' at offset {$bodyEnd[0][1]}\n";
        echo "  Context: " . substr($rest, max(0, $bodyEnd[0][1] - 20), 60) . "\n\n";
        
        // Step 5: args start
        $argsStart = $offset + $bodyEnd[0][1] + 2;
        echo "Step 5 - Args start: {$argsStart}\n";
        $argsStr = substr($html, $argsStart);
        echo "  First 100 chars: " . substr($argsStr, 0, 100) . "\n\n";
        
        // Step 6: payload
        $found3 = preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'/', $argsStr, $payloadMatch);
        echo "Step 6 - Payload found: {$found3}\n";
        if ($found3) {
            echo "  Payload length: " . strlen($payloadMatch[1]) . "\n";
            echo "  First 100: " . substr($payloadMatch[1], 0, 100) . "\n";
            
            $payloadEnd = $argsStart + strlen($payloadMatch[0]);
            $remaining = substr($html, $payloadEnd);
            echo "  Remaining first 200: " . substr($remaining, 0, 200) . "\n\n";
            
            // Step 7: rest of args
            $found4 = preg_match('/\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*\'([^\']+)\'(?:\.split\(\'\|\'\))?\s*,\s*\d+\s*,\s*\{\}\s*\)\s*\)/', $remaining, $restMatch);
            echo "Step 7 - Rest args found: {$found4}\n";
            if ($found4) {
                echo "  Base: {$restMatch[1]}, Count: {$restMatch[2]}\n";
                echo "  Dict first 100: " . substr($restMatch[3], 0, 100) . "\n";
            }
        }
    }
}
