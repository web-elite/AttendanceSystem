<?php
// Test close-forgotten-checkouts.php with a synthetic forgotten in-punch.
require __DIR__ . '/../vendor/autoload.php';

$pdo = App\Core\Database::conn();
$uid = (int) ($pdo->query("SELECT id FROM users WHERE role = 'employee' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn());
echo "test user: #$uid\n";

// 1) show current policy
echo "policy: " . App\Core\Config::str('forgotten_checkout', '(unset)') . "\n";

// 2) seed TWO consecutive forgotten days for the same user (multi-day gap case)
$day1 = date('Y-m-d', strtotime('-4 days'));
$day2 = date('Y-m-d', strtotime('-3 days'));
$pdo->prepare("DELETE FROM punches WHERE note LIKE '%TEST-FORGOTTEN%'")->execute();
$ins = $pdo->prepare("INSERT INTO punches (user_id, type, method, punched_at, note) VALUES (?, 'in', 'manual', ?, 'TEST-FORGOTTEN in')");
$ins->execute([$uid, "$day1 09:00:00"]);
$pid1 = (int) $pdo->lastInsertId();
$ins->execute([$uid, "$day2 08:30:00"]);
$pid2 = (int) $pdo->lastInsertId();
echo "seeded forgotten in-punches #$pid1 ($day1) and #$pid2 ($day2)\n";

// 3) run the closer (include, capture output)
ob_start();
include __DIR__ . '/close-forgotten-checkouts.php';
$out = ob_get_clean();
echo "--- closer output ---\n$out----------------------\n";

// 4) verify: an estimated out at 23:59:59 for EACH day, note contains خروج خودکار
$closedCount = 0;
foreach ([$day1, $day2] as $d) {
    $q = $pdo->prepare("SELECT id, type, punched_at, is_estimate, note FROM punches WHERE user_id = ? AND date(punched_at) = ? ORDER BY punched_at");
    $q->execute([$uid, $d]);
    foreach ($q->fetchAll() as $r) {
        echo "  $d #{$r['id']} {$r['type']} @ {$r['punched_at']} est={$r['is_estimate']} note={$r['note']}\n";
        if ($r['type'] === 'out' && (int) $r['is_estimate'] === 1 && str_ends_with($r['punched_at'], '23:59:59')) {
            $closedCount++;
        }
    }
}
echo "closed days: $closedCount (expect 2)\n";

// 5) idempotency: run again → closed=0
ob_start();
include __DIR__ . '/close-forgotten-checkouts.php';
$out2 = ob_get_clean();
preg_match('/closed=(\d+)/', $out2, $m);
echo "second run closed=" . ($m[1] ?? '?') . " (expect 0)\n";

// 6) cleanup test rows (both days)
$del = $pdo->prepare("DELETE FROM punches WHERE user_id = ? AND date(punched_at) = ?");
$del->execute([$uid, $day1]);
$del->execute([$uid, $day2]);
echo "test rows cleaned\n";
