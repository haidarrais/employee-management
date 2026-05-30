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

        // ── Recalculate fee live for every record ─────────────────
        // This ensures the report always reflects the current employee
        // rate, even if the rate was changed after the record was saved.
        foreach ($records as $record) {
            $workedMinutes = $record->check_out_at
                ? (int) $record->check_in_at->diffInMinutes($record->check_out_at)
                : 0;

            $record->computed_fee = $this->whs->dailyFee(
                $record->check_in_at,
                $record->late_minutes,
                $record->overtime_minutes,
                $record->user,
                $workedMinutes,
            );
        }

        // ── Per-employee summary ──────────────────────────────────
        $summary = $records
            ->groupBy('user_id')
            ->map(function ($recs) {
                $user = $recs->first()->user;
                return [
                    'user'               => $user,
                    'days_attended'      => $recs->count(),
                    'total_late_min'     => $recs->sum('late_minutes'),
                    'total_overtime_min' => $recs->sum('overtime_minutes'),
                    // Sum the live-computed fee, not the stored column
                    'total_fee'          => $recs->sum('computed_fee'),
                    'records'            => $recs,
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
