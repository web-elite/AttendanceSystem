<?php
// Employee POST flows: /punch + /leave-requests
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$pdo->exec("DELETE FROM rate_limits");

$base = 'http://127.0.0.1:8099';
$jar = __DIR__ . '/emp_cookie2.txt';
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
echo "CSRF: " . substr($csrf, 0, 16) . "...\n";

// Step 2: POST /login (test_emp)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['username' => 'test_emp', 'password' => 'test12345', '_token' => $csrf]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
echo "POST /login → " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
curl_close($ch);

// Step 3: POST /punch (type=in, method=manual)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/punch",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['type' => 'in', 'method' => 'manual', '_token' => $csrf]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
echo "POST /punch → {$code}, Location: {$loc}\n";
curl_close($ch);

// Verify punch in DB
$count = $pdo->query("SELECT COUNT(*) as c FROM punches WHERE user_id = 99991")->fetch()['c'];
echo "Punches for user 99991: {$count}\n";

// Step 4: POST /leave-requests (type=daily)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/leave-requests",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'type' => 'daily',
        'start_at' => '2027-03-01 09:00:00',
        'end_at' => '2027-03-02 17:00:00',
        'reason' => 'employee self-test leave',
        '_token' => $csrf,
    ]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
echo "POST /leave-requests → {$code}, Location: {$loc}\n";
curl_close($ch);

// Verify leave in DB
$row = $pdo->query("SELECT id, status, reason FROM leave_requests WHERE reason = 'employee self-test leave'")->fetch(PDO::FETCH_ASSOC);
echo "New leave: " . ($row ? "id={$row['id']} status={$row['status']}" : "NOT FOUND") . "\n";
