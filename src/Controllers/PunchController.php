<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Config;
use App\Models\Punch;
use App\Services\QrService;
use App\Services\GeoService;

class PunchController
{
    /**
     * Handles the punch POST (in/out).
     * Method is determined by the "method" POST field:
     *   manual, location, or qrcode
     */
    public static function create(): void
    {
        $user  = Auth::user();
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

        $now = time();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        $distanceM   = null;
        $insideFence = null;

        // Geofence: whenever coords are sent (any method) or the method
        // is "location", verify the employee is inside the work radius.
        // A punch outside the fence is REJECTED — the rules live on the
        // server, the client only pre-checks for a friendlier message.
        $coordsSent = $lat !== null && $lng !== null && $lat !== '' && $lng !== '';
        $geofenceOn = Config::str('geofence_enabled', '0') === '1';

        if ($coordsSent || $method === 'location') {
            if ($coordsSent) {
                $result = GeoService::checkGeofence((float) $lat, (float) $lng);
                $distanceM   = $result['distance_m'];
                $insideFence = $result['inside_fence'];

                if ($geofenceOn && $insideFence === false) {
                    Flash::set('error', 'شما بیرون از محدوده محل کار هستید؛ برای ثبت ورود یا خروج، به محل کار نزدیک‌تر شوید.');
                    header('Location: /');
                    return;
                }
            } elseif ($method === 'location') {
                // "location" method chosen but the device sent no coords
                Flash::set('error', 'موقعیت مکانی گوشی دریافت نشد. GPS را روشن کنید و دوباره تلاش کنید.');
                header('Location: /');
                return;
            }
        }

        // QR verification
        $qrTokenHash = null;
        if ($method === 'qrcode' && $qrToken !== null) {
            $verify = QrService::verifyToken($qrToken);
            if ($verify === false) {
                Flash::set('error', 'توکن QR معتبر نیست یا منقضی شده است.');
                header('Location: /');
                return;
            }
            $qrTokenHash = $verify; // store the hash, not the raw token
        }

        $punch = Punch::create([
            'user_id'         => $user->id,
            'type'            => $type,
            'method'          => $method,
            'punched_at'      => date('Y-m-d H:i:s', $now),
            'lat'             => $lat !== null ? (float) $lat : null,
            'lng'             => $lng !== null ? (float) $lng : null,
            'distance_m'      => $distanceM,
            'inside_fence'    => $insideFence,
            'qr_token_hash'   => $qrTokenHash,
            'ip'              => $ip,
            'user_agent'      => $ua,
            'note'            => $_POST['note'] ?? null,
        ]);

        Flash::set('success', $type === 'in' ? 'ورود شما ثبت شد.' : 'خروج شما ثبت شد.');
        header('Location: /');
        exit;
    }
}
