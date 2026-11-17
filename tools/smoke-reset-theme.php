<?php
// Smoke: reset admin theme back to light (99992) and verify.
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
$pdo = Database::conn();
$pdo->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute(['light', 99992]);
echo "theme=";
echo $pdo->query('SELECT theme FROM users WHERE id = 99992')->fetchColumn();
echo PHP_EOL;
