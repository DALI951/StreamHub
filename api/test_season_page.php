<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/BaseScraper.php';
require_once __DIR__ . '/../src/Scraper/EgyDeadScraper.php';

$scraper = new EgyDeadScraper();
$url = 'https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%b3%d8%a7%d8%a8%d8%b9-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/';

$html = $scraper->fetch($url);
if (!$html) {
    echo "FETCH FAILED\n";
    exit;
}

echo "HTML length: " . strlen($html) . "\n\n";

echo "=== Looking for episodes-list ===\n";
if (preg_match('/episodes-list/i', $html)) {
    echo "FOUND episodes-list\n";
} else {
    echo "NOT FOUND\n";
}

echo "\n=== Looking for episode links ===\n";
preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/si', $html, $allLinks);
$epLinks = [];
for ($i = 0; $i < count($allLinks[0]); $i++) {
    $href = $allLinks[1][$i];
    $text = strip_tags($allLinks[2][$i]);
    if (preg_match('#/episode/#i', $href)) {
        $epLinks[] = ['url' => $href, 'text' => $text];
    }
}
echo "Episode links found: " . count($epLinks) . "\n";
foreach (array_slice($epLinks, 0, 5) as $ep) {
    echo "  {$ep['text']} -> {$ep['url']}\n";
}

echo "\n=== Looking for حلقة ===\n";
if (preg_match('/حلقة/i', $html)) {
    echo "FOUND حلقة\n";
    preg_match_all('/<a\s+href="([^"]+)"[^>]*>.*?حلقة.*?(\d+).*?<\/a>/ui', $html, $epMatches);
    echo "Matches: " . count($epMatches[1]) . "\n";
} else {
    echo "NOT FOUND\n";
}
