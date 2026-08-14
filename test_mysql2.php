<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== MySQL Deep Scan ===\n\n";

// Try common hosting patterns
$patterns = [
    // Common cPanel patterns
    ['localhost', 'modali_admin', 'Hp9conDIhfVuBtxY'],
    ['localhost', 'modali_user', 'Hp9conDIhfVuBtxY'],
    ['localhost', 'modali_mysql', 'Hp9conDIhfVuBtxY'],
    ['localhost', 'modali_db', 'Hp9conDIhfVuBtxY'],
    ['localhost', 'modali_powerpme', 'Hp9conDIhfVuBtxY'],
    // Maybe the DB name is the user
    ['localhost', 'modali_streamhub_db', 'Hp9conDIhfVuBtxY'],
    ['localhost', 'modali_streamhub', 'modali_streamhub'],
    ['localhost', 'modali_streamhub', 'modali'],
    // Without password
    ['localhost', 'modali_streamhub', ''],
    // Root or admin
    ['localhost', 'root', 'Hp9conDIhfVuBtxY'],
    ['localhost', 'admin', 'Hp9conDIhfVuBtxY'],
    // Try connecting without specifying database
    ['localhost', 'modali_streamhub', 'Hp9conDIhfVuBtxY'],
];

foreach ($patterns as $i => [$host, $user, $pass]) {
    $dsn = "mysql:host={$host};charset=utf8mb4";
    $showPass = $pass === '' ? '(empty)' : substr($pass, 0, 4) . '***';
    echo "[{$i}] {$user}@{$host} pass={$showPass} -> ";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        echo "CONNECTED! ";
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "DBs: " . implode(', ', $dbs) . "\n";

        // Try creating streamhub_db
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS streamhub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "  -> streamhub_db CREATED\n";
        } catch (Exception $e) {
            echo "  -> Create DB failed: " . $e->getMessage() . "\n";
        }

        echo "\n=== WORKING: user={$user} pass={$pass} host={$host} ===\n";
        break;
    } catch (PDOException $e) {
        $code = $e->getCode();
        if ($code == 1045) echo "Access denied\n";
        elseif ($code == 2002) echo "Connection refused\n";
        else echo "Error {$code}\n";
    }
}
