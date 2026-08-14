<?php
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/Scraper/SourceManager.php';

echo "Loading SourceManager...\n";
try {
    $manager = new SourceManager();
    echo "SourceManager loaded OK\n";
    $scrapers = $manager->getAllScrapers();
    echo "Scrapers: " . count($scrapers) . "\n";
    foreach ($scrapers as $name => $s) {
        echo "  - {$name}: " . get_class($s) . "\n";
    }

    echo "\nTesting search on egydead...\n";
    $scraper = $manager->getScraper('egydead');
    if ($scraper) {
        $results = $scraper->search('breaking bad');
        echo "Results: " . count($results) . "\n";
        foreach (array_slice($results, 0, 3) as $r) {
            echo "  - {$r['title']} ({$r['type']})\n";
        }
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
