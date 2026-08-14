<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$seasonUrl = "https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%ab%d8%a7%d9%86%d9%8a-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/";
$path = parse_url($seasonUrl, PHP_URL_PATH);
$slug = urldecode(basename(rtrim($path, '/')));
echo "Slug: $slug\n\n";

if (preg_match('/^(مسلسل-.+?)-(.+?موسم.+?)-(مترجم|مدبلج)-(كامل|.+)$/', $slug, $m)) {
    echo "MATCHED!\n";
    $seriesPart = $m[1];
    $seasonPart = $m[2];
    echo "Series: $seriesPart\n";
    echo "Season: $seasonPart\n\n";
    
    $seasonClean = preg_replace('/.*موسم/', 'الموسم', $seasonPart);
    $searchQuery = str_replace(['مسلسل-', 'مسلسل ', '-'], ' ', $seriesPart) . ' ' . str_replace(['-', ' '], ' ', $seasonClean);
    $searchQuery = preg_replace('/\s+/', ' ', trim($searchQuery));
    echo "Query: $searchQuery\n\n";
    
    $searchUrl = 'https://tv10.egydead.live/?s=' . urlencode($searchQuery);
    $html = file_get_contents($searchUrl);
    
    preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $html, $matches);
    echo "Total movieItems: " . count($matches[1]) . "\n\n";
    
    $filtered = 0;
    $seriesFiltered = 0;
    for ($i = 0; $i < count($matches[1]); $i++) {
        $epUrl = urldecode($matches[1][$i]);
        $epPath = parse_url($epUrl, PHP_URL_PATH);
        $epSlug = basename(rtrim($epPath, '/'));
        
        // Check episode filter
        $isEp = str_contains($epSlug, 'حلقه') || str_contains($epSlug, 'الحلقة') || preg_match('/s\d+e\d+/i', $epSlug);
        if (!$isEp) continue;
        $filtered++;
        
        // Check series filter
        $isSeries = str_starts_with($epSlug, $seriesPart);
        if (!$isSeries) {
            echo "EXCLUDED (wrong series): $epSlug\n";
            continue;
        }
        $seriesFiltered++;
        if ($seriesFiltered <= 5) {
            echo "OK: $epSlug\n";
        }
    }
    echo "\nAfter episode filter: $filtered\n";
    echo "After series filter: $seriesFiltered\n";
} else {
    echo "Regex did NOT match\n";
}
