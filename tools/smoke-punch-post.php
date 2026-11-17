<?php
// Smoke: employee punch POST (manual type) — expects 302 PRG redirect + flash on dashboard.
$base = 'http://127.0.0.1:8099';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_COOKIEFILE => '',
    CURLOPT_COOKIESESSION => true,
    CURLOPT_FOLLOWLOCATION => false,
]);

// login
curl_setopt($ch, CURLOPT_POSTFIELDS, null);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_URL, "$base/login");
ob_start(); curl_exec($ch); $html = ob_get_clean();
preg_match('/name="_token"\s*value="([^"]+)"/s', $html, $m);
curl_setopt($ch, CURLOPT_URL, "$base/login");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => 'test_emp', 'password' => 'test12345', '_token' => $m[1]]));
ob_start(); curl_exec($ch); ob_get_clean();

// dashboard → fresh token + method field names
curl_setopt($ch, CURLOPT_POSTFIELDS, null);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_URL, "$base/dashboard");
ob_start(); curl_exec($ch); $dash = ob_get_clean();
preg_match('/name="_token"\s*value="([^"]+)"/s', $dash, $m2);
echo "dashboard token: " . (isset($m2[1]) ? 'found' : 'MISSING') . "\n";

// find the punch form action
preg_match('/<form[^>]*action="([^"]*)"[^>]*>/', $dash, $fa);
echo "form action: " . ($fa[1] ?? 'NOT FOUND') . "\n";
// find select options for method
preg_match_all('/<option value="([^"]+)"/', $dash, $opts);
echo "method options: " . implode('|', $opts[1]) . "\n";
// find select name attrs inside punch form
preg_match('/<form[^>]*>(.*?)<\/form>/s', $dash, $ff);
if (isset($ff[1])) {
    preg_match_all('/name="([^"]+)"/', $ff[1], $names);
    echo "form fields: " . implode('|', array_unique($names[1])) . "\n";
}

// POST /punch: manual in-punch with note
curl_setopt($ch, CURLOPT_URL, "$base/punch");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'type' => 'in', 'method' => 'manual', 'note' => 'smoke-test punch', '_token' => $m2[1],
]));
ob_start(); curl_exec($ch); ob_get_clean();
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loc  = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
echo "POST /punch: HTTP $code → $loc\n";

// follow redirect → expect 200 + flash
if ($code === 302) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, null);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_URL, $loc);
    ob_start(); curl_exec($ch); $after = ob_get_clean();
    $c2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hasFlash = str_contains($after, 'alert-success') || str_contains($after, 'flash');
    echo "GET $loc: HTTP $c2, flash visible: " . ($hasFlash ? 'yes' : 'no') . "\n";
    echo "note in page: " . (str_contains($after, 'smoke-test punch') ? 'yes' : 'no') . "\n";
}
