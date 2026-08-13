<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';

$query = trim($_GET['q'] ?? '');
$source = $_GET['source'] ?? null;

if (empty($query)) {
    echo json_encode(['error' => 'Missing query parameter q', 'results' => []]);
    exit;
}

$manager = new SourceManager();

$cached = Cache::getSearch($query, $source ?? '');
if ($cached) {
    echo json_encode(['results' => $cached, 'cached' => true]);
    exit;
}

if ($source) {
    $scraper = $manager->resolveSource($source);
    if ($scraper) {
        $results = $scraper->search($query);
    } else {
        echo json_encode(['error' => "Unknown source: {$source}", 'results' => []]);
        exit;
    }
} else {
    $results = $manager->searchAll($query);
}

$seen = [];
$unique = [];
foreach ($results as $r) {
    if ($r['type'] === 'episode') continue;
    $key = strtolower(trim($r['title']));
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $unique[] = $r;
    }
}

Cache::setSearch($query, $unique, null, $source ?? '');
echo json_encode(['results' => $unique, 'cached' => false]);
