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

echo "Length: " . strlen($html) . "\n\n";

// Find all eval(function(p,a,c,k,e,d) positions
echo "=== Searching for packer patterns ===\n";
$pattern = '/eval\(function\(p,a,c,k,e,d\)\{/si';
if (preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
    echo "Found " . count($matches[0]) . " matches\n";
    foreach ($matches[0] as $match) {
        $pos = $match[1];
        $context = substr($html, $pos, 200);
        echo "\nOffset $pos:\n";
        echo $context . "\n";
        
        // Now look for the closing }) pattern
        $rest = substr($html, $pos);
        echo "\nFirst 500 chars from match start:\n";
        echo substr($rest, 0, 500) . "\n";
    }
} else {
    echo "No eval(function(p,a,c,k,e,d) found!\n";
    
    // Try other packer patterns
    echo "\nTrying alternative patterns...\n";
    $patterns = [
        '/eval\(function\(/si',
        '/eval\(unescape\(/si',
        '/String\.fromCharCode/si',
        '/document\.write/si',
    ];
    foreach ($patterns as $p) {
        if (preg_match_all($p, $html, $m, PREG_OFFSET_CAPTURE)) {
            echo "Pattern $p: " . count($m[0]) . " matches\n";
            foreach (array_slice($m[0], 0, 3) as $match) {
                $pos = $match[1];
                echo "  @$pos: " . substr($html, $pos, 200) . "\n";
            }
        }
    }
    
    // Show all script tags
    echo "\n=== All script tags ===\n";
    preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $html, $scripts);
    foreach ($scripts[0] as $i => $script) {
        $len = strlen($script);
        $preview = strip_tags($script);
        $preview = preg_replace('/\s+/', ' ', $preview);
        echo "Script #$i ($len chars): " . substr($preview, 0, 200) . "\n";
    }
}
