<?php

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Database;
use App\Models\Setting;
use App\Services\QrService;

/**
 * All settings live in the `settings` table as key/value rows.
 * The form posts everything at once; update() validates and persists.
 */
class SettingsController
{
    /** Method keys shown in the form, with their Persian labels + hints. */
    private const METHODS = [
        'manual'   => ['ثبت ساده (بدون تاییدیه)', 'کارمند فقط دکمه را می‌زند و ثبت می‌شود. سریع‌ترین حالت، بدون نیاز به دوربین یا GPS.'],
        'location' => ['ثبت با موقعیت مکانی', 'هنگام ثبت، موقعیت گوشی کارمند بررسی می‌شود؛ اگر بیرون از محدوده محل کار باشد ثبت نمی‌شود.'],
        'qrcode'   => ['ثبت با اسکن کد QR', 'کارمند با دوربین گوشی کدی را که روی صفحه نمایش یا دیوار محل کار است اسکن می‌کند. از عکس گرفتن کد جلوگیری می‌شود.'],
    ];

    /**
     * Shows the settings form with all current values.
     */
    public static function index(): string
    {
        $settings = Setting::all();
        $config = require dirname(__DIR__, 3) . '/config.php';
        return view('admin.settings', [
            'settings'  => $settings,
            'csrf'      => Csrf::field(),
            'neshanKey' => (string) ($config['env']['NESHAN_API_KEY'] ?? ''),
            'methodMeta' => self::METHODS,
        ]);
    }

    /**
     * Shows a full-screen QR the admin can display/print where employees scan it.
     * - rotating mode: shows the current token + seconds left + auto refresh.
     * - static mode:   shows the fixed token (created on first visit if empty).
     */
    public static function qr(): string
    {
        $mode = \App\Core\Config::str('qr_mode', 'rotating');

        if ($mode === 'static') {
            $token = QrService::generateStaticToken(); // returns existing or creates one
            $expiresIn = null;
        } else {
            $token = QrService::generateRotatingToken();
            $expiresIn = \App\Core\Config::int('qr_ttl_seconds', 60);
        }

        return view('admin.qr', [
            'qrMode'    => $mode,
            'token'     => $token,
            'expiresIn' => $expiresIn,
            'dataUri'   => QrService::qrDataUri($token),
        ]);
    }

    /**
     * Saves settings from the form.
     */
    public static function update(): void
    {
        $pdo = Database::conn();

        // ── Punch methods ────────────────────────────────────────────
        // Only whitelisted methods are accepted; invalid keys are dropped.
        $methods = array_values(array_intersect(
            array_keys(self::METHODS),
            (array) ($_POST['methods'] ?? [])
        ));
        $methodMode = ($_POST['method_mode'] ?? 'combined') === 'single' ? 'single' : 'combined';

        $errors = [];
        if ($methods === []) {
            $errors[] = 'حداقل یک روش ثبت ورود و خروج باید فعال باشد.';
        } elseif ($methodMode === 'single' && count($methods) > 1) {
            $errors[] = 'در حالت «یک روش اجباری» فقط یک روش می‌توانید انتخاب کنید.';
        }

        // ── Hours ────────────────────────────────────────────────────
        $minIn  = self::normalizeTime($_POST['min_in_time'] ?? '');
        $maxOut = self::normalizeTime($_POST['max_out_time'] ?? '');
        if ($minIn === null || $maxOut === null) {
            $errors[] = 'ساعت شروع و پایان کار نامعتبر است.';
        } elseif ($maxOut <= $minIn) {
            $errors[] = 'ساعت پایان کار باید بعد از ساعت شروع باشد.';
        }

        // ── Geofence ─────────────────────────────────────────────────
        $geofenceOn = isset($_POST['geofence_enabled']);
        $lat = is_numeric($_POST['office_lat'] ?? '') ? (float) $_POST['office_lat'] : null;
        $lng = is_numeric($_POST['office_lng'] ?? '') ? (float) $_POST['office_lng'] : null;
        $radius = max(50, min(5000, (int) ($_POST['radius_m'] ?? 150)));
        if ($geofenceOn && ($lat === null || $lng === null)) {
            $errors[] = 'برای فعال‌سازی محدوده مکانی، مختصات محل کار را روی نقشه انتخاب یا وارد کنید.';
        }

        // ── QR ───────────────────────────────────────────────────────
        $qrMode  = ($_POST['qr_mode'] ?? 'rotating') === 'static' ? 'static' : 'rotating';
        $qrTtl   = max(30, min(300, (int) ($_POST['qr_ttl_seconds'] ?? 60)));

        // ── Policies ─────────────────────────────────────────────────
        $forgotten = in_array($_POST['forgotten_checkout'] ?? '', ['auto_close', 'admin_manual'], true)
            ? $_POST['forgotten_checkout'] : 'auto_close';
        $holidayMode = in_array($_POST['holiday_mode'] ?? '', ['none', 'friday', 'official', 'both'], true)
            ? $_POST['holiday_mode'] : 'friday';

        if ($errors !== []) {
            Flash::set('error', implode(' — ', $errors));
            header('Location: /admin/settings');
            return;
        }

        $updates = [
            'min_in_time'        => $minIn,
            'max_out_time'       => $maxOut,
            'grace_minutes'      => max(0, min(120, (int) ($_POST['grace_minutes'] ?? 0))),
            'geofence_enabled'   => $geofenceOn ? 1 : 0,
            'office_lat'         => $lat !== null ? (string) $lat : '',
            'office_lng'         => $lng !== null ? (string) $lng : '',
            'radius_m'           => $radius,
            'methods'            => json_encode($methods),
            'method_mode'        => $methodMode,
            'qr_mode'            => $qrMode,
            'qr_static_token'    => $qrMode === 'static'
                ? trim((string) ($_POST['qr_static_token'] ?? ''))
                : '',
            'qr_ttl_seconds'     => $qrTtl,
            'forgotten_checkout' => $forgotten,
            'holiday_mode'       => $holidayMode,
        ];

        foreach ($updates as $key => $value) {
            $pdo->prepare(
                "INSERT INTO settings (key, value) VALUES (?, ?)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value"
            )->execute([$key, (string) $value]);
        }

        Flash::set('success', 'تنظیمات ذخیره شد.');
        header('Location: /admin/settings');
        exit;
    }

    /**
     * Normalizes "H:MM"/"HH:MM" (with optional seconds) to "HH:MM",
     * or null when invalid.
     */
    private static function normalizeTime(string $raw): ?string
    {
        $raw = trim($raw);
        if (!preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $raw, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return null;
        }
        return sprintf('%02d:%02d', $h, $i);
    }
}
