<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$query = trim($_GET['q'] ?? '');
echo "Query: {$query}\n";

if (empty($query)) {
    echo "Empty query\n";
    exit;
}

echo "Creating SourceManager...\n";
$manager = new SourceManager();
echo "Searching...\n";

$results = $manager->searchAll($query);
echo "Found " . count($results) . " results\n";

foreach (array_slice($results, 0, 3) as $r) {
    echo "- [{$r['type']}] {$r['title']}\n";
}
