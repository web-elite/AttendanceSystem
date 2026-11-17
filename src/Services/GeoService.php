<?php

namespace App\Services;

use App\Core\Config;

class GeoService
{
    /**
     * Calculates Haversine distance in meters between two lat/lng points.
     *
     * @param  float  $lat1
     * @param  float  $lng1
     * @param  float  $lat2
     * @param  float  $lng2
     * @return float  distance in meters
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Checks whether the given coordinates are inside the configured geofence.
     *
     * @return array{distance_m: float|null, inside_fence: bool|null}
     */
    public static function checkGeofence(float $lat, float $lng): array
    {
        if (!Config::bool('geofence_enabled', false)) {
            return ['distance_m' => null, 'inside_fence' => null];
        }

        $officeLat = (float) Config::str('office_lat', '0');
        $officeLng = (float) Config::str('office_lng', '0');
        $radiusM   = Config::int('radius_m', 150);

        if ($officeLat === 0.0 && $officeLng === 0.0) {
            return ['distance_m' => null, 'inside_fence' => null];
        }

        $distanceM = self::haversine($lat, $lng, $officeLat, $officeLng);
        return [
            'distance_m'   => round($distanceM, 1),
            'inside_fence' => $distanceM <= $radiusM,
        ];
    }
}
