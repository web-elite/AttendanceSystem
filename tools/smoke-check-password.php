<?php
// Debug: verify test_emp password
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$row = $pdo->query("SELECT username, password_hash, is_active FROM users WHERE username = 'test_emp'")->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "test_emp not found\n"; exit; }
echo "username: {$row['username']}\n";
echo "is_active: {$row['is_active']}\n";
echo "hash: " . substr($row['password_hash'], 0, 30) . "...\n";
echo "verify: " . (password_verify('test12345', $row['password_hash']) ? "OK" : "FAIL") . "\n";
