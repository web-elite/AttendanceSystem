<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class QrService
{
    /**
     * Generates a rotating QR token.
     * Returns the raw token string for display; stores only the HMAC hash.
     */
    public static function generateRotatingToken(): string
    {
        $nonce = bin2hex(random_bytes(8));
        $ttl   = Config::int('qr_ttl_seconds', 60);
        $secret = Config::str('secret_key', 'default');
        $expiresAt = time() + $ttl;

        $raw   = $nonce . '|' . $expiresAt;
        $hash  = hash_hmac('sha256', $raw, $secret);

        $pdo = Database::conn();
        $pdo->prepare(
            "INSERT INTO qr_tokens (token_hash, nonce, issued_at, expires_at, used_at, used_by)
             VALUES (?, ?, ?, ?, NULL, NULL)"
        )->execute([
            $hash,
            $nonce,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', $expiresAt),
        ]);

        return $raw;
    }

    /**
     * Verifies a rotating token (raw string from the QR).
     * Returns the stored hash on success, or false.
     */
    public static function verifyToken(string $raw): string|false
    {
        $parts = explode('|', $raw);
        if (count($parts) !== 2) {
            return false;
        }
        [$nonce, $expiresAt] = $parts;

        if ((int) $expiresAt < time()) {
            return false;
        }

        $secret = Config::str('secret_key', 'default');
        $hash   = hash_hmac('sha256', $raw, $secret);

        $stmt = Database::query(
            "SELECT id FROM qr_tokens WHERE nonce = ? AND used_at IS NULL",
            [$nonce]
        );
        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }

        Database::query(
            "UPDATE qr_tokens SET used_at = ?, used_by = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), 'verified', $row['id']]
        );

        return $hash;
    }

    /**
     * Returns the absolute base URL used to build the QR scan links
     * (scheme + host, no trailing slash).
     *
     * Prefers the current request's host so the link always matches the
     * domain employees actually use; falls back to config app_url when
     * running from the CLI or on localhost (e.g. LAN dev needs APP_URL
     * pointing at the machine's LAN address for phone scanning).
     */
    public static function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $localHosts = ['localhost', '127.0.0.1', '[::1]', 'localhost:8080', '127.0.0.1:8080'];
        if ($host !== '' && !in_array($host, $localHosts, true)) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 80) === 443;
            return ($https ? 'https' : 'http') . '://' . $host;
        }
        $config = require dirname(__DIR__, 2) . '/config.php';
        return rtrim((string) ($config['app_url'] ?? ''), '/');
    }

    /**
     * Extracts the punch token from either a bare token string or a full
     * scanned URL ("/scan?t=..."), so pasting either one works.
     */
    public static function extractToken(string $raw): string
    {
        $raw = trim($raw);
        if ($raw !== '' && (str_contains($raw, '://') || str_starts_with($raw, '/'))) {
            $query = parse_url($raw, PHP_URL_QUERY);
            if ($query !== null && $query !== false) {
                parse_str($query, $params);
                if (!empty($params['t']) && is_string($params['t'])) {
                    return trim($params['t']);
                }
            }
        }
        return $raw;
    }

    /**
     * Verifies any punch token — rotating ("nonce|expiry") or static —
     * after URL extraction. Returns the hash to store, or false.
     */
    public static function verifyAny(string $raw): string|false
    {
        $token = self::extractToken($raw);
        if ($token === '') {
            return false;
        }

        // Rotating tokens carry the "nonce|expiry" shape
        if (str_contains($token, '|')) {
            return self::verifyToken($token);
        }

        // Static mode: compare against the stored static token
        $static = Config::str('qr_static_token', '');
        if ($static === '' || !hash_equals($static, $token)) {
            return false;
        }
        return hash_hmac('sha256', 'static|' . $token, Config::str('secret_key', 'default'));
    }

    /**
     * Generates a static QR token for the static mode.
     */
    public static function generateStaticToken(): string
    {
        $existing = Config::str('qr_static_token', '');
        if ($existing !== '') {
            return $existing;
        }
        $token = bin2hex(random_bytes(16));
        Config::set('qr_static_token', $token);
        return $token;
    }

    /**
     * Returns the QR image data URI (PNG) for a given token string.
     * Requires endroid/qr-code (installed via composer) + the GD extension.
     *
     * @return string Data URI, e.g. "data:image/png;base64,..." — or '' on failure.
     */
    public static function qrDataUri(string $content): string
    {
        if ($content === '' || !extension_loaded('gd') || !class_exists(\Endroid\QrCode\QrCode::class)) {
            return '';
        }

        try {
            $qrCode = \Endroid\QrCode\QrCode::create($content)
                ->setSize(300)
                ->setMargin(10);
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);
            $png    = $result->getString();
            return 'data:' . $result->getMimeType() . ';base64,' . base64_encode($png);
        } catch (\Throwable) {
            return '';
        }
    }
}
