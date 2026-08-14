<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

// Simulate the search query that findEpisodesForSeason would build
$searchQuery = "the mentalist الموسم الثاني";
$searchUrl = "https://tv10.egydead.live/?s=" . urlencode($searchQuery);
echo "Search URL: $searchUrl\n\n";

$html = file_get_contents($searchUrl);
echo "HTML length: " . strlen($html) . "\n";

// Show all movieItem entries with their URLs
preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $html, $matches);
echo "movieItem count: " . count($matches[1]) . "\n\n";

for ($i = 0; $i < min(10, count($matches[1])); $i++) {
    $url = $matches[1][$i];
    $text = strip_tags(html_entity_decode($matches[2][$i]));
    $text = preg_replace('/\s+/', ' ', trim($text));
    echo "URL: $url\n";
    echo "Text: $text\n\n";
}

// Also try searching by the Arabic series name
echo "\n=== Search by Arabic name ===\n";
$searchUrl2 = "https://tv10.egydead.live/?s=" . urlencode("the mentalist");
echo "Search URL: $searchUrl2\n\n";
$html2 = file_get_contents($searchUrl2);
preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $html2, $m2);
echo "movieItem count: " . count($m2[1]) . "\n\n";
for ($i = 0; $i < min(10, count($m2[1])); $i++) {
    $url = $m2[1][$i];
    $text = strip_tags(html_entity_decode($m2[2][$i]));
    $text = preg_replace('/\s+/', ' ', trim($text));
    echo "URL: $url\n";
    echo "Text: $text\n\n";
}
