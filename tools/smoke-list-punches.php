<?php
use App\Core\Database;
require dirname(__DIR__) . '/vendor/autoload.php';
$pdo = Database::conn();
$r = $pdo->query('SELECT id, user_id, type, punched_at FROM punches ORDER BY id');
foreach ($r as $row) {
    echo $row['id'] . ' | ' . $row['user_id'] . ' | ' . $row['type'] . ' | ' . $row['punched_at'] . PHP_EOL;
}
