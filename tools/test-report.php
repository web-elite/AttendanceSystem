<?php
// tools/test-report.php — runtime test for ReportService (Jalali month, working days, hours, late/early).
// Usage: php -d error_reporting=24575 tools/test-report.php
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\ReportService;

date_default_timezone_set('Asia/Tehran');
$pdo = Database::conn();

// Ensure settings exist (seed defaults).
$pdo->exec("DELETE FROM settings WHERE key IN ('min_in_time','max_out_time','grace_minutes','holiday_mode')");
$pdo->exec("INSERT INTO settings (key, value) VALUES
  ('min_in_time','09:00'),
  ('max_out_time','17:00'),
  ('grace_minutes','0'),
  ('holiday_mode','friday')");

// Clean a test user's punches.
$uid = 99991;
$pdo->exec("DELETE FROM punches WHERE user_id = {$uid}");
$pdo->exec("INSERT INTO users (id,name,username,password_hash,role,is_active) VALUES
  ({$uid},'کارمند تست','test_emp','x','employee',1)
  ON CONFLICT(id) DO UPDATE SET name='کارمند تست', username='test_emp', role='employee'");

// Punches for a known weekday spread (Mon..Thu), including late & early.
// Today is some weekday; use explicit dates relative to now.
$mon = date('Y-m-d', strtotime('monday this week'));
$add = function (string $day, string $t, string $type, int $est = 0) use ($uid, $pdo) {
    $punchedAt = date('Y-m-d H:i:s', strtotime($day . ' ' . $t . ':00'));
    $pdo->exec("INSERT INTO punches (id, user_id, type, method, punched_at, is_estimate)
                VALUES (ABS(RANDOM()), {$uid}, '{$type}', 'qrcode', '{$punchedAt}', {$est})");
};
// Mon: on time
$add($mon, '08:55', 'in');
$add($mon, '17:05', 'out');
// Tue: late (in at 09:30)
$add(date('Y-m-d', strtotime($mon . ' +1 day')), '09:30', 'in');
$add(date('Y-m-d', strtotime($mon . ' +1 day')), '17:05', 'out');
// Wed: early out (out at 15:00)
$add(date('Y-m-d', strtotime($mon . ' +2 day')), '08:50', 'in');
$add(date('Y-m-d', strtotime($mon . ' +2 day')), '15:00', 'out');
// Fri: should be EXCLUDED (Friday off)
$add(date('Y-m-d', strtotime('friday this week')), '08:55', 'in');
$add(date('Y-m-d', strtotime('friday this week')), '17:05', 'out');

$from = date('Y-m-01');            // 1st of current month
$to   = date('Y-m-t');            // last day of current month
$report = ReportService::generate($from, $to, (string) $uid);

echo "Range: {$report['from']} .. {$report['to']}\n";
echo "Totals: days={$report['totals']['total_days']} hours=" . $report['totals']['total_hours']
    . " late={$report['totals']['total_late']} early={$report['totals']['total_early']}\n";
echo "Rows: " . count($report['rows']) . " (expect 3 — Mon/Tue/Wed; Fri excluded)\n\n";
foreach ($report['rows'] as $r) {
    printf(
        "  %s  %s in=%s out=%s h=%.2f late=%d early=%d est=%d\n",
        $r['user_name'], $r['jalali_day'], $r['first_in'], $r['last_out'],
        $r['hours'], $r['late'] ? 1 : 0, $r['early'] ? 1 : 0, $r['estimated']
    );
}

// Assertions.
$ok = true;
if (count($report['rows']) !== 3) { $ok = false; echo "FAIL: expected 3 rows, got " . count($report['rows']) . "\n"; }
$byDay = [];
foreach ($report['rows'] as $r) $byDay[$r['day']] = $r;
if (isset($byDay[date('Y-m-d', strtotime('friday this week'))])) { $ok = false; echo "FAIL: Friday row present\n"; }
if (!($report['rows'][1]['late'] ?? false)) { $ok = false; echo "FAIL: Tue should be late\n"; }
if (!($report['rows'][2]['early'] ?? false)) { $ok = false; echo "FAIL: Wed should be early\n"; }
// Jalali display sanity
foreach ($report['rows'] as $r) {
    if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $r['jalali_day'])) { $ok = false; echo "FAIL: jalali_day format: {$r['jalali_day']}\n"; }
}

// Default range (empty from/to) should resolve to current Jalali month.
$defaultReport = ReportService::generate('', '', (string) $uid);
echo "\nDefault range: {$defaultReport['from']} .. {$defaultReport['to']} (current Jalali month)\n";

// toCsv should not throw and include the header.
$csv = ReportService::toCsv($report);
if (strpos($csv, 'کارمند') === false) { $ok = false; echo "FAIL: CSV missing header\n"; }

echo $ok ? "\nALL PASS\n" : "\nFAILURES PRESENT\n";
exit($ok ? 0 : 1);
