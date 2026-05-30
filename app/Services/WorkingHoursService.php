<?php

namespace App\Services;

use App\Models\SystemConfig;
use App\Models\User;
use Carbon\Carbon;

/**
 * WorkingHoursService
 *
 * Handles per-day-of-week schedules, public holidays, and special overtime days.
 *
 * Full config format (stored under system_configs key "working_hours"):
 * {
 *   "days": {
 *     "monday":   { "segments": [{"start":"07:00","end":"12:00"},{"start":"13:00","end":"16:00"}] },
 *     "saturday": { "segments": [{"start":"06:30","end":"12:00"},{"start":"12:30","end":"14:30"}] },
 *     "sunday":   { "off": true }
 *   },
 *   "grace_minutes":                  15,
 *   "daily_base_fee":                 150000,
 *   "overtime_rate_per_hour":         25000,
 *   "holiday_overtime_rate_per_hour": 50000,   // default rate for holiday/Sunday work
 *   "special_days": {
 *     "2026-01-01": { "type": "holiday",          "name": "New Year's Day" },
 *     "2026-12-25": { "type": "holiday",          "name": "Christmas Day" },
 *     "2026-05-03": { "type": "overtime_sunday",  "name": "Special Work Sunday",
 *                     "rate_per_hour": 60000 }   // optional per-day override
 *   }
 * }
 *
 * Special day types:
 *   "holiday"         — public holiday; employees who work get paid at holiday OT rate
 *                       for every hour worked (no base pay, no late deduction).
 *   "overtime_sunday" — Sunday or any off-day where work is authorised; same pay logic
 *                       as holiday but can carry a per-day rate override.
 */
class WorkingHoursService
{
    /** Canonical day names in Carbon order (0 = Sunday) */
    public const DAY_NAMES = [
        'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
    ];

    private array $config;

    public function __construct()
    {
        $this->config = $this->loadConfig();
    }

    // ─────────────────────────────────────────────────────────────
    // Public API — config
    // ─────────────────────────────────────────────────────────────

    public function getConfig(): array
    {
        return $this->config;
    }

    /** All special days keyed by Y-m-d. */
    public function getSpecialDays(): array
    {
        return $this->config['special_days'] ?? [];
    }

    /** Special-day entry for a date, or null. */
    public function specialDay(Carbon $date): ?array
    {
        return $this->config['special_days'][$date->toDateString()] ?? null;
    }

    /** True when the date is a public holiday. */
    public function isHoliday(Carbon $date): bool
    {
        $sd = $this->specialDay($date);
        return $sd !== null && ($sd['type'] ?? '') === 'holiday';
    }

