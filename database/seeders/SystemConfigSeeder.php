<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Working hours schedule
        SystemConfig::updateOrCreate(
            ['key' => 'working_hours'],
            [
                'value'       => json_encode(array_merge(
                    \App\Services\WorkingHoursService::defaultConfig(),
                    [
                        'special_days' => [
                            now()->year . '-01-01' => ['type' => 'holiday',          'name' => "New Year's Day"],
                            now()->year . '-05-01' => ['type' => 'holiday',          'name' => 'Labour Day'],
                            now()->year . '-08-17' => ['type' => 'holiday',          'name' => 'Independence Day'],
                            now()->year . '-12-25' => ['type' => 'holiday',          'name' => 'Christmas Day'],
                            // Example: authorised overtime Sunday with a custom rate
                            now()->startOfMonth()->next('sunday')->format('Y-m-d') => [
                                'type'          => 'overtime_sunday',
                                'name'          => 'Authorised Work Sunday',
                                'rate_per_hour' => 60000,
                            ],
                        ],
                    ]
                )),
                'description' => 'Per-day work schedule + public holidays + special overtime days.',
            ]
        );

        // Workplace position and geofence radius (example: Jakarta office).
        // Both are editable from the admin Location page; this only sets the
        // starting values so a fresh install has a working fence.
        SystemConfig::updateOrCreate(
            ['key' => \App\Services\GeolocationService::CONFIG_KEY],
            [
                'value' => json_encode([
                    'latitude'      => -6.2088,
                    'longitude'     => 106.8456,
                    'radius_meters' => \App\Services\GeolocationService::DEFAULT_GEOFENCE_RADIUS,
                ]),
                'description' => 'Workplace GPS coordinates and accepted radius for geofence validation.',
            ]
        );

        $this->command->info('System configs seeded.');
    }
}
