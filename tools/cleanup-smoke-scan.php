<?php
// Cleanup: remove punches created by smoke-qr-scan.php (note = 'اسکن دوربین')
$pdo = new PDO('sqlite:' . __DIR__ . '/../database/app.sqlite');
$pdo->exec("DELETE FROM punches WHERE note = 'اسکن دوربین'");
$pdo->exec("DELETE FROM qr_tokens");
$pdo->exec("DELETE FROM rate_limits");
$n = $pdo->query("SELECT COUNT(*) c FROM punches")->fetch()['c'];
echo "cleanup done; punches left: $n\n";
