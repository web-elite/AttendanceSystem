<?php
/**
 * Idempotent smoke-test seed: creates a real admin so we can log in to
 * the admin reports/users/punches/leaves pages. Safe to re-run.
 */
use App\Core\Database;
require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Database::conn();
$exists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
if ($exists === 0) {
    $pdo->prepare("INSERT INTO users (name, username, password_hash, role, is_active, created_at)
                   VALUES ('مدیر سیستم', 'admin', ?, 'admin', 1, datetime('now'))")
        ->execute([password_hash('admin1234', PASSWORD_ARGON2ID)]);
    echo "✅ admin created (admin / admin1234)\n";
} else {
    echo "ℹ️  admin already present — skipped.\n";
}
