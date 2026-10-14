<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        $query = User::query()->orderBy('created_at', 'desc');
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        
        $users = $query->paginate(20);
        
        return view('admin.users', compact('users'));
    }

    public function editUser(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        $currentUser = $request->user();

        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            abort(403, 'Only superadmins can edit superadmin accounts.');
        }

        $allowedRoles = [
            User::ROLE_ADMIN,
            User::ROLE_MANAGEMENT,
            User::ROLE_QR_GENERATOR,
            User::ROLE_EMPLOYEE,
        ];

        if ($currentUser->isSuperAdmin()) {
            array_unshift($allowedRoles, User::ROLE_SUPERADMIN);
        }

        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role'          => ['required', Rule::in($allowedRoles)],
            'password'      => 'nullable|string|min:8',
            'daily_rate'    => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'name'          => $request->name,
            'email'         => $request->email,
            'role'          => $request->role,
            'daily_rate'    => $request->filled('daily_rate')    ? (float) $request->daily_rate    : null,
            'overtime_rate' => $request->filled('overtime_rate') ? (float) $request->overtime_rate : null,
        ];

        if ($request->filled('password')) {
            $data['password_hash'] = Hash::make($request->password);
        }

        $user->update($data);

        // An admin clearing MFA must wipe the enrolled secret too, otherwise
        // the account would still demand a code it can no longer produce.
        if ($request->boolean('reset_mfa') && ($user->mfa_enabled || $user->mfa_secret)) {
            $user->update([
                'mfa_enabled' => false,
                'mfa_secret'  => null,
            ]);

            app(\App\Services\AuditService::class)->log(
                'mfa_reset_by_admin',
                auth()->id(),
                ['target_user_id' => $user->id]
            );
        }

        if ($user->wasChanged('role')) {
            app(\App\Services\AuditService::class)->log(
                'role_changed',
                auth()->id(),
                [
                    'target_user_id' => $user->id,
                    'old_role'       => $user->getOriginal('role'),
                    'new_role'       => $user->role,
                ]
            );
        }

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account');
        }

        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only superadmins can delete superadmin accounts.');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully');
    }

    public function attendanceMaintenance(Request $request)
    {
        $records = $this->attendanceQuery($request)
            ->orderByDesc('check_in_at')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->appends($request->query());

        $employees = User::whereIn('role', [User::ROLE_EMPLOYEE])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.attendance-maintenance', [
            'records' => $records,
            'employees' => $employees,
            'matchingCount' => $this->attendanceQuery($request)->count(),
        ]);
    }

    public function exportAttendance(Request $request): StreamedResponse
    {
        $filename = 'attendance-records-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($out, [
                'ID',
                'Employee',
                'Email',
                'Status',
                'Check In',
                'Check Out',
                'Worked Minutes',
                'Late Minutes',
                'Overtime Minutes',
                'Daily Fee',
                'Check-in Latitude',
                'Check-in Longitude',
                'Check-in Distance Meters',
                'Check-out Latitude',
                'Check-out Longitude',
                'Check-out Distance Meters',
                'Rejection Reason',
            ]);

            $this->attendanceQuery($request)
                ->orderBy('check_in_at')
                ->orderBy('created_at')
                ->chunk(200, function ($records) use ($out) {
                    foreach ($records as $record) {
                        fputcsv($out, [
                            $record->id,
                            $record->user?->name,
                            $record->user?->email,
                            $record->status,
                            $record->check_in_at?->toDateTimeString(),
                            $record->check_out_at?->toDateTimeString(),
                            $record->workedMinutes(),
                            $record->late_minutes,
                            $record->overtime_minutes,
                            $record->daily_fee,
                            $record->latitude,
                            $record->longitude,
                            $record->distance_meters,
                            $record->check_out_latitude,
                            $record->check_out_longitude,
                            $record->check_out_distance_meters,
                            $record->rejection_reason,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function cleanupAttendance(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'status' => ['nullable', Rule::in([
                AttendanceRecord::STATUS_CONFIRMED,
                AttendanceRecord::STATUS_PENDING,
                AttendanceRecord::STATUS_REJECTED,
            ])],
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'confirm_text' => 'required|in:CLEANUP',
        ]);

        $hasFilter = $request->filled('user_id')
            || $request->filled('status')
            || $request->filled('from_date')
            || $request->filled('to_date');

        if (!$hasFilter) {
            return back()->with('error', 'Choose at least one filter before cleaning attendance records.');
        }

        $ids = $this->attendanceQuery($request)->pluck('id');
        $deleted = $ids->isEmpty() ? 0 : AttendanceRecord::whereIn('id', $ids)->delete();

        app(\App\Services\AuditService::class)->log('attendance_records_cleanup', auth()->id(), [
            'deleted_count' => $deleted,
            'filters' => $request->only(['user_id', 'status', 'from_date', 'to_date']),
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.attendance.maintenance', $request->only(['user_id', 'status', 'from_date', 'to_date']))
            ->with('success', "Deleted {$deleted} attendance record(s).");
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');
        
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $logs = $query->paginate(50);
        
        return view('admin.audit-logs', compact('logs'));
    }

    private function attendanceQuery(Request $request)
    {
        $query = AttendanceRecord::with('user:id,name,email,role')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_EMPLOYEE));

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('check_in_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('check_in_at', '<=', $request->to_date);
        }

        return $query;
    }
}
