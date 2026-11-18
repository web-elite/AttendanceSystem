<?php
// Smoke: punch-method + geofence enforcement (admin has methods=["qrcode"],
// method_mode=single, geofence on at 36.32259/59.525861 r=150, qr static).
// Run with dev server up:  php -S 127.0.0.1:8099 -t public
$base = 'http://127.0.0.1:8099';
$pass = 0; $fail = 0;
function check(string $label, bool $cond, string $extra = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS  $label\n"; }
    else        { $fail++; echo "FAIL  $label" . ($extra !== '' ? "  — $extra" : '') . "\n"; }
}

function req(string $method, string $path, array $headers = [], ?string $body = null, ?string $jar = null, int $max = 8, bool $follow = true): array
{
    global $base;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow ? $max : 0);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? http_build_query([]));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $hdrs = substr((string)$raw, 0, $hsize);
    $body = substr((string)$raw, $hsize);
    curl_close($ch);
    return [$code, $hdrs, $body];
}
$jar = tempnam(sys_get_temp_dir(), 'cookie');

// 1) Login as test employee — prime the session (CSRF token is per-session,
//    so the first POST to /login must re-fetch the form first).
[, , $page] = req('GET', '/login', [], null, $jar, 1, false);
if (!preg_match('/name="_token" value="([^"]+)"/', $page, $m)) {
    fwrite(STDERR, "no CSRF token on /login — page:\n" . substr($page, 0, 600) . "\n");
    exit(1);
}
$csrf = $m[1];
[$code, , $body] = req('POST', '/login', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['_token' => $csrf, 'username' => 'test_emp', 'password' => 'test12345']), $jar);
check('login ok', in_array($code, [200, 302], true) && !str_contains($body, '419'), "code=$code body=" . substr($body, 0, 200));

function lastFlash(string $kind): string
{
    global $jar;
    [, , $body] = req('GET', '/', [], null, $jar, 1, false);
    // Layout renders flash toasts as <div class="alert alert-…" data-flash role="status">
    preg_match_all('/<div[^>]*data-flash[^>]*>(.*?)<\/div>/s', $body, $mm);
    foreach ($mm[1] as $blk) {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($blk)));
        if ($text !== '' && stripos($text, $kind) !== false) {
            return $text;
        }
    }
    return '';
}

function refreshCsrf(): string
{
    global $jar;
    [, , $body] = req('GET', '/', [], null, $jar, 1, false);
    if (!preg_match('/name="_token" value="([^"]+)"/', $body, $m)) {
        // The form may be on / (employee dashboard) when logged in
        if (!preg_match('/<input\s+type="hidden"\s+name="_token"\s+value="([^"]+)"/s', $body, $m)) {
            fwrite(STDERR, "no CSRF token found on / — page:\n" . substr($body, 0, 800) . "\n");
            exit(1);
        }
    }
    return $m[1];
}

// 2) qrcode with EMPTY token → must be rejected
$csrf = refreshCsrf();
req('POST', '/punch', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['_token' => $csrf, 'method' => 'qrcode', 'type' => 'in', 'qr_token' => '']), $jar);
$msg = lastFlash('QR');
check('empty qr_token rejected', $msg !== '', "msg=[$msg]");

// 3) Invalid static token → rejected
$csrf = refreshCsrf();
req('POST', '/punch', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['_token' => $csrf, 'method' => 'qrcode', 'type' => 'in', 'qr_token' => 'wrong-token']), $jar);
$msg = lastFlash('QR');
check('bad qr_token rejected', $msg !== '', "msg=[$msg]");

// 4) valid static token + coords OUTSIDE fence (home far away) → rejected by geofence
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database/app.sqlite');
$static = (string) $pdo->query("SELECT value FROM settings WHERE key='qr_static_token'")->fetchColumn();
check('static token configured', $static !== '', 'qr_static_token empty in settings');

$csrf = refreshCsrf();
req('POST', '/punch', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['_token' => $csrf, 'method' => 'qrcode', 'type' => 'in',
        'qr_token' => $static, 'lat' => '35.0', 'lng' => '50.0']), $jar);
$msg = lastFlash('محدوده');
check('outside geofence rejected', $msg !== '', "msg=[$msg]");

// 5) valid static token + coords INSIDE fence → success
$csrf = refreshCsrf();
req('POST', '/punch', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['_token' => $csrf, 'method' => 'qrcode', 'type' => 'in',
        'qr_token' => $static, 'lat' => '36.32259', 'lng' => '59.525861']), $jar);
$msg = lastFlash('ورود');
check('inside geofence success', $msg !== '', "msg=[$msg]");

// 6) manual method (not in allowed methods) → rejected
$csrf = refreshCsrf();
req('POST', '/punch', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['_token' => $csrf, 'method' => 'manual', 'type' => 'in']), $jar);
$msg = lastFlash('غیرفعال');
check('manual method disabled by admin rejected', $msg !== '', "msg=[$msg]");

// Cleanup: remove the punch we created in step 5
$pdo->exec("DELETE FROM punches WHERE user_id = 99991 AND note IS NULL AND date(punched_at) = date('now','localtime') AND method='qrcode'");

echo "\n$pass passed, $fail failed\n";
unlink($jar);
exit($fail === 0 ? 0 : 1);
