<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

// Clear cache for season URL
$seasonUrl = 'https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%a7%d9%88%d9%84-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/';
$decodedUrl = urldecode($seasonUrl);

echo "=== Clearing cache ===\n";
Database::query("DELETE FROM cache_metadata WHERE url = ? OR url = ?", [$seasonUrl, $decodedUrl]);
Database::query("DELETE FROM cache_streams WHERE content_url = ? OR content_url = ?", [$seasonUrl, $decodedUrl]);
echo "Cache cleared.\n\n";

// Now call getDetails fresh
$manager = new SourceManager();
$scraper = $manager->detectSource($decodedUrl);
if (!$scraper) {
    echo "ERROR: No scraper detected\n";
    exit;
}

echo "=== Fresh getDetails call ===\n";
$details = $scraper->getDetails($decodedUrl);

echo "Type: " . ($details['type'] ?? 'N/A') . "\n";
echo "Title: " . ($details['title'] ?? 'N/A') . "\n";
echo "Episodes count: " . count($details['episodes'] ?? []) . "\n";
echo "Seasons count: " . count($details['seasons'] ?? []) . "\n";

if (!empty($details['episodes'])) {
    echo "\nFirst 3 episodes:\n";
    foreach (array_slice($details['episodes'], 0, 3) as $ep) {
        echo "  #{$ep['number']}: {$ep['title']} => {$ep['url']}\n";
    }
}