    /**
     * True when working on this date earns holiday/special overtime pay.
     * Covers: public holidays, authorised Sunday work, and any special_day entry.
     */
    public function isSpecialOvertimeDay(Carbon $date): bool
    {
        // Any special_day entry qualifies
        if ($this->specialDay($date) !== null) {
            return true;
        }
        // Sunday that is not a regular working day
        if ($date->isSunday() && !$this->isWorkingDay($date)) {
            return true;
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────
    // Public API — schedule
    // ─────────────────────────────────────────────────────────────

    /**
     * Day-of-week config for a date.
     * Returns null when the day is off / not configured.
     *
     * NOTE: holidays are NOT treated as off here — use isHoliday() separately.
     *
     * @return array{segments: array, off?: bool}|null
     */
    public function dayConfig(Carbon|string $day): ?array
    {
        $name = $day instanceof Carbon
            ? strtolower($day->format('l'))
            : strtolower($day);

        $cfg = $this->config['days'][$name] ?? null;

        if ($cfg === null || ($cfg['off'] ?? false)) {
            return null;
        }

        return $cfg;
    }

    /**
     * Whether a date is a regular working day (ignores holidays —
     * a holiday on Monday is still "Monday" in the schedule).
     */
    public function isWorkingDay(Carbon $date): bool
    {
        return $this->dayConfig($date) !== null;
    }

    /** Official start time for a date. Null on days off. */
    public function officialStart(Carbon $date): ?Carbon
    {
        $cfg = $this->dayConfig($date);
        if (!$cfg) return null;

        return Carbon::createFromTimeString($cfg['segments'][0]['start']);
    }

    /** Official end time for a date. Null on days off. */
    public function officialEnd(Carbon $date): ?Carbon
    {
        $cfg = $this->dayConfig($date);
        if (!$cfg) return null;

        $segs = $cfg['segments'];
        return Carbon::createFromTimeString(end($segs)['end']);
    }

    /** Total scheduled working minutes for a date (sum of segments, no breaks). */
    public function scheduledMinutes(Carbon $date): int
    {
        $cfg = $this->dayConfig($date);
        if (!$cfg) return 0;

        $total = 0;
        foreach ($cfg['segments'] as $seg) {
            $total += Carbon::createFromTimeString($seg['start'])
                ->diffInMinutes(Carbon::createFromTimeString($seg['end']));
        }
        return $total;
    }

    // ─────────────────────────────────────────────────────────────
    // Public API — calculations
    // ─────────────────────────────────────────────────────────────

    /**
     * Minutes late for a check-in.
     * Always 0 on special overtime days (no "late" concept — all hours are OT).
     */
    public function lateMinutes(Carbon $checkIn): int
    {
        // No late penalty on holidays / special days
        if ($this->isSpecialOvertimeDay($checkIn)) {
            return 0;
        }

        $start = $this->officialStart($checkIn);
        if (!$start) return 0;

        $grace    = (int) ($this->config['grace_minutes'] ?? 0);
        $deadline = $start->copy()->addMinutes($grace);
        $actual   = Carbon::createFromTimeString($checkIn->format('H:i:s'));

        return $actual->lte($deadline) ? 0 : (int) $deadline->diffInMinutes($actual);
    }

    /**
     * Overtime minutes for a check-out.
     *
     * On special overtime days every minute worked counts as overtime.
     * On regular days only minutes past the official end count.
     */
    public function overtimeMinutes(?Carbon $checkOut, ?Carbon $checkIn = null): int
    {
        if (!$checkOut) return 0;

        // Special day: all worked time is overtime
        if ($this->isSpecialOvertimeDay($checkOut)) {
            if ($checkIn === null) return 0;
            return (int) $checkIn->diffInMinutes($checkOut);
        }

        $end = $this->officialEnd($checkOut);
        if (!$end) return 0;

        $actual = Carbon::createFromTimeString($checkOut->format('H:i:s'));
        return $actual->lte($end) ? 0 : (int) $end->diffInMinutes($actual);
    }

    /**
     * Effective overtime rate per hour for a given date.
     *
     * Priority:
     *   1. Per-day rate in special_days entry
     *   2. Global holiday_overtime_rate_per_hour
     *   3. Employee's own overtime_rate
     *   4. Global overtime_rate_per_hour
     */
    public function effectiveOvertimeRate(Carbon $date, ?User $user = null): float
    {
        $sd = $this->specialDay($date);

        // Per-day override in special_days
        if ($sd && isset($sd['rate_per_hour'])) {
            return (float) $sd['rate_per_hour'];
        }

        // Holiday / Sunday → use holiday rate
        if ($this->isSpecialOvertimeDay($date)) {
            return (float) ($this->config['holiday_overtime_rate_per_hour']
                ?? $this->config['overtime_rate_per_hour']
                ?? 0);
        }

        // Regular day → employee rate or global default
        return (float) ($user?->overtime_rate ?? $this->config['overtime_rate_per_hour'] ?? 0);
    }

    /**
     * Daily fee calculation.
     *
     * Special overtime days (holidays, Sundays):
     *   base = 0, all worked minutes paid at holiday OT rate.
     *
     * Regular days:
     *   base fee (employee or global) minus proportional late deduction,
     *   plus overtime pay at the regular OT rate.
     *
     * @param Carbon    $date
     * @param int       $lateMinutes
     * @param int       $overtimeMinutes
     * @param User|null $user
     * @param int       $workedMinutes   Total minutes worked (needed for special days)
     */
    public function dailyFee(
        Carbon  $date,
        int     $lateMinutes,
        int     $overtimeMinutes,
        ?User   $user = null,
        int     $workedMinutes = 0,
    ): float {
        $otRate = $this->effectiveOvertimeRate($date, $user);

        // Special overtime day: no base pay, all hours at holiday rate
        if ($this->isSpecialOvertimeDay($date)) {
            return round($otRate * ($workedMinutes / 60), 2);
        }

        // Regular day
        $base      = (float) ($user?->daily_rate ?? $this->config['daily_base_fee'] ?? 0);
        $scheduled = $this->scheduledMinutes($date);

        $lateDeduction = $scheduled > 0 ? ($base * $lateMinutes / $scheduled) : 0;
        $overtimePay   = $otRate * ($overtimeMinutes / 60);

        return round(max(0, $base - $lateDeduction) + $overtimePay, 2);
    }

    /**
     * Compute late, overtime, and fee in one call.
     *
     * @return array{late_minutes: int, overtime_minutes: int, daily_fee: float, is_special_day: bool, special_day_name: string|null}
     */
    public function compute(Carbon $checkIn, ?Carbon $checkOut, ?User $user = null): array
    {
        $isSpecial   = $this->isSpecialOvertimeDay($checkIn);
        $sd          = $this->specialDay($checkIn);
        $late        = $this->lateMinutes($checkIn);
        $overtime    = $this->overtimeMinutes($checkOut, $checkIn);
        $workedMin   = $checkOut ? (int) $checkIn->diffInMinutes($checkOut) : 0;
        $fee         = $this->dailyFee($checkIn, $late, $overtime, $user, $workedMin);

        return [
            'late_minutes'      => $late,
            'overtime_minutes'  => $overtime,
            'daily_fee'         => $fee,
            'is_special_day'    => $isSpecial,
            'special_day_name'  => $sd['name'] ?? ($isSpecial ? 'Special Day' : null),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Config loading & defaults
    // ─────────────────────────────────────────────────────────────

    private function loadConfig(): array
    {
        $row = SystemConfig::find('working_hours');

        if ($row) {
            $decoded = json_decode($row->value, true);
            if (is_array($decoded)) {
                if (isset($decoded['days'])) {
                    return $decoded;
                }
                if (isset($decoded['segments'])) {
                    return $this->migrateLegacy($decoded);
                }
            }
        }

        return self::defaultConfig();
    }

    private function migrateLegacy(array $old): array
    {
        $weekdaySchedule = ['segments' => $old['segments']];
        return array_merge(self::defaultConfig(), [
            'days' => [
                'monday'    => $weekdaySchedule,
                'tuesday'   => $weekdaySchedule,
                'wednesday' => $weekdaySchedule,
                'thursday'  => $weekdaySchedule,
                'friday'    => $weekdaySchedule,
                'saturday'  => ['off' => true],
                'sunday'    => ['off' => true],
            ],
            'grace_minutes'          => $old['grace_minutes']          ?? 15,
            'daily_base_fee'         => $old['daily_base_fee']         ?? 150000,
            'overtime_rate_per_hour' => $old['overtime_rate_per_hour'] ?? 25000,
        ]);
    }

    public static function defaultConfig(): array
    {
        $weekday  = ['segments' => [
            ['start' => '07:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '16:00'],
        ]];
        $saturday = ['segments' => [
            ['start' => '06:30', 'end' => '12:00'],
            ['start' => '12:30', 'end' => '14:30'],
        ]];

        return [
            'days' => [
                'monday'    => $weekday,
                'tuesday'   => $weekday,
                'wednesday' => $weekday,
                'thursday'  => $weekday,
                'friday'    => $weekday,
                'saturday'  => $saturday,
                'sunday'    => ['off' => true],
            ],
            'grace_minutes'                  => 15,
            'daily_base_fee'                 => 150000,
            'overtime_rate_per_hour'         => 25000,
            'holiday_overtime_rate_per_hour' => 50000,
            'special_days'                   => [],
        ];
    }
}
