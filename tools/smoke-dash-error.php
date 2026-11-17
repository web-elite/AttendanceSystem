<?php
// Check employee dashboard for errors
$ch = curl_init();
$jar = __DIR__ . '/cookie.txt';
curl_setopt_array($ch, [
    CURLOPT_URL => "http://127.0.0.1:8099/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Find the error
if (preg_match('/(?:Fatal|Warning|Deprecated|Uncaught|Error):(.{0,300})/is', $html, $m)) {
    echo "ERROR FOUND: " . trim($m[0]) . "\n";
} else {
    echo "No error pattern found in " . strlen($html) . " bytes\n";
}
