<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../src/Database.php';

echo "Clearing ALL cache...\n";
Database::query("DELETE FROM cache_metadata");
Database::query("DELETE FROM cache_streams");
Database::query("DELETE FROM search_cache");
echo "Done. All cache cleared.\n";

// Verify
$r1 = Database::fetchOne("SELECT COUNT(*) as c FROM cache_metadata");
$r2 = Database::fetchOne("SELECT COUNT(*) as c FROM cache_streams");
$r3 = Database::fetchOne("SELECT COUNT(*) as c FROM search_cache");
echo "metadata: {$r1['c']}, streams: {$r2['c']}, search: {$r3['c']}\n";
