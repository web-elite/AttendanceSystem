<?php
// Seed a leave request for test_emp (correct column names: start_at, end_at)
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$pdo->prepare("INSERT INTO leave_requests (user_id, type, start_at, end_at, reason, status) VALUES (?, 'daily', ?, ?, ?, 'pending')")
    ->execute([99991, '2026-12-01 09:00:00', '2026-12-02 17:00:00', 'test leave']);
echo "leave inserted: " . $pdo->lastInsertId() . "\n";
$pdo->exec("DELETE FROM rate_limits");
echo "rate limits cleared\n";
