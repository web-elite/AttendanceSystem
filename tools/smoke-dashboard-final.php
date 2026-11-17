<?php
// Smoke: login both roles, fetch every screen, report status + signature strings.
$base = 'http://127.0.0.1:8099';

function http_get($ch, $url) {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, null); // clear first...
    curl_setopt($ch, CURLOPT_HTTPGET, true);    // ...then force GET (POSTFIELDS alone implies POST)
    ob_start();
    $ok  = curl_exec($ch);
    $out = ob_get_clean();
    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $out, $ok];
}
function http_post($ch, $url, $data) {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    ob_start();
    $ok  = curl_exec($ch);
    $out = ob_get_clean();
    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $out, $ok];
}

function login($base, $user, $pass) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false, // we capture via ob
        CURLOPT_COOKIEFILE => '',        // in-memory cookie jar
        CURLOPT_COOKIESESSION => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    [, $html] = http_get($ch, "$base/login");
    if (!preg_match('/name="_token"\s*value="([^"]+)"/s', $html, $m)) {
        return [null, 'no _token on /login'];
    }
    [$code] = http_post($ch, "$base/login", [
        'username' => $user, 'password' => $pass, '_token' => $m[1],
    ]);
    if ($code !== 302) {
        return [null, "login POST returned $code"];
    }
    return [$ch, 'ok'];
}

$checks = [
    '/admin/dashboard' => ['وضعیت حضور پرسنل', 'space-y-6', 'data-clock', 'bar-grow'],
    '/admin/users'     => ['مدیریت کاربران', 'table-zebra', 'avatar-circle'],
    '/admin/punches'   => ['سوابق پانچ', 'table-zebra', 'anim-fade-up'],
    '/admin/leaves'    => ['درخواست‌های مرخصی', 'btn-success'],
    '/admin/reports'   => ['گزارش‌های حضور و غیاب', 'table-sm'],
    '/admin/settings'  => ['تنظیمات سیستم', 'anim-fade-up'],
    '/admin/users/create' => ['کاربر جدید', 'btn-primary'],
];

echo "== ADMIN ==\n";
[$ch, $err] = login($base, 'admin', 'admin1234');
if (!$ch) { echo "LOGIN FAIL: $err\n"; exit(1); }
echo "login: OK\n";
foreach ($checks as $path => $needles) {
    [$code, $html] = http_get($ch, $base . $path);
    $status = $code === 200 ? '200' : "HTTP $code";
    $missing = [];
    foreach ($needles as $n) {
        if (!str_contains($html, $n)) { $missing[] = $n; }
    }
    $flag = $missing ? '  MISSING: ' . implode(',', $missing) : '';
    echo "$path : $status$flag\n";
}

$empChecks = [
    '/dashboard'       => ['ثبت پانچ', 'space-y-6', 'data-punch-method', 'bar-grow', 'data-clock'],
    '/leave-requests'  => ['درخواست مرخصی', 'table-zebra', 'درخواست جدید'],
];

echo "== EMPLOYEE ==\n";
[$ch, $err] = login($base, 'test_emp', 'test12345');
if (!$ch) { echo "LOGIN FAIL: $err\n"; exit(1); }
echo "login: OK\n";
foreach ($empChecks as $path => $needles) {
    [$code, $html] = http_get($ch, $base . $path);
    $status = $code === 200 ? '200' : "HTTP $code";
    $missing = [];
    foreach ($needles as $n) {
        if (!str_contains($html, $n)) { $missing[] = $n; }
    }
    $flag = $missing ? '  MISSING: ' . implode(',', $missing) : '';
    echo "$path : $status$flag\n";
}

// CSS asset check
$css = file_get_contents("$base/assets/app.css");
echo "app.css: " . (strlen($css) > 10000 ? strlen($css) . ' bytes' : 'TOO SMALL') . "\n";
foreach (['side-link', 'anim-fade-up', 'bar-grow', 'avatar-circle', 'sidebar-active'] as $c) {
    echo "  .$c: " . (str_contains($css, $c) ? 'present' : 'ABSENT') . "\n";
}
