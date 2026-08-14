<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== MySQL Connection Test ===\n\n";

$attempts = [
    ['host' => 'localhost', 'user' => 'modali', 'pass' => 'Hp9conDIhfVuBtxY'],
    ['host' => '127.0.0.1', 'user' => 'modali', 'pass' => 'Hp9conDIhfVuBtxY'],
    ['host' => '212.227.215.235', 'user' => 'modali', 'pass' => 'Hp9conDIhfVuBtxY'],
    ['host' => 'localhost', 'user' => 'modali_streamhub', 'pass' => 'Hp9conDIhfVuBtxY'],
    ['host' => 'localhost', 'user' => 'modali_stream', 'pass' => 'Hp9conDIhfVuBtxY'],
    ['host' => 'localhost', 'user' => 'modali', 'pass' => ''],
];

foreach ($attempts as $i => $cfg) {
    $dsn = "mysql:host={$cfg['host']};charset=utf8mb4";
    echo "Attempt {$i}: host={$cfg['host']} user={$cfg['user']} pass=" . substr($cfg['pass'], 0, 3) . "***\n";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        echo "  -> CONNECTED!\n";
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "  -> Databases: " . implode(', ', $dbs) . "\n";

        // Try creating the database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS streamhub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "  -> Database streamhub_db created!\n";

        // Save working credentials
        echo "\n=== WORKING CREDENTIALS ===\n";
        echo "host={$cfg['host']}\n";
        echo "user={$cfg['user']}\n";
        echo "pass={$cfg['pass']}\n";
        break;
    } catch (PDOException $e) {
        echo "  -> FAILED: " . $e->getMessage() . "\n";
    }
}
