<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Core\View;

$svg = View::icon('dashboard');
echo 'svg starts ok: ' . (str_starts_with($svg, '<svg') ? 'yes' : 'no') . PHP_EOL;
echo 'class escaped: ' . (str_contains($svg, 'w-5 h-5') ? 'yes' : 'no') . PHP_EOL;

$names = ['dashboard','users','clock','calendar','chart','check','x','logout','login','edit','plus','download','pin','qr','warning','info','settings','trend-up','trend-down','file','filter','power','moon','sun'];
foreach ($names as $n) {
    $s = View::icon($n);
    if (!str_starts_with($s, '<svg') || !str_ends_with($s, '</svg>')) {
        echo "FAIL icon: $n" . PHP_EOL;
        exit(1);
    }
}
echo 'all 24 icons render ok' . PHP_EOL;

echo 'unknown icon falls back: ' . (str_contains(View::icon('nonexistent'), '<svg') ? 'yes' : 'no') . PHP_EOL;
echo 'avatar: ' . View::avatar('علی رضایی') . PHP_EOL;
echo 'jalali: ' . View::jalali(time()) . PHP_EOL;
echo 'fa: ' . View::fa('1403/07/06') . PHP_EOL;
echo 'ALL OK' . PHP_EOL;
