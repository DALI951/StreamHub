<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: text/plain');

require_once __DIR__ . '/../src/Database.php';

Database::query("DELETE FROM search_cache");
Database::query("DELETE FROM cache_metadata");
Database::query("DELETE FROM cache_streams");

echo "All caches cleared.\n";
