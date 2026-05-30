<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Services\WorkingHoursService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // Working hours schedule
    // ─────────────────────────────────────────────────────────────

    public function edit(WorkingHoursService $whs)
    {
        return view('reports.schedule', ['schedule' => $whs->getConfig()]);
    }

    public function update(Request $request)
    {
        $days  = WorkingHoursService::DAY_NAMES;
        $rules = [
            'grace_minutes'                  => 'required|integer|min:0|max:120',
            'daily_base_fee'                 => 'required|numeric|min:0',
            'overtime_rate_per_hour'         => 'required|numeric|min:0',
            'holiday_overtime_rate_per_hour' => 'required|numeric|min:0',
        ];

        foreach ($days as $day) {
            if (!$request->boolean("day_{$day}_off")) {
                $rules["day_{$day}_seg0_start"] = 'required|date_format:H:i';
                $rules["day_{$day}_seg0_end"]   = "required|date_format:H:i|after:day_{$day}_seg0_start";
                $rules["day_{$day}_seg1_start"] = "nullable|date_format:H:i|after:day_{$day}_seg0_end";
                $rules["day_{$day}_seg1_end"]   = "nullable|date_format:H:i|after:day_{$day}_seg1_start";
            }
        }

        $request->validate($rules);

        $dayConfigs = [];
        foreach ($days as $day) {
            if ($request->boolean("day_{$day}_off")) {
                $dayConfigs[$day] = ['off' => true];
                continue;
            }

            $segments = [[
                'start' => $request->input("day_{$day}_seg0_start"),
                'end'   => $request->input("day_{$day}_seg0_end"),
            ]];

            $s1 = $request->input("day_{$day}_seg1_start");
            $e1 = $request->input("day_{$day}_seg1_end");
            if ($s1 && $e1) {
                $segments[] = ['start' => $s1, 'end' => $e1];
            }

            $dayConfigs[$day] = ['segments' => $segments];
        }

        // Preserve existing special_days when saving the schedule
        $existing     = $this->loadCurrentConfig();
        $specialDays  = $existing['special_days'] ?? [];

        $config = [
            'days'                           => $dayConfigs,
            'grace_minutes'                  => (int)   $request->grace_minutes,
            'daily_base_fee'                 => (float) $request->daily_base_fee,
            'overtime_rate_per_hour'         => (float) $request->overtime_rate_per_hour,
            'holiday_overtime_rate_per_hour' => (float) $request->holiday_overtime_rate_per_hour,
            'special_days'                   => $specialDays,
        ];

        $this->saveConfig($config);

        return redirect()->route('reports.schedule')->with('success', 'Schedule saved.');
    }

    // ─────────────────────────────────────────────────────────────
    // Special days (holidays / overtime Sundays)
    // ─────────────────────────────────────────────────────────────

    public function addSpecialDay(Request $request)
    {
        $request->validate([
            'date'         => 'required|date_format:Y-m-d',
            'type'         => 'required|in:holiday,overtime_sunday',
            'name'         => 'required|string|max:100',
            'rate_per_hour'=> 'nullable|numeric|min:0',
        ]);

        $config = $this->loadCurrentConfig();

        $entry = [
            'type' => $request->type,
            'name' => $request->name,
        ];

        if ($request->filled('rate_per_hour')) {
            $entry['rate_per_hour'] = (float) $request->rate_per_hour;
        }

        $config['special_days'][$request->date] = $entry;

        // Keep sorted by date
        ksort($config['special_days']);

        $this->saveConfig($config);

        return redirect()->route('reports.schedule', ['#special-days'])
            ->with('success', 'Special day "' . $request->name . '" added.');
    }

    public function removeSpecialDay(Request $request)
    {
        $request->validate(['date' => 'required|date_format:Y-m-d']);

        $config = $this->loadCurrentConfig();
        unset($config['special_days'][$request->date]);
        $this->saveConfig($config);

        return redirect()->route('reports.schedule', ['#special-days'])
            ->with('success', 'Special day removed.');
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function loadCurrentConfig(): array
    {
        $row = SystemConfig::find('working_hours');
        if ($row) {
            $decoded = json_decode($row->value, true);
            if (is_array($decoded)) return $decoded;
        }
        return WorkingHoursService::defaultConfig();
    }

    private function saveConfig(array $config): void
    {
        SystemConfig::updateOrCreate(
            ['key' => 'working_hours'],
            ['value' => json_encode($config), 'description' => 'Per-day work schedule + special days.']
        );
    }
}
