<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

// Test 1: Fetch season page with FOLLOWLOCATION
echo "=== Test 1: Season page with FOLLOWLOCATION ===\n";
$seasonUrl = "https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%ab%d8%a7%d9%86%d9%8a-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $seasonUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
echo "HTTP: $code, Final URL: $finalUrl\n";
echo "HTML length: " . strlen($html) . "\n";
if (preg_match('/og:title.*?content="([^"]+)"/', $html, $m)) echo "OG Title: {$m[1]}\n";

// Test 2: Parse episodes from this page
echo "\n=== Test 2: Episode links ===\n";
preg_match_all('/<a\s+href="([^"]*\/episode\/[^"]*)"[^>]*>(.*?)<\/a>/si', $html, $matches);
echo "Episode links: " . count($matches[1]) . "\n";
for ($i = 0; $i < min(5, count($matches[1])); $i++) {
    echo "  " . urldecode($matches[1][$i]) . "\n";
}

// Test 3: Check if search page has episodes
echo "\n=== Test 3: Search page ===\n";
$searchUrl = "https://tv10.egydead.live/?s=" . urlencode("مسلسل the mentalist الموسم الثاني");
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $searchUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
    ],
]);
$searchHtml = curl_exec($ch2);
$searchCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$searchFinal = curl_getinfo($ch2, CURLINFO_EFFECTIVE_URL);
curl_close($ch2);
echo "HTTP: $searchCode, Final URL: $searchFinal\n";
echo "HTML length: " . strlen($searchHtml) . "\n";
preg_match_all('/<a\s+href="([^"]*\/episode\/[^"]*)"[^>]*>(.*?)<\/a>/si', $searchHtml, $sMatches);
echo "Episode links: " . count($sMatches[1]) . "\n";
preg_match_all('/class="movieItem"/', $searchHtml, $mi);
echo "movieItem: " . count($mi[0]) . "\n";

// Test 4: Check a working episode page
echo "\n=== Test 4: Check recent episode from sidebar ===\n";
$testEp = "https://tv10.egydead.live/episode/the-walking-dead-dead-city-s03e03/";
$ch3 = curl_init();
curl_setopt_array($ch3, [
    CURLOPT_URL => $testEp,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);
$epHtml = curl_exec($ch3);
$epCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$epFinal = curl_getinfo($ch3, CURLINFO_EFFECTIVE_URL);
curl_close($ch3);
echo "HTTP: $epCode, Final URL: $epFinal\n";
echo "HTML length: " . strlen($epHtml) . "\n";
if (preg_match('/og:title.*?content="([^"]+)"/', $epHtml, $m)) echo "OG Title: {$m[1]}\n";
