<?php

namespace App\Services;

use App\Models\SystemConfig;

/**
 * Geolocation Service for calculating distances and validating geofences.
 * Uses the Haversine formula to calculate distances between coordinates.
 *
 * The workplace position and the accepted radius are operator settings, stored
 * in `system_configs` under the key `workplace_coordinates` and editable from
 * the admin Location page. Nothing here is hardcoded: the constants below are
 * only the fallbacks used when the row is missing or incomplete.
 */
class GeolocationService
{
    /**
     * Earth's radius in meters as per the WGS84 ellipsoid.
     */
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Fallback geofence radius, in meters, used only when no radius is
     * configured. The seeded default and the admin page both start here.
     */
    public const DEFAULT_GEOFENCE_RADIUS = 10;

    /**
     * Bounds for the configured radius, in meters.
     *
     * The floor is above the ~5-10m accuracy of consumer GPS, because a smaller
     * fence would reject a correctly positioned employee at random. The ceiling
     * is generous enough for a large site while still being a fence rather than
     * "anywhere in the city".
     */
    public const MIN_GEOFENCE_RADIUS = 5;
    public const MAX_GEOFENCE_RADIUS = 10000;

    /**
     * The `system_configs` key holding the workplace position and radius.
     */
    public const CONFIG_KEY = 'workplace_coordinates';

    /**
     * Calculate the distance between two coordinates using the Haversine formula.
     *
     * The Haversine formula determines the great-circle distance between
     * two points on a sphere given their longitudes and latitudes.
     *
     * @param float $lat1 Latitude of the first point in decimal degrees
     * @param float $lon1 Longitude of the first point in decimal degrees
     * @param float $lat2 Latitude of the second point in decimal degrees
     * @param float $lon2 Longitude of the second point in decimal degrees
     * @return float Distance between the two points in meters
     */
    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        // Convert decimal degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        // Calculate differences
        $dLat = $lat2Rad - $lat1Rad;
        $dLon = $lon2Rad - $lon1Rad;

        // Haversine formula
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Calculate distance in meters
        $distance = self::EARTH_RADIUS_METERS * $c;

        return $distance;
    }

    /**
     * Check if a set of coordinates is within a specified geofence radius.
     *
     * @param float $employeeLat Employee's current latitude
     * @param float $employeeLon Employee's current longitude
     * @param float $workplaceLat Workplace latitude
     * @param float $workplaceLon Workplace longitude
     * @param float|null $radiusMeters Radius in meters; null uses the configured radius
     * @return bool True if the employee is within the geofence, false otherwise
     */
    public function isWithinGeofence(
        float $employeeLat,
        float $employeeLon,
        float $workplaceLat,
        float $workplaceLon,
        ?float $radiusMeters = null
    ): bool {
        $distance = $this->calculateDistance(
            $employeeLat,
            $employeeLon,
            $workplaceLat,
            $workplaceLon
        );

        return $distance <= ($radiusMeters ?? $this->getGeofenceRadius());
    }

    /**
     * Read the stored workplace row.
     *
     * Returns null when the row is absent or does not carry a usable pair of
     * coordinates, which callers treat as "not configured" and fail closed.
     *
     * @return array{latitude: float, longitude: float, radius_meters: float}|null
     */
    public function getWorkplaceConfig(): ?array
    {
        $config = SystemConfig::find(self::CONFIG_KEY);

        if (!$config) {
            return null;
        }

        $decoded = json_decode($config->value, true);

        if (!is_array($decoded) || !isset($decoded['latitude'], $decoded['longitude'])) {
            return null;
        }

        // A row written before the radius was configurable has no radius key.
        // Treat that as the default rather than as "no radius".
        $radius = isset($decoded['radius_meters']) && is_numeric($decoded['radius_meters'])
            ? (float) $decoded['radius_meters']
            : (float) self::DEFAULT_GEOFENCE_RADIUS;

        return [
            'latitude'      => (float) $decoded['latitude'],
            'longitude'     => (float) $decoded['longitude'],
            'radius_meters' => $this->clampRadius($radius),
        ];
    }

    /**
     * Get the workplace coordinates from system configuration.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function getWorkplaceCoordinates(): ?array
    {
        $config = $this->getWorkplaceConfig();

        if ($config === null) {
            return null;
        }

        return [
            'latitude'  => $config['latitude'],
            'longitude' => $config['longitude'],
        ];
    }

    /**
     * The configured geofence radius in meters.
     *
     * Falls back to the default when the workplace row is missing entirely, so
     * callers that only need the radius do not have to null-check.
     */
    public function getGeofenceRadius(): float
    {
        $config = $this->getWorkplaceConfig();

        return $config['radius_meters'] ?? (float) self::DEFAULT_GEOFENCE_RADIUS;
    }

    /**
     * Persist the workplace position and radius.
     *
     * Only this method writes the row, so the stored shape stays in one place.
     *
     * @param float $latitude  Decimal degrees, -90..90
     * @param float $longitude Decimal degrees, -180..180
     * @param float $radiusMeters Accepted distance from the workplace
     */
    public function setWorkplaceConfig(
        float $latitude,
        float $longitude,
        float $radiusMeters
    ): void {
        SystemConfig::updateOrCreate(
            ['key' => self::CONFIG_KEY],
            [
                'value' => json_encode([
                    'latitude'      => $latitude,
                    'longitude'     => $longitude,
                    'radius_meters' => $this->clampRadius($radiusMeters),
                ]),
                'description' => 'Workplace GPS coordinates and accepted radius for geofence validation.',
            ]
        );
    }

    /**
     * Keep a radius inside the supported bounds.
     */
    public function clampRadius(float $radius): float
    {
        return max(
            (float) self::MIN_GEOFENCE_RADIUS,
            min((float) self::MAX_GEOFENCE_RADIUS, $radius)
        );
    }

    /**
     * Check if the employee is within the workplace geofence.
     *
     * @param float $employeeLat Employee's current latitude
     * @param float $employeeLon Employee's current longitude
     * @return bool True if within geofence, false otherwise
     */
    public function isWithinWorkplaceGeofence(
        float $employeeLat,
        float $employeeLon
    ): bool {
        $workplace = $this->getWorkplaceConfig();

        if ($workplace === null) {
            return false;
        }

        return $this->isWithinGeofence(
            $employeeLat,
            $employeeLon,
            $workplace['latitude'],
            $workplace['longitude'],
            $workplace['radius_meters']
        );
    }

    /**
     * Get the distance from employee to workplace in meters.
     *
     * @param float $employeeLat Employee's current latitude
     * @param float $employeeLon Employee's current longitude
     * @return float|null Distance in meters, or null if workplace not configured
     */
    public function getDistanceToWorkplace(
        float $employeeLat,
        float $employeeLon
    ): ?float {
        $workplace = $this->getWorkplaceCoordinates();

        if ($workplace === null) {
            return null;
        }

        return $this->calculateDistance(
            $employeeLat,
            $employeeLon,
            $workplace['latitude'],
            $workplace['longitude']
        );
    }
}
