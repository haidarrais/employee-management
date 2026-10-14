<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\WorkingHoursService;
use Illuminate\Http\Request;

/**
 * ReportController
 *
 * Payroll / attendance report for admin and management.
 *
 * Fee calculation is always done live from the current employee rates
 * so that changing a rate is immediately reflected without re-seeding.
 * The stored `daily_fee` column is used only as a fallback when the
 * user record is unavailable.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly WorkingHoursService $whs,
    ) {}

    public function index(Request $request)
    {
        // ── Filters ──────────────────────────────────────────────
        $month      = $request->input('month', now()->format('Y-m'));
        $employeeId = $request->input('employee_id');

        [$year, $mon] = explode('-', $month);

        // ── Employee list for filter dropdown ────────────────────
        $employees = User::where('role', 'employee')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // ── Records ───────────────────────────────────────────────
        $query = AttendanceRecord::with('user:id,name,email,daily_rate,overtime_rate')
            ->where('status', AttendanceRecord::STATUS_CONFIRMED)
            ->whereYear('check_in_at', $year)
            ->whereMonth('check_in_at', $mon)
            ->whereNotNull('check_in_at');

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        } else {
            $query->whereHas('user', fn ($q) => $q->where('role', 'employee'));
        }

        $records = $query->orderBy('check_in_at')->get();

        // ── Recalculate fee live per employee-day ─────────────────
        // A day can now contain multiple sessions (for example lunch break),
        // so base pay and late deduction must be counted once per day, not once
        // per check-in/check-out pair. Each record still stays visible as a
        // session inside the day.
        $days = $records
            ->groupBy(fn ($record) => $record->user_id . '|' . $record->check_in_at->toDateString())
            ->map(function ($dayRecords) {
                $first = $dayRecords->sortBy('check_in_at')->first();
                $lastClosed = $dayRecords->filter(fn ($record) => $record->check_out_at !== null)
                    ->sortByDesc('check_out_at')
                    ->first();

                $checkIn = $first->check_in_at;
                $checkOut = $lastClosed?->check_out_at;
                $workedMinutes = $dayRecords->sum(fn ($record) => $record->workedMinutes());
                $lateMinutes = $this->whs->lateMinutes($checkIn);
                $overtimeMinutes = $this->whs->overtimeMinutes($checkOut, $checkIn);
                $fee = $this->whs->dailyFee($checkIn, $lateMinutes, $overtimeMinutes, $first->user, $workedMinutes);

                foreach ($dayRecords as $record) {
                    $record->computed_fee = 0;
                }

                $first->computed_fee = $fee;

                return [
                    'user' => $first->user,
                    'date' => $checkIn->toDateString(),
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'late_minutes' => $lateMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'worked_minutes' => $workedMinutes,
                    'computed_fee' => $fee,
                    'records' => $dayRecords->sortBy('check_in_at')->values(),
                ];
            });

        // ── Per-employee summary ──────────────────────────────────
        $summary = $days
            ->groupBy(fn ($day) => $day['user']->id)
            ->map(function ($employeeDays) {
                $user = $employeeDays->first()['user'];

                return [
                    'user'               => $user,
                    'days_attended'      => $employeeDays->count(),
                    'total_late_min'     => $employeeDays->sum('late_minutes'),
                    'total_overtime_min' => $employeeDays->sum('overtime_minutes'),
                    'total_fee'          => $employeeDays->sum('computed_fee'),
                    'records'            => $employeeDays->flatMap(fn ($day) => $day['records'])->values(),
                    'days'               => $employeeDays->values(),
                ];
            })
            ->sortBy('user.name')
            ->values();

        $schedule = $this->whs->getConfig();

        return view('reports.payroll', compact(
            'summary', 'employees',
            'month', 'employeeId', 'schedule',
        ));
    }
}
