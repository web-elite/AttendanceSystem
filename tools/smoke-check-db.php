<?php
/**
 * Survey the DB state before seeding: counts + a sample of recent rows.
 * Read-only — does not modify anything.
 */
use App\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Database::conn();

$tables = ['users', 'punches', 'leave_requests', 'holidays', 'settings'];
foreach ($tables as $t) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "$t: $n\n";
}

echo "\nusers sample:\n";
foreach ($pdo->query("SELECT id, name, username, role, is_active, created_at FROM users ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  #" . $r['id'] . "  " . $r['name'] . "  @" . $r['username'] . "  role=" . $r['role'] . "  active=" . $r['is_active'] . "  created=" . $r['created_at'] . "\n";
}

echo "\nrecent punches (last 10):\n";
foreach ($pdo->query("SELECT id, user_id, type, method, punched_at, is_estimate FROM punches ORDER BY punched_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  #" . $r['id'] . "  user=" . $r['user_id'] . "  " . $r['type'] . "  via " . $r['method'] . "  at " . $r['punched_at'] . "  est=" . $r['is_estimate'] . "\n";
}

echo "\nsettings:\n";
foreach ($pdo->query("SELECT key, value FROM settings ORDER BY key")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  " . $r['key'] . " = " . $r['value'] . "\n";
}

echo "\nleaves:\n";
foreach ($pdo->query("SELECT id, user_id, type, start_at, end_at, status FROM leave_requests ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  #" . $r['id'] . "  user=" . $r['user_id'] . "  " . $r['type'] . "  " . $r['start_at'] . " → " . $r['end_at'] . "  " . $r['status'] . "\n";
}

echo "\nOK\n";
