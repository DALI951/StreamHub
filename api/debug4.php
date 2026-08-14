<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$manager = new SourceManager();
$scraper = $manager->getScraper('egydead');

$seasonUrl = "https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%ab%d8%a7%d9%86%d9%8a-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/";

// Simulate findEpisodesForSeason logic
$path = parse_url($seasonUrl, PHP_URL_PATH);
$slug = urldecode(basename(rtrim($path, '/')));
echo "Slug: $slug\n\n";

if (preg_match('/^(مسلسل-.+?)-(الموسم-.+?)-(مترجم|مدبلج)-(كامل|.+)$/', $slug, $m)) {
    $seriesPart = $m[1];
    $seasonPart = $m[2];
    echo "Series: $seriesPart\n";
    echo "Season: $seasonPart\n\n";
    
    $searchQuery = str_replace(['مسلسل-', 'مسلسل ', '-'], ' ', $seriesPart) . ' ' . str_replace(['الموسم-', 'الموسم '], ' ', $seasonPart);
    $searchQuery = preg_replace('/\s+/', ' ', trim($searchQuery));
    echo "Search query: $searchQuery\n\n";
    
    $searchUrl = 'https://tv10.egydead.live/?s=' . urlencode($searchQuery);
    echo "Search URL: $searchUrl\n\n";
    
    $searchHtml = file_get_contents($searchUrl);
    echo "HTML length: " . strlen($searchHtml) . "\n\n";
    
    preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $searchHtml, $matches);
    echo "movieItem matches: " . count($matches[1]) . "\n";
    
    $host = parse_url('https://tv10.egydead.live', PHP_URL_HOST);
    $episodes = [];
    for ($i = 0; $i < count($matches[1]); $i++) {
        $epUrl = $matches[1][$i];
        if (strpos($epUrl, 'http') !== 0) $epUrl = 'https://tv10.egydead.live' . $epUrl;
        $epHost = parse_url($epUrl, PHP_URL_HOST);
        if ($epHost !== $host) continue;
        $epPath = parse_url($epUrl, PHP_URL_PATH);
        $epSlug = basename(rtrim($epPath, '/'));
        if (strpos($epSlug, 'حلقه') === false && strpos($epSlug, 'الحلقة') === false && !preg_match('/s\d+e\d+/i', $epSlug)) continue;
        $text = '';
        if (preg_match('/<h1\s+class="BottomTitle">(.*?)<\/h1>/si', $matches[2][$i], $tm)) {
            $text = trim(strip_tags(html_entity_decode($tm[1])));
        }
        $epNum = 0;
        if (preg_match('/(\d+)/', $epSlug, $nm)) $epNum = (int) $nm[1];
        if ($epNum === 0 && preg_match('/(\d+)/', $text, $nm)) $epNum = (int) $nm[1];
        $episodes[] = ['number' => $epNum, 'url' => $epUrl, 'title' => $text];
    }
    
    echo "\nFiltered episodes: " . count($episodes) . "\n";
    foreach (array_slice($episodes, 0, 5) as $ep) {
        echo "  {$ep['number']}: {$ep['title']} -> {$ep['url']}\n";
    }
} else {
    echo "Regex did not match!\n";
}
