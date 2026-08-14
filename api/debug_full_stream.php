<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$testUrl = urldecode('https://tv10.egydead.live/episode/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%b3%d8%a7%d8%a8%d8%b9-%d8%a7%d9%84%d8%ad%d9%84%d9%82%d8%a9-13/');

echo "=== Full Pipeline Test ===\n";
echo "URL: $testUrl\n\n";

// Use the actual SourceManager
$manager = new SourceManager();
$scraper = $manager->detectSource($testUrl);

if (!$scraper) {
    echo "FATAL: No scraper detected\n";
    exit;
}

echo "Scraper: " . get_class($scraper) . "\n\n";

// Clear cache for this URL
Database::query("DELETE FROM cache_streams WHERE content_url = ?", [$testUrl]);
Database::query("DELETE FROM cache_metadata WHERE url = ?", [$testUrl]);

// Call getStreams directly
echo "--- Calling getStreams ---\n";
$streams = $scraper->getStreams($testUrl);

echo "\nStreams returned: " . count($streams) . "\n";
foreach ($streams as $i => $stream) {
    echo "\nStream $i:\n";
    echo "  URL: " . ($stream['stream_url'] ?? 'N/A') . "\n";
    echo "  Type: " . ($stream['stream_type'] ?? 'N/A') . "\n";
    echo "  Quality: " . ($stream['quality_label'] ?? 'N/A') . "\n";
    echo "  Server: " . ($stream['server_name'] ?? 'N/A') . "\n";
}

// Now test the actual API endpoint
echo "\n\n=== Testing streams.php API ===\n";
$_GET['url'] = $testUrl;
ob_start();
include __DIR__ . '/../api/streams.php';
$output = ob_get_clean();
echo $output;
