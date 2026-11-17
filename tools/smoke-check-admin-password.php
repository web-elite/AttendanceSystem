<?php
// Check admin password
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$row = $pdo->query("SELECT username, password_hash, is_active FROM users WHERE username = 'admin'")->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "admin not found\n"; exit; }
echo "admin is_active: {$row['is_active']}\n";
echo "verify admin12345: " . (password_verify('admin12345', $row['password_hash']) ? "OK" : "FAIL") . "\n";
echo "verify admin1234:  " . (password_verify('admin1234', $row['password_hash']) ? "OK" : "FAIL") . "\n";
