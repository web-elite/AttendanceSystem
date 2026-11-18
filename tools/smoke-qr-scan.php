<?php
// Smoke: QR camera-scan flow (URL-based QR → /scan route).
// Scenario A: logged-in employee scans → punch auto-created (in then out).
// Scenario B: guest scans → login → punch auto-created after login.
// Scenario C: rotating token reuse → rejected.
$base = 'http://127.0.0.1:8099';

function newClient(): CurlHandle
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_COOKIESESSION => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    return $ch;
}

function get(CurlHandle $ch, string $url): string
{
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    $body = curl_exec($ch);
    return is_string($body) ? $body : '';
}

function post(CurlHandle $ch, string $url, array $fields): string
{
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_URL, $url);
    $body = curl_exec($ch);
    curl_setopt($ch, CURLOPT_POST, false);
    return is_string($body) ? $body : '';
}

function login(CurlHandle $ch, string $base, string $user, string $pass): void
{
    $html = get($ch, "$base/login");
    preg_match('/name="_token"\s*value="([^"]+)"/s', $html, $m);
    post($ch, "$base/login", ['username' => $user, 'password' => $pass, '_token' => $m[1] ?? '']);
}

function lastRedirect(CurlHandle $ch): string
{
    return curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: ('HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE));
}

function getLastPunch(CurlHandle $ch, string $base): string
{
    $dash = get($ch, "$base/");
    $flash = '';
    if (preg_match('/data-flash[^>]*>\s*(?:<svg.*?<\/svg>)?\s*<span>([^<]+)<\/span>/s', $dash, $fm)) {
        $flash = trim($fm[1]);
    }
    $records = substr_count($dash, 'timeline-box');
    return "records=$records flash=[$flash]";
}

function fetchScanUrl(CurlHandle $admin, string $base): string
{
    $html = get($admin, "$base/admin/qr");
    preg_match('#(/scan\?t=[^"<\s]+)#', $html, $m);
    return $m[1] ?? '';
}

// ── Admin: login + enable qrcode method via real settings POST ──────
$admin = newClient();
login($admin, $base, 'admin', 'admin1234');
$settingsHtml = get($admin, "$base/admin/settings");
preg_match('/name="_token"\s*value="([^"]+)"/s', $settingsHtml, $m2);
$settingsFields = [
    'min_in_time'  => '08:00',
    'max_out_time' => '17:00',
    'grace_minutes' => '10',
    'methods'      => ['manual', 'qrcode'],
    'method_mode'  => 'combined',
    'qr_mode'      => 'rotating',
    'qr_ttl_seconds' => '60',
    'forgotten_checkout' => 'auto_close',
    'holiday_mode' => 'friday',
    '_token'       => $m2[1] ?? '',
];
post($admin, "$base/admin/settings/update", $settingsFields);
echo "admin settings POST → " . lastRedirect($admin) . "\n";

// ── Admin: fetch a fresh rotating token from /admin/qr ──────────────
$scanPath = fetchScanUrl($admin, $base);
echo "scan URL: " . ($scanPath !== '' ? substr($scanPath, 0, 24) . '…' : 'MISSING') . "\n";
if ($scanPath === '') { echo "FAIL: no scan URL found on /admin/qr\n"; exit(1); }
$scanUrl = $base . $scanPath;

// ── Scenario A: logged-in employee scans with the phone camera ──────
$emp = newClient();
login($emp, $base, 'test_emp', 'test12345');
echo "A1 before: " . getLastPunch($emp, $base) . "\n";

get($emp, $scanUrl);
echo "A2 GET /scan → " . lastRedirect($emp) . "\n";
get($emp, "$base/");
echo "A3 after:  " . getLastPunch($emp, $base) . "\n";

// Second scan → should register the OUT
$scanPath2 = fetchScanUrl($admin, $base);
get($emp, $base . $scanPath2);
get($emp, "$base/");
echo "A4 after 2nd scan: " . getLastPunch($emp, $base) . "\n";

// ── Scenario B: guest scans (fresh token), then logs in ─────────────
$scanPath3 = fetchScanUrl($admin, $base);
$guest = newClient();
get($guest, $base . $scanPath3);
echo "B1 guest GET /scan → " . lastRedirect($guest) . "\n";
login($guest, $base, 'test_emp', 'test12345');
echo "B2 after login: " . getLastPunch($guest, $base) . "\n";

// ── Scenario C: reuse the same rotating token → rejected ────────────
get($guest, $base . $scanPath3);
$rejLoc = lastRedirect($guest);
$rejPage = get($guest, str_starts_with($rejLoc, 'http') ? $rejLoc : "$base/");
echo "C1 reuse → $rejLoc, rejected-flash: " . (str_contains($rejPage, 'معتبر نیست') ? 'yes' : 'NO') . "\n";

echo "\nDone.\n";
