<?php
require_once __DIR__ . '/../config/database.php';

$tables = ['attendance', 'car_photos', 'cars', 'customers', 'extra_work', 'form_submissions', 'salaries', 'users', 'work_history'];

foreach ($tables as $t) {
    echo "=== TABLE: {$t} ===\n";
    $cols = $pdo->query("DESCRIBE `{$t}`")->fetchAll();
    foreach ($cols as $c) {
        echo "  - {$c['Field']}: {$c['Type']} " . ($c['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . " (Default: " . var_export($c['Default'], true) . ")\n";
    }
    echo "\n";
}
