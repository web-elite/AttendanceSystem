<?php
// Prime the settings to the admin config the user described
// (qrcode-only, single-method, geofence on with a static token) so the
// smoke-punch-rules.php matrix runs. Run from repo root, BEFORE the dev
// server is up (reads/writes the DB directly).
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;");

$set = [
    'methods'          => '["qrcode"]',
    'method_mode'      => 'single',
    'geofence_enabled' => '1',
    'office_lat'       => '36.32259',
    'office_lng'       => '59.525861',
    'radius_m'         => '150',
    'qr_mode'          => 'static',
];
// Generate a fresh static token if one is not already set
$stmt = $pdo->query("SELECT value FROM settings WHERE key='qr_static_token'");
$tok = $stmt->fetchColumn();
if (!$tok) {
    $set['qr_static_token'] = strtoupper(substr(hash_hmac('sha256', random_bytes(16), 'seed'), 0, 24));
}
foreach ($set as $k => $v) {
    $pdo->prepare(
        "INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value=excluded.value"
    )->execute([$k, $v]);
}
echo "Settings primed. static token: " . (isset($set['qr_static_token']) ? $set['qr_static_token'] : $tok) . PHP_EOL;
