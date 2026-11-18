<?php

namespace App\Core;

use App\Core\Csrf;
use App\Models\LeaveRequest;
use App\Services\ReportService;
use App\Services\CalendarService;
use App\Controllers\AuthController;
use App\Controllers\LeaveController;
use App\Controllers\PunchController;
use App\Controllers\Admin\UsersController;
use App\Controllers\Admin\LeavesController;
use App\Controllers\Admin\PunchesController;
use App\Controllers\Admin\ReportsController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\DashboardController;

class Router
{
    /**
     * Dispatches the current request to the matching controller action.
     * Returns the HTML response (or redirects).
     */
    public static function dispatch(): void
    {
        // Security headers
        self::securityHeaders();

        // CSRF guard for all POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
        }

        // Route table
        $route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
        $method = $_SERVER['REQUEST_METHOD'];

        // --- Auth ---
        if ($route === 'login' && $method === 'GET') {
            // Already logged in → skip the login form
            if (Auth::check()) {
                header('Location: /');
                exit;
            }
            exit(AuthController::showLogin());
        }
        if ($route === 'login' && $method === 'POST') {
            AuthController::attempt();
            return;
        }
        if ($route === 'logout') {
            AuthController::logout();
            return;
        }

        // --- Camera QR scan (public GET; punches or parks a pending scan) ---
        if ($route === 'scan' && $method === 'GET') {
            PunchController::scan();
            return;
        }

        // --- Employee (auth required) ---
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        // Dashboard
        if ($route === '' || $route === 'dashboard') {
            exit(Auth::isAdmin() ? DashboardController::index() : self::employeeDashboard());
        }

        // Punch
        if ($route === 'punch' && $method === 'POST') {
            PunchController::create();
            return;
        }

        // Theme selection (any logged-in user)
        if ($route === 'theme' && $method === 'POST') {
            AuthController::setTheme();
            return;
        }

        // Leave requests
        if ($route === 'leave-requests') {
            if ($method === 'GET') {
                exit(LeaveController::index());
            }
            if ($method === 'POST') {
                LeaveController::create();
                return;
            }
        }

        // --- Admin (auth + admin role) ---
        if (!Auth::isAdmin()) {
            // Non-admin hitting an admin route → redirect to their dashboard
            header('Location: /');
            exit;
        }

        if ($route === 'admin/dashboard' && $method === 'GET') {
            exit(DashboardController::index());
        }
        if ($route === 'admin/users' && $method === 'GET') {
            exit(UsersController::index());
        }
        if ($route === 'admin/users/create' && $method === 'GET') {
            exit(UsersController::create());
        }
        if ($route === 'admin/users/create' && $method === 'POST') {
            UsersController::store();
            return;
        }
        if (preg_match('#^admin/users/(\d+)$#', $route, $m) && $method === 'GET') {
            exit(UsersController::edit((int) $m[1]));
        }
        if (preg_match('#^admin/users/(\d+)/update$#', $route, $m) && $method === 'POST') {
            UsersController::update((int) $m[1]);
            return;
        }
        if ($route === 'admin/settings' && $method === 'GET') {
            exit(SettingsController::index());
        }
        if ($route === 'admin/settings/update' && $method === 'POST') {
            SettingsController::update();
            return;
        }
        if ($route === 'admin/qr' && $method === 'GET') {
            exit(SettingsController::qr());
        }
        if ($route === 'admin/leaves' && $method === 'GET') {
            exit(LeavesController::index());
        }
        if (preg_match('#^admin/leaves/(\d+)/approve$#', $route, $m) && $method === 'POST') {
            LeavesController::approve((int) $m[1]);
            return;
        }
        if (preg_match('#^admin/leaves/(\d+)/reject$#', $route, $m) && $method === 'POST') {
            LeavesController::reject((int) $m[1]);
            return;
        }
        if ($route === 'admin/punches' && $method === 'GET') {
            exit(PunchesController::index());
        }
        if (preg_match('#^admin/punches/(\d+)/edit$#', $route, $m) && $method === 'GET') {
            exit(PunchesController::edit((int) $m[1]));
        }
        if (preg_match('#^admin/punches/(\d+)/update$#', $route, $m) && $method === 'POST') {
            PunchesController::update((int) $m[1]);
            return;
        }
        if ($route === 'admin/reports' && $method === 'GET') {
            exit(ReportsController::index());
        }

