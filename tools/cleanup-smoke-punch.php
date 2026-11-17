<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../database/app.sqlite');
$n = $pdo->exec("DELETE FROM punches WHERE note = 'smoke-test punch'");
echo "removed: $n\n";
