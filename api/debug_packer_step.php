<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$url = 'https://morencius.com/v/mx7p6yocdl1t';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_REFERER        => 'https://tv10.egydead.live/',
]);
$html = curl_exec($ch);
curl_close($ch);

echo "=== Step-by-step packer extraction ===\n\n";

$pattern = '/eval\(function\(p,a,c,k,e,d\)\{/si';
if (!preg_match_all($pattern, $html, $starts, PREG_OFFSET_CAPTURE)) {
    echo "No packer found!\n";
    exit;
}

echo "Step 1: Found " . count($starts[0]) . " packer start(s)\n\n";

foreach ($starts[0] as $i => $start) {
    echo "--- Packer #$i ---\n";
    echo "Start offset: " . $start[1] . "\n";
    echo "Match length: " . strlen($start[0]) . "\n";
    
    $offset = $start[1] + strlen($start[0]);
    echo "Content after eval(function(...){ : " . substr($html, $offset, 200) . "\n\n";
    
    $rest = substr($html, $offset);
    echo "Step 2: Looking for })( pattern...\n";
    if (preg_match('/\}\(/', $rest, $bodyEnd, PREG_OFFSET_CAPTURE)) {
        echo "  Found at rest offset: " . $bodyEnd[0][1] . "\n";
        echo "  Match: " . $bodyEnd[0][0] . "\n";
        
        $argsStart = $offset + $bodyEnd[0][1] + 2;
        echo "  Args start (absolute): $argsStart\n";
        $argsStr = substr($html, $argsStart, 100);
        echo "  Args content (first 100): $argsStr\n\n";
        
        echo "Step 3: Looking for payload string...\n";
        if (preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'/', $argsStr, $payloadMatch)) {
            echo "  Payload found! Length: " . strlen($payloadMatch[1]) . "\n";
            echo "  Payload (first 200): " . substr($payloadMatch[1], 0, 200) . "\n";
            
            $payloadEnd = $argsStart + strlen($payloadMatch[0]);
            $remaining = substr($html, $payloadEnd, 200);
            echo "\n  Remaining after payload: $remaining\n\n";
            
            echo "Step 4: Looking for base, count, dict...\n";
            if (preg_match('/\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*\'([^\']+)\'(?:\.split\(\'\|\'\))?\s*,\s*\d+\s*,\s*\{\}\s*\)\s*\)/', substr($html, $payloadEnd), $restMatch)) {
                echo "  base: " . $restMatch[1] . "\n";
                echo "  count: " . $restMatch[2] . "\n";
                echo "  dict (first 200): " . substr($restMatch[3], 0, 200) . "\n";
                echo "  Full match: " . substr($restMatch[0], 0, 200) . "\n";
            } else {
                echo "  FAILED to match base/count/dict pattern!\n";
                echo "  Remaining content (first 500):\n";
                echo substr($html, $payloadEnd, 500) . "\n";
                
                // Try a more relaxed pattern
                echo "\n  Trying relaxed patterns...\n";
                if (preg_match('/,\s*(\d+)\s*,\s*(\d+)\s*,/', substr($html, $payloadEnd), $rm)) {
                    echo "  Found base=$rm[1] count=$rm[2]\n";
                    $after = substr($html, $payloadEnd + strlen($rm[0]), 300);
                    echo "  After base/count: $after\n";
                }
            }
        } else {
            echo "  FAILED to match payload string!\n";
            echo "  Content at args: " . substr($html, $argsStart, 300) . "\n";
        }
    } else {
        echo "  FAILED to find })( pattern!\n";
        echo "  First 300 of rest: " . substr($rest, 0, 300) . "\n";
    }
}
