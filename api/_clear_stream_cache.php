<?php
require_once __DIR__ . '/../src/Database.php';
Database::query("DELETE FROM cache_streams WHERE content_url LIKE '%egydead.live/episode/%'");
echo 'cleared';