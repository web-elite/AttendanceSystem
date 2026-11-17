<?php
/**
 * Seed script — creates the initial admin user and default settings.
 * Run after build-db.php:  php database/seed.php
 */

use App\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Database::conn();

// ── Default admin user ─────────────────────────────────────
$adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ((int) $adminExists === 0) {
    $passwordHash = password_hash('admin1234', PASSWORD_ARGON2ID);
    $pdo->prepare(
        "INSERT INTO users (name, username, password_hash, role, is_active, created_at)
         VALUES (?, ?, ?, 'admin', 1, datetime('now'))"
    )->execute(['مدیر سیستم', 'admin', $passwordHash]);
    echo "✅ Initial admin created: admin / admin1234\n";
    echo "⚠️  Change this password immediately in production.\n";
} else {
    echo "ℹ️  Admin user already exists — skipping seed.\n";
}

// ── Default settings ───────────────────────────────────────
$defaults = [
    'min_in_time'        => '09:00',
    'max_out_time'       => '17:00',
    'grace_minutes'      => '0',
    'geofence_enabled'   => '0',
    'office_lat'         => '',
    'office_lng'         => '',
    'radius_m'           => '150',
    'methods'            => '["manual","location","qrcode"]',
    'method_mode'        => 'combined',
    'qr_mode'            => 'rotating',
    'qr_static_token'    => '',
    'qr_ttl_seconds'     => '60',
    'forgotten_checkout' => 'auto_close',
    'holiday_mode'       => 'friday',
];

foreach ($defaults as $key => $value) {
    $pdo->prepare(
        "INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO NOTHING"
    )->execute([$key, $value]);
}

echo "✅ Default settings applied.\n";
echo "Done.\n";
