<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "=== StreamHub Search Test ===\n\n";

require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/Scraper/SourceManager.php';

echo "1. Creating SourceManager...\n";
try {
    $sm = new SourceManager();
    echo "   OK - " . count($sm->getAllScrapers()) . " scrapers loaded\n";
    foreach ($sm->getAllScrapers() as $name => $s) {
        echo "   - {$name}: (priority {$s->priority})\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit;
}

echo "\n2. Testing egydead search for 'breaking bad'...\n";
try {
    $egydead = $sm->getScraper('egydead');
    if (!$egydead) {
        echo "   egydead scraper not found!\n";
        exit;
    }
    $results = $egydead->search('breaking bad');
    echo "   Found " . count($results) . " results\n";
    foreach (array_slice($results, 0, 5) as $r) {
        echo "   - [{$r['type']}] {$r['title']}\n     URL: {$r['url']}\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";
