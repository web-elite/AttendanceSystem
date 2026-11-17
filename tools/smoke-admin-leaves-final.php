<?php
// Seed a new pending leave + test admin approve/reject
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, type, start_at, end_at, reason, status) VALUES (99991, 'daily', '2027-01-10 09:00:00', '2027-01-11 17:00:00', 'new test leave', 'pending')");
$stmt->execute();
$id = $pdo->lastInsertId();
echo "Seeded leave id: {$id}\n";

// Clear rate limits
$pdo->exec("DELETE FROM rate_limits");
echo "Rate limits cleared\n";

// Test admin approve + reject via cURL
$base = 'http://127.0.0.1:8099';
$jar = __DIR__ . '/admin_cookie3.txt';
@unlink($jar);

// Step 1: GET /login
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$html = curl_exec($ch);
curl_close($ch);
$csrf = '';
preg_match('/name="_token" value="([^"]+)"/', $html, $m);
if ($m) $csrf = $m[1];

// Step 2: POST /login
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['username' => 'admin', 'password' => 'admin1234', '_token' => $csrf]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
echo "POST /login → " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
curl_close($ch);

// Step 3: GET /admin/leaves
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/admin/leaves",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "GET /admin/leaves → {$code}\n";
echo "Has 'new test leave': " . (str_contains($body, 'new test leave') ? "YES" : "NO") . "\n";
curl_close($ch);

// Step 4: POST /admin/leaves/{id}/approve
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/admin/leaves/{$id}/approve",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['_token' => $csrf]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "POST /admin/leaves/{$id}/approve → {$code}\n";
curl_close($ch);

// Verify
$row = $pdo->query("SELECT status FROM leave_requests WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
echo "DB status after approve: " . ($row['status'] ?? "not found") . "\n";

// Seed another + reject
$stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, type, start_at, end_at, reason, status) VALUES (99991, 'daily', '2027-02-01 09:00:00', '2027-02-02 17:00:00', 'reject test leave', 'pending')");
$stmt->execute();
$id2 = $pdo->lastInsertId();

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/admin/leaves/{$id2}/reject",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['_token' => $csrf]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "POST /admin/leaves/{$id2}/reject → {$code}\n";
curl_close($ch);

$row = $pdo->query("SELECT status FROM leave_requests WHERE id = {$id2}")->fetch(PDO::FETCH_ASSOC);
echo "DB status after reject: " . ($row['status'] ?? "not found") . "\n";
