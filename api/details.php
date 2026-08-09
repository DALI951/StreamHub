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
    echo json_encode(['error' => 'Missing url parameter']);
    exit;
}

$manager = new SourceManager();
$scraper = $manager->detectSource($url);

if (!$scraper) {
    $sourceParam = $_GET['source'] ?? null;
    if ($sourceParam) {
        $scraper = $manager->getScraper($sourceParam);
    }
}

if (!$scraper) {
    echo json_encode(['error' => 'Could not detect source from URL. Pass ?source= parameter.']);
    exit;
}

$details = $scraper->getDetails($url);
if (!$details) {
    echo json_encode(['error' => 'Failed to fetch details']);
    exit;
}

echo json_encode(['details' => $details]);
