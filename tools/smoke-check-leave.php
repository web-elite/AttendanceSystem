<?php
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$rows = $pdo->query("SELECT id, user_id, type, start_at, end_at, reason, status FROM leave_requests")->fetchAll(PDO::FETCH_ASSOC);
echo "leave_requests: " . count($rows) . " rows\n";
foreach ($rows as $r) {
    echo "  id={$r['id']} user={$r['user_id']} type={$r['type']} status={$r['status']} reason={$r['reason']}\n";
}
