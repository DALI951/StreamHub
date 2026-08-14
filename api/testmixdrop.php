<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/../config.php';
$embedUrl = 'https://mixdrop.top/e/3nldg9rlbq1qg8';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $embedUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
]);
$html = curl_exec($ch);

// Find MDCore context
$pos = strpos($html, 'MDCore');
if ($pos !== false) {
    echo "=== MDCore context (pos {$pos}) ===\n";
    echo substr($html, max(0, $pos - 100), 500) . "\n\n";
}

// Find all script blocks
preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $html, $scripts, PREG_SET_ORDER);
echo "Script blocks: " . count($scripts) . "\n";
foreach ($scripts as $i => $s) {
    $content = trim($s[1]);
    if (strlen($content) > 20) {
        echo "\n--- Script {$i} (len " . strlen($content) . ") ---\n";
        echo substr($content, 0, 300) . "\n";
    }
}

// Also check for packed/obfuscated JS
preg_match_all('/eval\(function/p', $html, $evals);
echo "\neval(function count: " . count($evals[0]) . "\n";

// Check for base64
preg_match_all('/atob\(/', $html, $atob);
echo "atob count: " . count($atob[0]) . "\n";

// Check for document.write
preg_match_all('/document\.write/', $html, $dw);
echo "document.write count: " . count($dw[0]) . "\n";

// Check for innerHTML
preg_match_all('/innerHTML\s*=/', $html, $ih);
echo "innerHTML count: " . count($ih[0]) . "\n";
