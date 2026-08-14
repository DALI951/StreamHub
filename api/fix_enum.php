<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';

echo "Altering cache_streams.stream_type ENUM to add 'iframe'...\n";
try {
    Database::query("ALTER TABLE cache_streams MODIFY COLUMN stream_type ENUM('hls', 'mp4', 'direct', 'iframe') DEFAULT 'hls'");
    echo "SUCCESS!\n";
    
    // Verify
    $rows = Database::fetchAll("SHOW COLUMNS FROM cache_streams WHERE Field = 'stream_type'");
    echo "Current definition: " . json_encode($rows) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
