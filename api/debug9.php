<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$slug = 'مسلسل-the-mentalist-اموسم-الثاني-مترجم-كامل';
echo "Original: $slug\n";

$normalizedSlug = str_replace(['اموسم', 'اموسم-'], ['الموسم', 'الموسم-'], $slug);
echo "Normalized: $normalizedSlug\n";

if (preg_match('/(الموسم-[^\-]+(?:-[^\-]+)*)/', $normalizedSlug, $sm)) {
    echo "Season part: {$sm[1]}\n";
} else {
    echo "NO season part match\n";
}

// Test with actual episode slug from search
$epSlug1 = 'مسلسل-the-mentalist-الموسم-الثاني-الحلقة-5';
$epSlug2 = 'مسلسل-the-mentalist-الموسم-الأول-الحلقة-5';
echo "\nEp1 contains seasonPart: " . (str_contains($epSlug1, $sm[1] ?? '') ? 'YES' : 'NO') . "\n";
echo "Ep2 contains seasonPart: " . (str_contains($epSlug2, $sm[1] ?? '') ? 'YES' : 'NO') . "\n";

// Test the search
$searchQuery = 'مسلسل the mentalist الموسم الثاني';
$searchUrl = 'https://tv10.egydead.live/?s=' . urlencode($searchQuery);
$html = file_get_contents($searchUrl);
echo "\nHTML length: " . strlen($html) . "\n";

preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $html, $matches);
echo "movieItem: " . count($matches[1]) . "\n";

$englishName = 'the-mentalist';
$count = 0;
for ($i = 0; $i < count($matches[1]); $i++) {
    $epUrl = urldecode($matches[1][$i]);
    $epPath = parse_url($epUrl, PHP_URL_PATH);
    $epSlug = basename(rtrim($epPath, '/'));
    if (!str_contains($epSlug, 'حلقه') && !str_contains($epSlug, 'الحلقة') && !preg_match('/s\d+e\d+/i', $epSlug)) continue;
    if (!str_contains(strtolower($epSlug), $englishName)) continue;
    $seasonMatch = str_contains($epSlug, $sm[1] ?? 'ZZZZZ');
    if (!$seasonMatch) continue;
    $count++;
    if ($count <= 5) echo "OK: $epSlug\n";
}
echo "Total with season filter: $count\n";

// Without season filter
$count2 = 0;
for ($i = 0; $i < count($matches[1]); $i++) {
    $epUrl = urldecode($matches[1][$i]);
    $epPath = parse_url($epUrl, PHP_URL_PATH);
    $epSlug = basename(rtrim($epPath, '/'));
    if (!str_contains($epSlug, 'حلقه') && !str_contains($epSlug, 'الحلقة') && !preg_match('/s\d+e\d+/i', $epSlug)) continue;
    if (!str_contains(strtolower($epSlug), $englishName)) continue;
    $count2++;
}
echo "Total without season filter: $count2\n";
