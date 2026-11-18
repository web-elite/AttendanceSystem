<?php
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
foreach ($pdo->query("SELECT key, value FROM settings WHERE key IN ('methods','method_mode','geofence_enabled','qr_mode','qr_static_token','office_lat','office_lng','radius_m')") as $r) {
    echo $r['key'], ' = ', $r['value'], PHP_EOL;
}
