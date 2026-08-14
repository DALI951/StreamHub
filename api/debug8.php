<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$slug = 'مسلسل-the-mentalist-اموسم-الثاني-مترجم-كامل';
echo "Slug: $slug\n\n";

$cleanSlug = preg_replace('/-(مترجم|مدبلج)-.*$/', '', $slug);
echo "Clean slug: $cleanSlug\n\n";

$searchQuery = str_replace('-', ' ', $cleanSlug);
$searchQuery = preg_replace('/\s+/', ' ', trim($searchQuery));
echo "Query: $searchQuery\n\n";

$searchUrl = 'https://tv10.egydead.live/?s=' . urlencode($searchUrl ?? $searchQuery);
echo "URL: $searchUrl\n\n";

$html = file_get_contents($searchUrl);
echo "HTML length: " . strlen($html) . "\n";

preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $html, $matches);
echo "movieItem: " . count($matches[1]) . "\n\n";

$englishName = '';
if (preg_match('/^ المسلسل-([a-zA-Z0-9-]+)/', $slug, $nm)) {
    $englishName = strtolower($nm[1]);
}
echo "English name: $englishName\n\n";

$count = 0;
for ($i = 0; $i < count($matches[1]); $i++) {
    $epUrl = urldecode($matches[1][$i]);
    $epPath = parse_url($epUrl, PHP_URL_PATH);
    $epSlug = basename(rtrim($epPath, '/'));
    $isEp = str_contains($epSlug, 'حلقه') || str_contains($epSlug, 'الحلقة') || preg_match('/s\d+e\d+/i', $epSlug);
    if (!$isEp) continue;
    $isMatch = !$englishName || str_contains(strtolower($epSlug), $englishName);
    if (!$isMatch) continue;
    $count++;
    if ($count <= 5) {
        echo "EP $count: $epSlug\n";
    }
}
echo "\nTotal matched: $count\n";
