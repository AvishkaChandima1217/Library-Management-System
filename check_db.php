<?php
require __DIR__ . '/db.php';
$pdo = db();

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "DB File: " . DB_FILE . PHP_EOL;
echo "Tables: " . implode(', ', $tables) . PHP_EOL . PHP_EOL;

foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM " . $t)->fetchColumn();
    echo "$t -> $count rows" . PHP_EOL;
}