        // 404
        http_response_code(404);
        exit('<h1>404 — صفحه پیدا نشد</h1><a href="/">بازگشت به خانه</a>');
    }

    /**
     * Renders the employee dashboard view.
     */
    private static function employeeDashboard(): string
    {
        $user  = Auth::user();
        $today = date('Y-m-d');
        $pdo   = Database::conn();

        $stmt = $pdo->prepare(
            "SELECT type, punched_at, method FROM punches WHERE user_id = ? AND date(punched_at) = ? ORDER BY punched_at ASC"
        );
        $stmt->execute([$user->id, $today]);
        $todayPunches = $stmt->fetchAll();

        $hasIn  = false;
        $hasOut = false;
        foreach ($todayPunches as $p) {
            if ($p['type'] === 'in') {
                $hasIn = true;
            } else {
                $hasOut = true;
            }
        }

        // ── Current Jalali month stats for this employee ──────────────
        $monthRange = CalendarService::currentJalaliMonthRange();
        $month = ReportService::generate(
            date('Y-m-d', $monthRange['start']),
            date('Y-m-d', $monthRange['end']),
            (string) $user->id
        );

        // ── Last 7 days daily-hours series (for the mini chart) ───────
        $stmt = $pdo->prepare(
            "SELECT date(p.punched_at) AS d,
                    SUM(CASE WHEN p.type = 'in' THEN 1 ELSE 0 END)  AS ins,
                    SUM(CASE WHEN p.type = 'out' THEN 1 ELSE 0 END) AS outs
             FROM punches p
             WHERE p.user_id = ? AND date(p.punched_at) >= date('now', '-6 days', 'localtime')
             GROUP BY d ORDER BY d ASC"
        );
        $stmt->execute([$user->id]);
        $weekRows = $stmt->fetchAll();

        $week = [];
        $faWeekdays = [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 0 => 'یکشنبه'];
        $byDay = [];
        foreach ($weekRows as $r) {
            $byDay[$r['d']] = $r;
        }
        for ($i = 6; $i >= 0; $i--) {
            $ts = strtotime("-$i days");
            $d = date('Y-m-d', $ts);
            $r = $byDay[$d] ?? null;
            $week[] = [
                'day'      => $faWeekdays[(int) date('w', $ts)],
                'date_js'  => $ts,
                'ins'      => $r ? (int) $r['ins'] : 0,
                'outs'     => $r ? (int) $r['outs'] : 0,
                'is_today' => $i === 0,
            ];
        }

        // ── Leave requests for this employee (recent 5) ───────────────
        $myLeaves = array_slice(LeaveRequest::forUser((int) $user->id), 0, 5);

        // ── Pending leave requests count ─────────────────────────────
        $pendingLeaves = 0;
        foreach (LeaveRequest::forUser((int) $user->id) as $lr) {
            if (($lr['status'] ?? '') === 'pending') {
                $pendingLeaves++;
            }
        }

        // Which punch methods the admin allows (employee UI only shows these)
        $allowedMethods = json_decode(\App\Core\Config::str('methods', '["manual"]'), true) ?: ['manual'];
        $methodMode = \App\Core\Config::str('method_mode', 'combined');
        $geofenceOn = \App\Core\Config::str('geofence_enabled', '0') === '1';

        return view('employee.dashboard', [
            'user'          => $user,
            'todayPunches'  => $todayPunches,
            'hasIn'         => $hasIn,
            'hasOut'        => $hasOut,
            'csrf'          => Csrf::field(),
            'month'         => $month,
            'week'          => $week,
            'myLeaves'      => $myLeaves,
            'pendingLeaves' => $pendingLeaves,
            'allowedMethods' => $allowedMethods,
            'methodMode'    => $methodMode,
            'geofenceOn'    => $geofenceOn,
        ]);
    }

    /**
     * Sets security headers on every response.
     *
     * Runs at the very top of dispatch() so it applies to pages, redirects,
     * CSV exports, and error exits alike.
     *
     * - X-Content-Type-Options: nosniff — stop MIME-type sniffing.
     * - X-Frame-Options / frame-ancestors — block clickjacking.
     * - X-XSS-Protection — legacy; kept for older browsers.
     * - Referrer-Policy — don't leak full URLs cross-origin.
     * - Permissions-Policy — camera stays (self) for html5-qrcode on the
     *   same origin; geolocation MUST be (self) too — `geolocation=()`
     *   hard-disables the GPS prompt in every browser (Chrome, Safari,
     *   Firefox) and silently breaks the "موقعیت مکانی" punch method.
     *   Microphone/payment stay fully denied.
     * - Strict-Transport-Security — HSTS, only on HTTPS so a stray local
     *   or HTTP request never gets pinned to HTTPS.
     * - Content-Security-Policy:
     *     - 'self' default: same-origin scripts/styles/frames only.
     *     - Google Fonts (Vazirmatn, loaded by the layout) → allow
     *       fonts.googleapis.com + fonts.gstatic.com.
     *     - QR codes are inline data: URIs (PngWriter → base64) → allow
     *       'data:' for img-src.
     *     - html5-qrcode uses getUserMedia locally; no external frame.
     *     - 'unsafe-inline' for script/style only where unavoidable (the
     *       layout emits tiny inline <style> blocks); img-src stays strict.
     */
    private static function securityHeaders(): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-XSS-Protection'       => '1; mode=block',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(self), geolocation=(self), microphone=(), payment=()',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "script-src 'self'",
                // 'unsafe-inline' for style only — dynamic width bars in the
                // admin dashboard set inline style="width: N%". The style
                // value is computed server-side from a SQL SUM and cannot
                // be abused by a client-injected attacker. script-src stays
                // strict to keep blocking XSS.
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src https://fonts.gstatic.com",
                // Neshan Leaflet SDK (admin settings map picker): tiles are
                // served from tile1..tile8.neshan.org, marker/UI images from
                // static.neshan.org. https://*.neshan.org covers all three.
                "img-src 'self' data: https://*.neshan.org",
                "connect-src 'self' https://api.neshan.org",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "upgrade-insecure-requests",
            ]),
        ];

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        if ($isHttps) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true, 200);
        }
    }
}
