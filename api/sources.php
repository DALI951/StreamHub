<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/../config.php';
$sources = [];
foreach ($config['sources'] as $name => $info) {
    $sources[] = [
        'name'     => $name,
        'base_url' => $info['base'],
        'priority' => $info['priority'],
        'enabled'  => true,
    ];
}
usort($sources, fn($a, $b) => $a['priority'] <=> $b['priority']);
echo json_encode(['sources' => $sources]);
