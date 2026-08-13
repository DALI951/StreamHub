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
$sourceParam = $_GET['source'] ?? '';
$slug = $_GET['slug'] ?? '';

if (empty($url) && !empty($sourceParam) && !empty($slug)) {
    $manager = new SourceManager();
    $scraper = $manager->resolveSource($sourceParam);
    if ($scraper) {
        $baseUrl = $scraper->getBaseUrl();
        $url = rtrim($baseUrl, '/') . '/' . ltrim(urldecode($slug), '/');
    }
}

if (empty($url)) {
    echo json_encode(['error' => 'Missing url or source+slug parameter']);
    exit;
}

$manager = new SourceManager();
$scraper = $manager->detectSource($url);

if (!$scraper && !empty($sourceParam)) {
    $scraper = $manager->resolveSource($sourceParam);
}

if (!$scraper) {
    echo json_encode(['error' => 'Could not detect source from URL. Pass ?source= parameter.']);
    exit;
}

$details = $scraper->getDetails($url);
if (!$details) {
    echo json_encode([
        'error' => 'Failed to fetch details',
        'debug' => [
            'source'   => $scraper->getSourceName(),
            'base_url' => $scraper->getBaseUrl(),
            'url'      => $url,
        ],
    ]);
    exit;
}

echo json_encode(['details' => $details]);
