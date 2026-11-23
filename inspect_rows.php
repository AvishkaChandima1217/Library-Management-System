<?php
require __DIR__ . '/db.php';
$pdo = db();

foreach (['libraries', 'users', 'news', 'books', 'loans', 'audit_logs'] as $t) {
    echo "=== $t ===" . PHP_EOL;
    print_r($pdo->query("SELECT * FROM $t")->fetchAll());
}
