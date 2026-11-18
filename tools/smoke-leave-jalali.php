<?php
// Smoke: LeaveController::jalaliToStore() — Jalali picker input → Gregorian storage.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use App\Controllers\LeaveController;

$m = new ReflectionMethod(LeaveController::class, 'jalaliToStore');
$m->setAccessible(true);

$cases = [
    // [input, withTime, expected]
    ['1405/08/20 09:30',    true,  '2026-11-11 09:30:00'],
    ['1405/08/20',          false, '2026-11-11 00:00:00'],
    ['1405/08/20 14:05:15', true,  '2026-11-11 14:05:15'],
    ['1405/08/20',          true,  '2026-11-11 00:00:00'], // hourly w/o time → 00:00:00
    ['1405-08-20',          false, '2026-11-11 00:00:00'], // dash separator accepted
    ['1405/13/01',          true,  null],                  // invalid month
    ['garbage',             true,  null],
    ['1405/08/20 25:00',    true,  null],                  // invalid hour
    ['',                    true,  null],
];

$fail = 0;
foreach ($cases as [$in, $wt, $exp]) {
    $got = $m->invoke(null, $in, $wt);
    $ok  = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    printf("%s  in=%-20s wt=%-5s got=%-20s exp=%s%s",
        $ok ? 'PASS' : 'FAIL', "[$in]", var_export($wt, true),
        var_export($got, true), var_export($exp, true), PHP_EOL);
}

exit($fail ? 1 : 0);
