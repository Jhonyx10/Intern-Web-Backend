<?php

namespace App\Support;

class Geofence
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    public static function distanceMeters(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB,
    ): float {
        $latFrom = deg2rad($latitudeA);
        $latTo = deg2rad($latitudeB);
        $latDelta = deg2rad($latitudeB - $latitudeA);
        $lngDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    public static function isWithinRadius(
        float $latitude,
        float $longitude,
        float $centerLatitude,
        float $centerLongitude,
        int $radiusMeters,
        float $accuracyGraceMeters = 0,
    ): bool {
        $distance = self::distanceMeters(
            $latitude,
            $longitude,
            $centerLatitude,
            $centerLongitude,
        );

        return $distance <= ($radiusMeters + max(0, $accuracyGraceMeters));
    }
}
