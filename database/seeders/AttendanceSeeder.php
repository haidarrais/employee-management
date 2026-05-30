<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\QRCode;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AttendanceSeeder extends Seeder
{
    /**
     * Seed realistic attendance data for the past 30 days.
     *
     * Employees:
     *  - Alice        → mostly on time, occasional overtime
     *  - Bob          → frequently late, rarely overtime
     *  - Carol        → sometimes absent, on time when present
     *  - David        → very punctual, heavy overtime
     *
     * Saturday uses its own schedule (06:30–12:00, 12:30–14:30).
     * Sunday is a day off and is skipped.
     */
    public function run(): void
    {
        $whs = app(WorkingHoursService::class);

        $employees = $this->ensureEmployees();

        // Shared dummy QR code for seeded records
        $admin  = User::where('role', 'admin')->first();
        $qrCode = QRCode::firstOrCreate(
            ['token_hash' => 'seed-dummy-hash'],
            [
                'generated_by'    => $admin->id,
                'encrypted_token' => 'seed-dummy-token',
                'token_hash'      => 'seed-dummy-hash',
                'status'          => QRCode::STATUS_USED,
                'generated_at'    => now(),
                'expires_at'      => now()->addMinutes(5),
                'used_at'         => now(),
            ]
        );

        $today = Carbon::today();

        foreach ($employees as $profile) {
            $user = $profile['user'];

            for ($d = 29; $d >= 0; $d--) {
                $date = $today->copy()->subDays($d);

                // Skip days the service considers off (Sunday by default)
                if (!$whs->isWorkingDay($date)) {
                    continue;
                }

                // Random absence
                if (rand(1, 100) <= $profile['absence_pct']) {
                    continue;
                }

                // Skip if already seeded
                if (AttendanceRecord::where('user_id', $user->id)
                        ->whereDate('check_in_at', $date->toDateString())
                        ->exists()) {
                    continue;
                }

                $checkIn  = $this->randomCheckIn($date, $profile, $whs);
                $checkOut = $this->randomCheckOut($date, $profile, $whs);
                $computed = $whs->compute($checkIn, $checkOut, $user);

                AttendanceRecord::create([
                    'user_id'          => $user->id,
                    'qr_code_id'       => $qrCode->id,
                    'latitude'         => -6.2088 + (rand(-5, 5) / 10000),
                    'longitude'        => 106.8456 + (rand(-5, 5) / 10000),
                    'distance_meters'  => rand(1, 9),
                    'status'           => AttendanceRecord::STATUS_CONFIRMED,
                    'check_in_at'      => $checkIn,
                    'check_out_at'     => $checkOut,
                    'late_minutes'     => $computed['late_minutes'],
                    'overtime_minutes' => $computed['overtime_minutes'],
                    'daily_fee'        => $computed['daily_fee'],
                    // Set created_at to the actual attendance date so
                    // any query ordering by created_at also works correctly
                    'created_at'       => $checkIn,
                    'updated_at'       => $checkIn,
                ]);
            }

            $this->command->info("Seeded: {$user->name}");
        }
    }

    // ─────────────────────────────────────────────────────────────

    private function ensureEmployees(): array
    {
        $profiles = [
            // The default demo employee from UserSeeder — gets data so the
            // dashboard shows records when logged in as employee@example.com
            [
                'name'          => 'Employee',
                'email'         => 'employee@example.com',
                'daily_rate'    => 150000,
                'overtime_rate' => 25000,
                'absence_pct'   => 8,
                'late_bias'     => 10,
                'late_spread'   => 15,
                'ot_bias'       => 20,
                'ot_spread'     => 20,
            ],
            [
                'name'          => 'Alice Santoso',
                'email'         => 'alice@example.com',
                'daily_rate'    => 180000,
                'overtime_rate' => 30000,
                'absence_pct'   => 5,
                'late_bias'     => 5,
                'late_spread'   => 10,
                'ot_bias'       => 45,
                'ot_spread'     => 30,
            ],
            [
                'name'          => 'Bob Wijaya',
                'email'         => 'bob@example.com',
                'daily_rate'    => 150000,
                'overtime_rate' => 25000,
                'absence_pct'   => 10,
                'late_bias'     => 30,
                'late_spread'   => 20,
                'ot_bias'       => 10,
                'ot_spread'     => 15,
            ],
            [
                'name'          => 'Carol Putri',
                'email'         => 'carol@example.com',
                'daily_rate'    => 160000,
                'overtime_rate' => 27000,
                'absence_pct'   => 20,
                'late_bias'     => 0,
                'late_spread'   => 8,
                'ot_bias'       => 5,
                'ot_spread'     => 10,
            ],
            [
                'name'          => 'David Kurniawan',
                'email'         => 'david@example.com',
                'daily_rate'    => 200000,
                'overtime_rate' => 35000,
                'absence_pct'   => 2,
                'late_bias'     => -5,
                'late_spread'   => 5,
                'ot_bias'       => 90,
                'ot_spread'     => 30,
            ],
        ];

        foreach ($profiles as &$p) {
            $p['user'] = User::firstOrCreate(
                ['email' => $p['email']],
                [
                    'name'          => $p['name'],
                    'password_hash' => Hash::make('password123'),
                    'role'          => 'employee',
                    'mfa_enabled'   => false,
                    'is_active'     => true,
                    'daily_rate'    => $p['daily_rate'],
                    'overtime_rate' => $p['overtime_rate'],
                ]
            );
            // Use DB::table to avoid re-hashing password_hash via the model cast
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $p['user']->id)
                ->update([
                    'daily_rate'    => $p['daily_rate'],
                    'overtime_rate' => $p['overtime_rate'],
                ]);
            $p['user']->refresh();
        }

        return $profiles;
    }

    /**
     * Random check-in relative to the day's official start time.
     */
    private function randomCheckIn(Carbon $date, array $profile, WorkingHoursService $whs): Carbon
    {
        $officialStart = $whs->officialStart($date);   // e.g. 07:00 weekday, 06:30 Saturday
        $offset        = (int) round($profile['late_bias'] + rand(-$profile['late_spread'], $profile['late_spread']));

        return $officialStart->copy()
            ->setDate($date->year, $date->month, $date->day)
            ->addMinutes($offset);
    }

    /**
     * Random check-out relative to the day's official end time.
     */
    private function randomCheckOut(Carbon $date, array $profile, WorkingHoursService $whs): Carbon
    {
        $officialEnd = $whs->officialEnd($date);       // e.g. 16:00 weekday, 14:30 Saturday
        $otMinutes   = max(0, (int) round($profile['ot_bias'] + rand(-$profile['ot_spread'], $profile['ot_spread'])));

        return $officialEnd->copy()
            ->setDate($date->year, $date->month, $date->day)
            ->addMinutes($otMinutes);
    }
}
