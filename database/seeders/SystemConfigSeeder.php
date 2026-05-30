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

        // Workplace coordinates (example: Jakarta office)
        SystemConfig::updateOrCreate(
            ['key' => 'workplace_coordinates'],
            [
                'value' => json_encode([
                    'latitude'  => -6.2088,
                    'longitude' => 106.8456,
                ]),
                'description' => 'Workplace GPS coordinates for geofence validation.',
            ]
        );

        $this->command->info('System configs seeded.');
    }
}
