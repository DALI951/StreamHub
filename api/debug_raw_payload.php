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

// Find the packer start
preg_match('/eval\(function\(p,a,c,k,e,d\)\{/si', $html, $m, PREG_OFFSET_CAPTURE);
$startPos = $m[1] + strlen($m[0]);
$rest = substr($html, $startPos);

// Find }(
preg_match('/\}\(/', $rest, $be, PREG_OFFSET_CAPTURE);
$argsStart = $startPos + $be[1] + 2;

echo "=== Raw payload content (hex + ASCII) ===\n";
$raw = substr($html, $argsStart, 300);
echo "String length: " . strlen($raw) . "\n";
echo "Raw content:\n$raw\n\n";

echo "Hex dump of first 100 bytes:\n";
for ($i = 0; $i < min(100, strlen($raw)); $i++) {
    $c = $raw[$i];
    $ord = ord($c);
    printf("%02x (%s) ", $ord, $c >= ' ' && $ord < 127 ? $c : '.');
    if (($i + 1) % 10 === 0) echo "\n";
}
echo "\n\n";

// Look for the first unescaped single quote in the payload
echo "=== Finding first unescaped single quote ===\n";
$payload = substr($html, $argsStart + 1); // skip opening quote
for ($i = 0; $i < strlen($payload); $i++) {
    $c = $payload[$i];
    if ($c === '\\' && $i + 1 < strlen($payload)) {
        echo "  Escaped char at offset $i: \\$payload[$i+1]\n";
        $i++; // skip escaped char
        continue;
    }
    if ($c === "'") {
        echo "  UNESCAPED single quote found at offset $i\n";
        echo "  Content around it: ..." . substr($payload, max(0, $i - 20), 40) . "...\n";
        break;
    }
}
