<?php
// Debug: check admin auth flow
$base = 'http://127.0.0.1:8099';
$jar = __DIR__ . '/admin_cookie.txt';
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

// Check what cookies we got
echo "Cookie file: " . (file_exists($jar) ? file_get_contents($jar) : "NOT FOUND") . "\n";

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
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$setCookie = '';
if (preg_match_all('/Set-Cookie:\s*(.+)/i', $headers, $scm)) {
    $setCookie = implode(' | ', $scm[1]);
}
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
curl_close($ch);
echo "POST /login → {$code}\n";
echo "Location: {$loc}\n";
echo "Set-Cookie: {$setCookie}\n";

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
$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
curl_close($ch);
echo "GET /admin/leaves → {$code}, Location: {$loc}\n";
