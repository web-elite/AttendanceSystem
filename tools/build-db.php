<?php
/**
 * Runs all SQL migrations in order and optionally seeds the database.
 *
 * Usage:
 *   php tools/build-db.php            # run migrations
 *   php tools/build-db.php --seed     # run migrations + seed
 */

$root  = dirname(__DIR__);
$dir   = $root . '/database/migrations';
$dbDir = $root . '/database';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$pdo = new PDO('sqlite:' . $dbDir . '/app.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL;');
$pdo->exec('PRAGMA busy_timeout=5000;');
$pdo->exec('PRAGMA foreign_keys=ON;');

// Create a meta table to track which migrations have been applied
$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    name TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
)");

$applied = array_map(
    fn($r) => $r['name'],
    $pdo->query("SELECT name FROM _migrations")->fetchAll(PDO::FETCH_ASSOC)
);

$files = glob($dir . '/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    $pdo->prepare("INSERT INTO _migrations (name) VALUES (?)")->execute([$name]);
    echo "✅ $name\n";
    $count++;
}

if ($count === 0) {
    echo "ℹ️  No new migrations to apply.\n";
} else {
    echo "✅ $count migration(s) applied.\n";
}

// Seed
if (in_array('--seed', $argv, true)) {
    echo "\nSeeding...\n";
    require $root . '/database/seed.php';
}
