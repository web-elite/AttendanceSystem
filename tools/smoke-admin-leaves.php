<?php
// Full admin test: login + approve/reject leave
$base = 'http://127.0.0.1:8099';
$jar = __DIR__ . '/admin_cookie.txt';
@unlink($jar);

function http_get(string $url, string $jar): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    $loc = '';
    if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
    curl_close($ch);
    return ['code' => $code, 'html' => $html, 'location' => $loc];
}

// 1. GET /login
$r = http_get("$base/login", $jar);
$csrf = '';
preg_match('/name="_token" value="([^"]+)"/', $r['html'], $m);
if ($m) $csrf = $m[1];
echo "GET /login → {$r['code']}\n";

// 2. POST /login as admin
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
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
curl_close($ch);
echo "POST /login (admin) → {$code}, Location: {$loc}\n";

// 3. GET /admin/leaves
$r = http_get("$base/admin/leaves", $jar);
$err = preg_match('/Fatal|Uncaught/', $r['html']) ? 'HAS ERROR' : 'clean';
$showLeave = preg_match('/leave|تقاضا|pending/i', $r['html']) ? 'SHOWS LEAVE' : 'no leave visible';
echo "GET /admin/leaves → {$r['code']} | {$err} | {$showLeave}\n";

// 4. POST approve leave (id 1)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/admin/leaves/1/approve",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['_token' => $csrf]),
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
curl_close($ch);
echo "POST /admin/leaves/1/approve → {$code}, Location: {$loc}\n";
