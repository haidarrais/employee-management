<?php

use App\Http\Controllers\Web\AuthController as WebAuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\AttendanceController as WebAttendanceController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\QrCodeController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

// Public routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->middleware(['web', 'throttle:5,1']);
    
    Route::get('/mfa-verify', [WebAuthController::class, 'showMfaForm'])->name('mfa.verify.form');
    Route::post('/mfa-verify', [WebAuthController::class, 'verifyMfa'])->name('mfa.verify')->middleware(['web', 'throttle:5,1']);
});

// Authenticated routes - use web middleware for session support
Route::middleware(['auth', 'web'])->group(function () {
    // Logout
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

    // Password confirmation (sudo mode) — must be inside auth but NOT behind password.confirm itself
    Route::get('/password/confirm', [WebAuthController::class, 'showConfirmPassword'])->name('password.confirm');
    Route::post('/password/confirm', [WebAuthController::class, 'confirmPassword']);

    // Dashboard — open, no password confirmation needed
    // (employees use this to scan QR on the shared office computer)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // QR Code generation — open to admin/management/QR-generator WITHOUT password confirmation
    // QR-generator users may only generate QR codes and do not get payroll/report access.
    Route::middleware('role:admin,management,qr_generator')->group(function () {
        Route::get('/qr/generate', [QrCodeController::class, 'generate'])
            ->name('qr.generate');
        Route::get('/qr/{qrCode}/status', [QrCodeController::class, 'status'])
            ->name('qr.status');
    });

    // Profile — requires password confirmation (personal sensitive data)
    Route::middleware('password.confirm')->group(function () {
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profile/mfa', [ProfileController::class, 'updateMfa'])->name('profile.mfa');

        // MFA enrolment — enabling only happens after a code is verified
        Route::get('/profile/mfa/setup', [ProfileController::class, 'showMfaSetup'])->name('mfa.setup');
        Route::post('/profile/mfa/setup', [ProfileController::class, 'confirmMfaSetup'])->name('mfa.setup.confirm');
    });

    // Attendance history — open (employees check their own records)
    Route::get('/attendance/today', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('/attendance/history', [WebAttendanceController::class, 'history'])->name('attendance.history');

    // ── Confidential routes — password confirmation required ──────
    // Admin routes
    Route::middleware(['role:admin', 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');

        // Workplace position and geofence tolerance.
        Route::get('/location', [LocationController::class, 'edit'])->name('location');
        Route::put('/location', [LocationController::class, 'update'])->name('location.update');
        Route::get('/location/suggest', [LocationController::class, 'suggest'])->name('location.suggest');
    });

    // Superadmin-only maintenance routes. These are intentionally outside
    // the normal admin group because regular admins must not delete records.
    Route::middleware(['role:superadmin', 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/attendance-maintenance', [AdminController::class, 'attendanceMaintenance'])->name('attendance.maintenance');
        Route::get('/attendance-maintenance/export', [AdminController::class, 'exportAttendance'])->name('attendance.export');
        Route::delete('/attendance-maintenance', [AdminController::class, 'cleanupAttendance'])->name('attendance.cleanup');
    });

    // Reports & schedule
    Route::middleware(['role:admin,management', 'password.confirm'])->group(function () {
        Route::get('/reports/payroll', [ReportController::class, 'index'])->name('reports.payroll');
        Route::get('/reports/schedule', [ScheduleController::class, 'edit'])->name('reports.schedule');
        Route::put('/reports/schedule', [ScheduleController::class, 'update'])->name('reports.schedule.update');
        Route::post('/reports/schedule/special-days', [ScheduleController::class, 'addSpecialDay'])->name('reports.schedule.special-days.add');
        Route::delete('/reports/schedule/special-days', [ScheduleController::class, 'removeSpecialDay'])->name('reports.schedule.special-days.remove');
    });

    // Audit logs
    Route::middleware(['role:admin', 'password.confirm'])->group(function () {
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit.logs');
    });
});

// Home redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

