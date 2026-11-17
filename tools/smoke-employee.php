<?php
// Smoke test: simulate employee login + dashboard + leave-requests via HTTP
$base = 'http://127.0.0.1:8099';

// Step 1: GET /login → extract CSRF + session cookie
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => __DIR__ . '/cookie.txt',
    CURLOPT_COOKIEFILE => __DIR__ . '/cookie.txt',
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$csrf = '';
preg_match('/name="_token" value="([^"]+)"/', $html, $m);
if ($m) $csrf = $m[1];
echo "GET /login → $code, CSRF: " . substr($csrf, 0, 16) . "...\n";
curl_close($ch);

// Step 2: POST /login as test_emp
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['username' => 'test_emp', 'password' => 'test12345', '_token' => $csrf]),
    CURLOPT_COOKIEFILE => __DIR__ . '/cookie.txt',
    CURLOPT_COOKIEJAR => __DIR__ . '/cookie.txt',
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loc = curl_getinfo($ch, CURLINFO_HEADER_OUT);
preg_match('/Location:\s*(.+)/i', $loc, $lm);
echo "POST /login (test_emp) → $code, Location: " . trim($lm[1] ?? '?') . "\n";
curl_close($ch);

// Step 3: GET / employee dashboard
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => __DIR__ . '/cookie.txt',
    CURLOPT_COOKIEJAR => __DIR__ . '/cookie.txt',
    CURLOPT_FOLLOWLOCATION => false,
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$fatal = preg_match('/Fatal|Uncaught|Warning|Deprecated/', $html);
$title = '';
preg_match('/<title>(.*?)<\/title>/s', $html, $tm);
if ($tm) $title = $tm[1];
echo "GET / → $code | title: $title | fatal: " . ($fatal ? "YES" : "no") . "\n";
curl_close($ch);

// Step 4: GET /leave-requests
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$base/leave-requests",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => __DIR__ . '/cookie.txt',
    CURLOPT_COOKIEJAR => __DIR__ . '/cookie.txt',
    CURLOPT_FOLLOWLOCATION => false,
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$fatal = preg_match('/Fatal|Uncaught/', $html);
echo "GET /leave-requests → $code | fatal: " . ($fatal ? "YES" : "no") . "\n";
curl_close($ch);
