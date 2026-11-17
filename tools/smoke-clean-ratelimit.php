<?php
// Clean rate limits for re-test
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$pdo->exec("DELETE FROM rate_limits");
echo "cleaned\n";
