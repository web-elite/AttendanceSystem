<?php
// Final admin leaves test with cookie debugging
$base = 'http://127.0.0.1:8099';
$jar = __DIR__ . '/admin_cookie2.txt';
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
echo "Step 1: GET /login → " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "Cookie after GET: " . @file_get_contents($jar) . "\n";

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
$setCookies = [];
if (preg_match_all('/Set-Cookie:\s*(.+)/i', $headers, $scm)) {
    $setCookies = $scm[1];
}
$loc = '';
if (preg_match('/Location:\s*(.+)/i', $headers, $m)) $loc = trim($m[1]);
curl_close($ch);
echo "Step 2: POST /login → {$code}, Location: {$loc}\n";
echo "Set-Cookie headers: " . (empty($setCookies) ? "none" : implode(' | ', $setCookies)) . "\n";
echo "Cookie file: " . @file_get_contents($jar) . "\n";

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
echo "Step 3: GET /admin/leaves → {$code}, Location: {$loc}\n";
if ($code === 200) {
    echo "Has 'leave' in HTML: " . (str_contains($body, 'leave') ? "YES" : "NO") . "\n";
    echo "Has 'test leave' in HTML: " . (str_contains($body, 'test leave') ? "YES" : "NO") . "\n";
}
