<?php

namespace App\Services;

use App\Models\SystemConfig;

/**
 * Geolocation Service for calculating distances and validating geofences.
 * Uses the Haversine formula to calculate distances between coordinates.
 */
class GeolocationService
{
    /**
     * Earth's radius in meters as per the WGS84 ellipsoid.
     */
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Default geofence radius in meters (10 meters).
     */
    private const DEFAULT_GEOFENCE_RADIUS = 10;

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
     * @param float $radiusMeters Radius in meters (default: 10 meters)
     * @return bool True if the employee is within the geofence, false otherwise
     */
    public function isWithinGeofence(
        float $employeeLat,
        float $employeeLon,
        float $workplaceLat,
        float $workplaceLon,
        float $radiusMeters = self::DEFAULT_GEOFENCE_RADIUS
    ): bool {
        $distance = $this->calculateDistance(
            $employeeLat,
            $employeeLon,
            $workplaceLat,
            $workplaceLon
        );

        return $distance <= $radiusMeters;
    }

    /**
     * Get the workplace coordinates from system configuration.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function getWorkplaceCoordinates(): ?array
    {
        $config = SystemConfig::where('key', 'workplace_coordinates')->first();

        if (!$config) {
            return null;
        }

        $coordinates = json_decode($config->value, true);

        if (!isset($coordinates['latitude'], $coordinates['longitude'])) {
            return null;
        }

        return [
            'latitude' => (float) $coordinates['latitude'],
            'longitude' => (float) $coordinates['longitude'],
        ];
    }

    /**
     * Check if the employee is within the workplace geofence.
     * Uses the default 10-meter radius from system configuration.
     *
     * @param float $employeeLat Employee's current latitude
     * @param float $employeeLon Employee's current longitude
     * @return bool True if within geofence, false otherwise
     */
    public function isWithinWorkplaceGeofence(
        float $employeeLat,
        float $employeeLon
    ): bool {
        $workplace = $this->getWorkplaceCoordinates();

        if ($workplace === null) {
            return false;
        }

        return $this->isWithinGeofence(
            $employeeLat,
            $employeeLon,
            $workplace['latitude'],
            $workplace['longitude'],
            self::DEFAULT_GEOFENCE_RADIUS
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