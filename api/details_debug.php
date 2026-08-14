<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$url = 'https://tv10.egydead.live/episode/breaking-bad-s05e01/';
echo "URL: {$url}\n";

$manager = new SourceManager();
$scraper = $manager->detectSource($url);
echo "Scraper: " . ($scraper ? get_class($scraper) : 'NONE') . "\n";

if ($scraper) {
    echo "Fetching details...\n";
    $details = $scraper->getDetails($url);
    if ($details) {
        echo "Title: {$details['title']}\n";
        echo "Type: {$details['type']}\n";
        echo "Poster: {$details['poster']}\n";
    } else {
        echo "No details returned\n";
    }
}
