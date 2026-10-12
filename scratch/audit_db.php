<?php
require_once __DIR__ . '/../config/database.php';

echo "=== ENVIRONMENT ===" . PHP_EOL;
echo "PHP Version: " . PHP_VERSION . PHP_EOL;
try {
    $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL/MariaDB Version: " . $ver . PHP_EOL;
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== TABLES & ROW COUNTS ===" . PHP_EOL;
$tables = $pdo->query('SHOW TABLE STATUS')->fetchAll();
foreach ($tables as $t) {
    echo sprintf("%-25s | Engine: %-8s | Rows: %-5d | Collation: %s\n", 
        $t['Name'], $t['Engine'], $t['Rows'], $t['Collation']);
}

echo PHP_EOL . "=== FOREIGN KEYS & INDEXES ===" . PHP_EOL;
foreach ($tables as $t) {
    $tableName = $t['Name'];
    $createTable = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch();
    echo "Table {$tableName} Definition Summary:\n";
    $lines = explode("\n", $createTable['Create Table']);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, 'PRIMARY KEY') || 
            str_starts_with($trimmed, 'KEY') || 
            str_starts_with($trimmed, 'UNIQUE KEY') || 
            str_starts_with($trimmed, 'CONSTRAINT')) {
            echo "   " . $trimmed . PHP_EOL;
        }
    }
}
