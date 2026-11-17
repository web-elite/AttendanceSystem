<?php
// Check what's in rate_limits
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$rows = $pdo->query("SELECT action, ip, created_at, COUNT(*) as cnt FROM rate_limits GROUP BY action, ip")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "{$r['action']} | {$r['ip']} | {$r['created_at']} | x{$r['cnt']}\n";
}
echo "total: " . $pdo->query("SELECT COUNT(*) FROM rate_limits")->fetchColumn() . "\n";
