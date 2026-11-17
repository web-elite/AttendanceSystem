<?php
/**
 * Cron: close forgotten check-outs.
 *
 * Finds users whose LAST punch of a finished day was an "in" (no matching "out")
 * and, when the admin policy is `auto_close`, inserts an estimated "out" punch
 * stamped at 23:59:59 of that day with is_estimate = 1.
 *
 * Run daily (e.g. 00:05 Asia/Tehran) from cron:
 *   5 0 * * * php /var/www/crm/tools/close-forgotten-checkouts.php >> storage/logs/cron.log 2>&1
 *
 * Rules honored (AGENTS.md / PROJECT_BRAIN.md):
 * - Policy check is server-side: `forgotten_checkout` setting must be `auto_close`.
 * - Estimated punch: method = 'manual', is_estimate = 1, note marks it as auto-closed.
 * - Only days strictly BEFORE today are considered (today is still open).
 * - Idempotent: re-running never duplicates (an estimated out already counts as the last punch).
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

// Cron bypasses public/index.php, so set the app timezone explicitly.
$appConfig = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Tehran');

$policy = \App\Core\Config::str('forgotten_checkout', 'auto_close');
if ($policy !== 'auto_close') {
    echo date('c') . " policy=admin_manual → nothing to do\n";
    exit(0);
}

$pdo = Database::conn();
$today = date('Y-m-d'); // Asia/Tehran, passed to SQL as a bound param (deterministic)
$closed = 0;

// Last punch of each (user, day) pair for any day before today.
// Grouping per day (not per user) closes EVERY forgotten day, not just the latest.
$sql = "SELECT DISTINCT p.id, p.user_id, p.type, p.punched_at, p.note
        FROM punches p
        INNER JOIN (
            SELECT user_id, date(punched_at) AS day, MAX(punched_at) AS max_at
            FROM punches
            WHERE date(punched_at) < :today
            GROUP BY user_id, date(punched_at)
        ) last ON last.user_id = p.user_id AND last.max_at = p.punched_at
        WHERE p.type = 'in'";

$stmt = $pdo->prepare($sql);
$stmt->execute([':today' => $today]);
$forgotten = $stmt->fetchAll();

$insert = $pdo->prepare(
    "INSERT INTO punches (user_id, type, method, punched_at, is_estimate, note)
     VALUES (?, 'out', 'manual', ?, 1, ?)"
);

foreach ($forgotten as $f) {
    $day = substr($f['punched_at'], 0, 10); // YYYY-MM-DD of the last in-punch of that day
    // Guard: never touch today, never close a day twice (note check)
    if ($day >= $today) {
        continue;
    }
    if (str_contains((string) $f['note'], 'خروج خودکار')) {
        continue;
    }
    // Stamp 23:59:59 of that day, Tehran time
    $ts = strtotime("$day 23:59:59");
    $insert->execute([
        (int) $f['user_id'],
        date('Y-m-d H:i:s', $ts),
        'خروج خودکار (فراموش‌شده)',
    ]);
    $closed++;
    echo date('c') . " closed user #{$f['user_id']} for $day\n";
}

echo date('c') . " done, closed=$closed\n";
