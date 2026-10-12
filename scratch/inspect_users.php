<?php
require_once __DIR__ . '/../config/database.php';

$cols = $pdo->query("DESCRIBE users")->fetchAll();
echo "=== USERS COLUMNS ===\n";
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

$users = $pdo->query("SELECT id, employee_id, username, name, role, status FROM users")->fetchAll();
echo "\n=== USERS ===\n";
foreach ($users as $u) {
    echo "ID: {$u['id']} | Emp: {$u['employee_id']} | User: {$u['username']} | Name: {$u['name']} | Role: {$u['role']} | Status: {$u['status']}\n";
}
