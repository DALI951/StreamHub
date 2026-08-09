<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$user = 'modali';
$pass = 'waFS6FtEt5Qm1H94!#';

try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("USE modalidb");
    echo "[OK] Using database modalidb\n";

    $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    $ok = 0;
    $errors = 0;
    foreach ($statements as $sql) {
        $sql = trim($sql);
        if (empty($sql) || str_starts_with($sql, '--')) continue;
        try {
            $pdo->exec($sql);
            $ok++;
        } catch (PDOException $e) {
            echo "Note: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "[OK] {$ok} statements executed, {$errors} notes\n";

    $count = $pdo->query("SELECT COUNT(*) FROM sources")->fetchColumn();
    echo "[OK] Sources table: {$count} entries\n";

    echo "\n=== Database setup complete ===\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
