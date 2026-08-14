<?php
header('Content-Type: text/plain; charset=utf-8');
$pass = 'waFS6FtEt5Qm1H94!#';
$attempts = [
    ['localhost', 'modali', $pass],
    ['localhost', 'modali_streamhub', $pass],
    ['localhost', 'modali_admin', $pass],
    ['127.0.0.1', 'modali', $pass],
];
foreach ($attempts as [$host, $user, $pw]) {
    echo "Testing {$user}@{$host}...\n";
    try {
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        echo "CONNECTED!\n";
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Databases: " . implode(', ', $dbs) . "\n";
        echo "WORKING: host={$host} user={$user}\n";
        break;
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n\n";
    }
}
