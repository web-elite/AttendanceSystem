<?php
declare(strict_types=1);

$db = new PDO('sqlite:' . __DIR__ . '/../database/app.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $db->query("SELECT id, user_id, type, status, start_at, end_at, reason FROM leave_requests WHERE reason = 'jdp e2e test'")->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "NO ROWS FOUND\n";
    exit(1);
}
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE), PHP_EOL;
}
// Gregorian assertion: start_at must match YYYY-MM-DD HH:MM:SS and month must be 11 (November), not 08 (Jalali month)
$r = $rows[0];
$gregorian = (bool) preg_match('/^\d{4}-11-\d{2} \d{2}:\d{2}:\d{2}$/', $r['start_at']);
echo $gregorian ? "GREGORIAN OK\n" : "GREGORIAN MISMATCH\n";
