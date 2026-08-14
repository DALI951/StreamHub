<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$manager = new SourceManager();
$scraper = $manager->detectSource('https://tv10.egydead.live/season/test');
echo "Scraper: " . ($scraper ? $scraper->getSourceName() : 'null') . "\n\n";

$url = 'https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%b3%d8%a7%d8%a8%d8%b9-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/';
echo "Testing getDetails for season...\n";

$details = $scraper->getDetails($url);
if ($details) {
    echo "Title: {$details['title']}\n";
    echo "Type: {$details['type']}\n";
    echo "Episodes: " . count($details['episodes']) . "\n";
    foreach (array_slice($details['episodes'], 0, 3) as $ep) {
        echo "  Ep {$ep['number']}: {$ep['url']}\n";
    }
} else {
    echo "getDetails returned null\n";
}
