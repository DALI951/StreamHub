<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$seasonUrl = "https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%ab%d8%a7%d9%86%d9%8a-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/";

$path = parse_url($seasonUrl, PHP_URL_PATH);
$slug = urldecode(basename(rtrim($path, '/')));
echo "Slug: $slug\n\n";

$searchQuery = str_replace(['مسلسل-', 'مسلسل ', 'الموسم-', 'الموسم ', 'مترجم', 'كامل', '-'], ' ', $slug);
$searchQuery = preg_replace('/\s+/', ' ', trim($searchQuery));
echo "Search query: [$searchQuery]\n\n";

$searchUrl = 'https://tv10.egydead.live/?s=' . urlencode($searchQuery);
echo "Search URL: $searchUrl\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $searchUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$searchHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
echo "HTML length: " . strlen($searchHtml) . "\n\n";

if ($searchHtml) {
    preg_match_all('/<a\s+href="([^"]*\/episode\/[^"]*)"[^>]*>(.*?)<\/a>/si', $searchHtml, $matches);
    echo "Episode matches: " . count($matches[1]) . "\n";
    for ($i = 0; $i < min(5, count($matches[1])); $i++) {
        echo "  " . urldecode($matches[1][$i]) . "\n";
    }
}
