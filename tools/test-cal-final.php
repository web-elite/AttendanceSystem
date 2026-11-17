<?php
error_reporting(0);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\CalendarService as C;

function check(string $label, $got, $want): void {
    $ok = $got === $want ? 'PASS' : 'FAIL';
    echo "[$ok] $label\n";
    if ($ok === 'FAIL') {
        echo "  got:  " . json_encode($got) . "\n";
        echo "  want: " . json_encode($want) . "\n";
    }
}

// 2026-03-21 = today = 1405/1/1 Jalali
check('G2026-03-21 → J', C::gregorianToJalali(2026,3,21), ['jalali_year'=>1405,'jalali_month'=>1,'jalali_day'=>1]);
check('J1405-1-1 → G', C::jalaliToGregorian(1405,1,1), ['gregorian_year'=>2026,'gregorian_month'=>3,'gregorian_day'=>21]);
// Civil 33-year rule (per PROJECT_BRAIN, matches Iranian civil calendar; NOT ICU/MDA):
//   nowruzG: J1379/1/1 = 2000-03-21  →  2000-03-20 is the LAST day of 1378 = 1378/12/29 (1378 not a civil leap year).
//   1381 IS a civil leap year (month12=30), 1382/1/1 = 2003-03-22 → 2003-03-20 = 1381/12/29.
check('G2000-03-20 → J', C::gregorianToJalali(2000,3,20), ['jalali_year'=>1378,'jalali_month'=>12,'jalali_day'=>29]);
check('J1378-12-29 → G', C::jalaliToGregorian(1378,12,29), ['gregorian_year'=>2000,'gregorian_month'=>3,'gregorian_day'=>20]);
check('G1997-03-21 → J', C::gregorianToJalali(1997,3,21), ['jalali_year'=>1376,'jalali_month'=>1,'jalali_day'=>1]);
check('G1996-03-21 → J', C::gregorianToJalali(1996,3,21), ['jalali_year'=>1375,'jalali_month'=>1,'jalali_day'=>1]);
check('G2003-03-20 → J', C::gregorianToJalali(2003,3,20), ['jalali_year'=>1381,'jalali_month'=>12,'jalali_day'=>29]);
// Round trips
$g = C::gregorianToJalali(2026,3,21);
$r = C::jalaliToGregorian($g['jalali_year'], $g['jalali_month'], $g['jalali_day']);
check('roundtrip G→J→G 2026-03-21', $r, ['gregorian_year'=>2026,'gregorian_month'=>3,'gregorian_day'=>21]);

// parseJalaliDate
$ts = C::parseJalaliDate('1405-01-01');
echo "parseJalaliDate('1405-01-01') ts=" . $ts . " formatted=" . gmdate('Y-m-d', $ts) . " (UTC) / " . date('Y-m-d', $ts) . " (server)\n";
// 1405/1/1 should be 2026-03-21
echo "formatJalali of that ts: " . C::formatJalali($ts) . "\n";
