<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/../config.php';

$episodeUrl = 'https://tv10.egydead.live/episode/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%b3%d8%a7%d8%a8%d8%b9-%d8%a7%d9%84%d8%ad%d9%84%d9%82%d8%a9-1/';

echo "URL: {$episodeUrl}\n";

// GET
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $episodeUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
]);
$html = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "GET HTTP: {$http}, Length: " . strlen($html) . "\n";
if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) echo "Title: {$m[1]}\n";
if (preg_match('/serversList/si', $html)) echo "serversList FOUND\n";
preg_match_all('/data-link="([^"]+)"/', $html, $m);
echo "data-link count: " . count($m[1]) . "\n";

echo "\n--- POST with View=1 ---\n";
curl_setopt_array($ch, [
    CURLOPT_URL        => $episodeUrl,
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(['View' => '1']),
    CURLOPT_REFERER    => $episodeUrl,
]);
$postHtml = curl_exec($ch);
$http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "POST HTTP: {$http2}, Length: " . strlen($postHtml) . "\n";
if (preg_match('/serversList/si', $postHtml)) echo "serversList FOUND\n";
preg_match_all('/data-link="([^"]+)"/', $postHtml, $m);
echo "data-link count: " . count($m[1]) . "\n";
foreach ($m[1] as $u) echo "  {$u}\n";

echo "\n--- Page content check ---\n";
echo "Contains 'الحلقة 1': " . (str_contains($postHtml, 'الحلقة 1') ? 'YES' : 'NO') . "\n";
echo "Contains 'mentalist': " . (str_contains($postHtml, 'mentalist') ? 'YES' : 'NO') . "\n";
echo "Contains 'لا يوجد': " . (str_contains($postHtml, 'لا يوجد') ? 'YES' : 'NO') . "\n";
echo "Contains '404': " . (str_contains($postHtml, '404') ? 'YES' : 'NO') . "\n";
