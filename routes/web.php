<?php

use App\Http\Controllers\Web\AuthController as WebAuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\AttendanceController as WebAttendanceController;
use App\Http\Controllers\Web\AdminController;
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
    Route::post('/login', [WebAuthController::class, 'login'])->middleware('web');
    
    Route::get('/mfa-verify', [WebAuthController::class, 'showMfaForm'])->name('mfa.verify.form');
    Route::post('/mfa-verify', [WebAuthController::class, 'verifyMfa'])->name('mfa.verify')->middleware('web');
});

// Authenticated routes - use web middleware for session support
Route::middleware(['auth', 'web'])->group(function () {
    // Logout
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/mfa', [ProfileController::class, 'updateMfa'])->name('profile.mfa');
    
    // Attendance API endpoints (session-based for web)
    Route::get('/attendance/today', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('/attendance/history', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'history'])->name('attendance.history');
    
    // Attendance
    Route::get('/attendance/history', [WebAttendanceController::class, 'history'])->name('attendance.history');
    
    // Admin routes (admin only)
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
    });
    
    // QR Code generation (admin/management only)
    Route::get('/qr/generate', [QrCodeController::class, 'generate'])
        ->middleware('role:admin,management')
        ->name('qr.generate');

    // Reports & schedule (admin/management only)
    Route::middleware('role:admin,management')->group(function () {
        Route::get('/reports/payroll', [ReportController::class, 'index'])->name('reports.payroll');
        Route::get('/reports/schedule', [ScheduleController::class, 'edit'])->name('reports.schedule');
        Route::put('/reports/schedule', [ScheduleController::class, 'update'])->name('reports.schedule.update');
        Route::post('/reports/schedule/special-days', [ScheduleController::class, 'addSpecialDay'])->name('reports.schedule.special-days.add');
        Route::delete('/reports/schedule/special-days', [ScheduleController::class, 'removeSpecialDay'])->name('reports.schedule.special-days.remove');
    });

    // Audit logs (admin)
    Route::middleware('role:admin')->group(function () {
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

