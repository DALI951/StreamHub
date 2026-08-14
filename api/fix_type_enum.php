<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../src/Database.php';

echo "Altering cache_metadata.type ENUM...\n";
Database::query("ALTER TABLE cache_metadata MODIFY COLUMN type ENUM('movie', 'series', 'season', 'episode', 'anime') DEFAULT 'movie'");
echo "Done.\n";

$rows = Database::fetchAll("SHOW COLUMNS FROM cache_metadata WHERE Field = 'type'");
echo "Definition: " . json_encode($rows) . "\n";
