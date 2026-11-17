<?php
error_reporting(0);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\CalendarService as C;

// 1) Round-trip every day from J1404/11/1 through J1405/2/1 (spans nowruz 1405/1/1) and verify G→J→G == G.
$start = C::jalaliToGregorian(1404,11,1);
$end   = C::jalaliToGregorian(1405,2,1);
$bad = 0;
for ($ts = mktime(12,0,0,$start['gregorian_month'],$start['gregorian_day'],$start['gregorian_year']);
     $ts <= mktime(12,0,0,$end['gregorian_month'],$end['gregorian_day'],$end['gregorian_year']);
     $ts += 86400) {
    $g = C::gregorianToJalali( (int)date('Y',$ts), (int)date('n',$ts), (int)date('j',$ts) );
    $g2 = C::jalaliToGregorian($g['jalali_year'],$g['jalali_month'],$g['jalali_day']);
    if ($g2['gregorian_year']!==(int)date('Y',$ts) || $g2['gregorian_month']!==(int)date('n',$ts) || $g2['gregorian_day']!==(int)date('j',$ts)) {
        if ($bad++ < 10) echo "MISMATCH " . date('Y-m-d',$ts) . " -> J" . $g['jalali_year']."/".$g['jalali_month']."/".$g['jalali_day'] . " -> G" . sprintf('%04d-%02d-%02d',$g2['gregorian_year'],$g2['gregorian_month'],$g2['gregorian_day']) . "\n";
    }
}
echo "roundtrip sweep 1404/11/1..1405/2/1 : " . ($bad ? "$bad mismatches" : "OK (all days round-trip)") . "\n";

// 2) Month-length consistency for all 12 months of 1405 vs 1404 (leap vs common).
foreach ([1404,1405] as $y) {
    $lens = [];
    for ($m=1;$m<=12;$m++) $lens[] = C::jalaliMonthLength($y,$m);
    echo "J$y month lengths: [" . implode(',',$lens) . "] total=" . array_sum($lens) . "\n";
}

// 3) parseJalaliDate + formatJalali round trip.
$ts = C::parseJalaliDate('1405-01-01');
echo "parseJalaliDate('1405-01-01') -> formatJalali = " . C::formatJalali($ts) . " (expect 1405/01/01)\n";
exit(0);
