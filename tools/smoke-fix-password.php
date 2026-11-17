<?php
// Set test_emp password to a known value
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$hash = password_hash('test12345', PASSWORD_ARGON2ID);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'test_emp'")->execute([$hash]);
echo "password updated, verify: " . (password_verify('test12345', $hash) ? "OK" : "FAIL") . "\n";
// Also clear rate limits
$pdo->exec("DELETE FROM rate_limits");
echo "rate limits cleared\n";
