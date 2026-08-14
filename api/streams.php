<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$url = trim($_GET['url'] ?? '');

if (empty($url)) {
    echo json_encode(['error' => 'Missing url parameter', 'streams' => []]);
    exit;
}

$manager = new SourceManager();
$scraper = $manager->detectSource($url);

if (!$scraper) {
    $sourceParam = $_GET['source'] ?? null;
    if ($sourceParam) {
        $scraper = $manager->resolveSource($sourceParam);
    }
}

if (!$scraper) {
    echo json_encode(['error' => 'Could not detect source', 'streams' => []]);
    exit;
}

// ?fresh=1 bypasses the DB cache: signed CDN tokens go stale fast, so when
// probing finds dead URLs we re-scrape to mint fresh ones.
if (isset($_GET['fresh']) && $_GET['fresh'] === '1') {
    Database::query(
        "DELETE FROM cache_streams WHERE content_url = ? AND source = ?",
        [$url, $scraper->getSourceName()]
    );
}

$streams = $scraper->getStreams($url);
echo json_encode(['streams' => $streams]);
