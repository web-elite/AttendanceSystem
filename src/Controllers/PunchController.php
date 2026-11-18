<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Config;
use App\Models\Punch;
use App\Core\Database;
use App\Services\QrService;
use App\Services\GeoService;

class PunchController
{
    /**
     * Handles the punch POST (in/out) from the dashboard form.
     * Method is determined by the "method" POST field:
     *   manual, location, or qrcode
     */
    public static function create(): void
    {
        $user   = Auth::user();
        $method = $_POST['method'] ?? 'manual';
        $type   = $_POST['type'] ?? '';
        $lat    = $_POST['lat'] ?? null;
        $lng    = $_POST['lng'] ?? null;
        $qrToken = $_POST['qr_token'] ?? null;

        if (!in_array($method, ['manual', 'location', 'qrcode'], true)) {
            Flash::set('error', 'روش ثبت نامعتبر است.');
            header('Location: /');
            return;
        }

        // The admin decides which methods are available — enforce it here,
        // never in the client (the employee UI only *hides* the others).
        $allowed = json_decode(Config::str('methods', '["manual"]'), true) ?: ['manual'];
        if (!in_array($method, $allowed, true)) {
            Flash::set('error', 'این روش ثبت توسط مدیر سیستم غیرفعال شده است.');
            header('Location: /');
            return;
        }

        if (!in_array($type, ['in', 'out'], true)) {
            Flash::set('error', 'نوع ثبت (ورود یا خروج) نامعتبر است.');
            header('Location: /');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        // QR verification — accepts both the bare token and a full scanned URL.
        // A "qrcode" punch WITHOUT a token is never accepted: when the admin
        // restricts punching to QR only, a bare POST must not register.
        $qrTokenHash = null;
        if ($method === 'qrcode') {
            if ($qrToken === null || trim((string) $qrToken) === '') {
                Flash::set('error', 'برای ثبت با کد QR، ابتدا کد را اسکن یا وارد کنید.');
                header('Location: /');
                return;
            }
            $verify = QrService::verifyAny((string) $qrToken);
            if ($verify === false) {
                Flash::set('error', 'توکن QR معتبر نیست یا منقضی شده است.');
                header('Location: /');
                return;
            }
            $qrTokenHash = $verify; // store the hash, not the raw token
        }

        [$ok, $error] = self::performPunch($user->id, $type, $method, $lat, $lng, $qrTokenHash, $ip, $ua, $_POST['note'] ?? null);
        if (!$ok) {
            Flash::set('error', $error);
        } else {
            Flash::set('success', $type === 'in' ? 'ورود شما ثبت شد.' : 'خروج شما ثبت شد.');
        }
        header('Location: /');
        exit;
    }

    /**
     * GET /scan?t=... — opened by any phone camera/QR app from the QR code.
     *
     * Logged-in employee  → punch is registered right away (in/out follows
     *                       today's last punch: first scan = ورود, next = خروج …).
     * Guest               → the token is verified NOW (one-time rotating tokens
     *                       are consumed before login), parked in the session,
     *                       and automatically punched right after a successful
     *                       login (login redirects to /scan?resume=1).
     */
    public static function scan(): void
    {
        $token = isset($_GET['t']) ? (string) $_GET['t'] : '';

        // The admin decides which methods are available — scanning is the
        // "qrcode" method, so it obeys the same server-side switch.
        $allowed = json_decode(Config::str('methods', '["manual"]'), true) ?: ['manual'];
        if (!in_array('qrcode', $allowed, true)) {
            Flash::set('error', 'ثبت با اسکن کد QR توسط مدیر سیستم غیرفعال شده است.');
            header('Location: ' . (Auth::check() ? '/' : '/login'));
            exit;
        }

        if ($token === '') {
            if (Auth::check()) {
                Flash::set('error', 'لینک اسکن نامعتبر است — کد QR را دوباره اسکن کنید.');
                header('Location: /');
            } else {
                Flash::set('error', 'لینک اسکن نامعتبر است — کد QR را دوباره اسکن کنید.');
                header('Location: /login');
            }
            exit;
        }

        // ── Already logged in → punch immediately ────────────────────
        if (Auth::check() && !Auth::isAdmin()) {
            $verify = QrService::verifyAny($token);
            if ($verify === false) {
                Flash::set('error', 'کد QR معتبر نیست یا قبلاً استفاده شده است.');
                header('Location: /');
                exit;
            }
            $nextType = self::nextPunchType((int) Auth::user()->id);
            [$ok, $error] = self::performPunch(
                (int) Auth::user()->id,
                $nextType,
                'qrcode',
                null,
                null,
                $verify,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'اسکن دوربین'
            );
            Flash::set($ok ? 'success' : 'error', $ok ? ($nextType === 'in' ? 'ورود شما با اسکن QR ثبت شد.' : 'خروج شما با اسکن QR ثبت شد.') : $error);
            header('Location: /');
            exit;
        }

        if (Auth::check() && Auth::isAdmin()) {
            // Admins don't punch — just send them home with a hint.
            Flash::set('info', 'ثبت ورود و خروج برای حساب مدیر فعال نیست.');
            header('Location: /');
            exit;
        }

        // ── Guest → verify + remember now, punch after login ──────────
        $verify = QrService::verifyAny($token);
        if ($verify === false) {
            Flash::set('error', 'کد QR معتبر نیست یا منقضی شده است. لطفاً کد جدید را اسکن کنید.');
            header('Location: /login');
            exit;
        }

        $_SESSION['pending_qr_hash'] = $verify;
        $_SESSION['pending_qr_ip']   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $_SESSION['pending_qr_ua']   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        // PRG: redirect to the login form (guards the token against refresh)
        header('Location: /login?scan=1');
        exit;
    }

    /**
     * Called by AuthController right after a successful login when a QR
     * scan is waiting in the session (set by scan() while guest).
     */
    public static function resumePendingScan(): void
    {
        if (Auth::check() && Auth::isAdmin()) {
            unset($_SESSION['pending_qr_hash'], $_SESSION['pending_qr_ua']);
            return;
        }
        $hash = $_SESSION['pending_qr_hash'] ?? '';
        $ip   = $_SESSION['pending_qr_ip']   ?? null;
        $ua   = $_SESSION['pending_qr_ua']   ?? null;
        unset($_SESSION['pending_qr_hash'], $_SESSION['pending_qr_ip'], $_SESSION['pending_qr_ua']);

        if ($hash === '' || !Auth::check()) {
            return;
        }

        // Same admin switch applies when the punch lands after login
        $allowed = json_decode(Config::str('methods', '["manual"]'), true) ?: ['manual'];
        if (!in_array('qrcode', $allowed, true)) {
            Flash::set('error', 'ثبت با اسکن کد QR توسط مدیر سیستم غیرفعال شده است.');
            return;
        }

        $userId = (int) Auth::user()->id;
        $nextType = self::nextPunchType($userId);
        [$ok, $error] = self::performPunch($userId, $nextType, 'qrcode', null, null, $hash, $ip ?? '0.0.0.0', $ua ?? '', 'اسکن دوربین');

        Flash::set($ok ? 'success' : 'error', $ok ? ($nextType === 'in' ? 'ورود شما با اسکن QR ثبت شد.' : 'خروج شما با اسکن QR ثبت شد.') : $error);
    }

    /**
     * Which punch type comes next for the user TODAY:
     * last punch "in" (or nothing) → "out"… actually: nothing → "in",
     * last "in" → "out", last "out" → "in" (multi in/out cycles).
     */
    private static function nextPunchType(int $userId): string
    {
        $stmt = Database::conn()->prepare(
            "SELECT type FROM punches
             WHERE user_id = ? AND date(punched_at) = date('now', 'localtime')
             ORDER BY punched_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $last = $stmt->fetchColumn();
        return $last === 'in' ? 'out' : 'in';
    }

    /**
     * All server-side punch rules in one place (shared by the dashboard
     * form and the camera-scan flow):
     *   - geofence check when coords are sent or method is "location"
     *   - writes the punches row
     * @return array{0: bool, 1: string} [success, error message]
     */
    private static function performPunch(
        int $userId,
        string $type,
        string $method,
        ?string $lat,
        ?string $lng,
        ?string $qrTokenHash,
        ?string $ip,
        ?string $ua,
        ?string $note
    ): array {
        $rules = self::applyRules($method, $lat, $lng);
        if ($rules[0] === false) {
            return [false, $rules[3] ?? ''];
        }
        $distanceM   = $rules[1];
        $insideFence = $rules[2];

        Punch::create([
            'user_id'         => $userId,
            'type'            => $type,
            'method'          => $method,
            'punched_at'      => date('Y-m-d H:i:s'),
            'lat'             => $lat !== null && $lat !== '' ? (float) $lat : null,
            'lng'             => $lng !== null && $lng !== '' ? (float) $lng : null,
            'distance_m'      => $distanceM,
            'inside_fence'    => $insideFence,
            'qr_token_hash'   => $qrTokenHash,
            'ip'              => $ip ?? '0.0.0.0',
            'user_agent'      => $ua ?? '',
            'note'            => $note,
        ]);

        return [true, ''];
    }

    /**
     * Shared server-side rules (used by performPunch, and directly by
     * /scan + resumePendingScan via performPunch).
     *
     * When the admin enables the geofence, EVERY punch — qrcode, location,
     * or any other method — requires coordinates: the employee must be
     * inside the fence to register in/out. Missing coordinates or being
     * outside the radius rejects the punch; the rules live on the server,
     * never in the client.
     *
     * @return array{0: bool, 1: float|null, 2: bool|null, 3?: string}
     *         [ok, distance_m, inside_fence, error_message]
     */
    private static function applyRules(string $method, ?string $lat, ?string $lng): array
    {
        $coordsSent = $lat !== null && $lng !== null && $lat !== '' && $lng !== '';
        $geofenceOn = Config::str('geofence_enabled', '0') === '1';

        if ($geofenceOn || $method === 'location') {
            if (!$coordsSent) {
                return [false, null, null, 'موقعیت مکانی دریافت نشد. برای ثبت، GPS گوشی را روشن کنید و دوباره تلاش کنید.'];
            }
            $result = GeoService::checkGeofence((float) $lat, (float) $lng);
            if ($geofenceOn && $result['inside_fence'] === false) {
                return [false, $result['distance_m'], false, 'شما بیرون از محدوده محل کار هستید؛ برای ثبت، به محل کار نزدیک‌تر شوید.'];
            }
            return [true, $result['distance_m'], $result['inside_fence']];
        }

        if ($coordsSent) {
            // Geofence off but coords volunteered — audit them anyway.
            $result = GeoService::checkGeofence((float) $lat, (float) $lng);
            return [true, $result['distance_m'], $result['inside_fence']];
        }

        return [true, null, null];
    }
}
