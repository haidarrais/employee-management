<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\QRCodeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum', 'ability:full_access'])->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Authentication Routes (API v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/auth')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // MFA verification - the only route a mfa_pending token may reach (besides logout)
        Route::post('/mfa/verify', [AuthController::class, 'verifyMfa'])
            ->middleware(['ability:mfa_pending', 'throttle:5,1'])
            ->name('auth.mfa.verify');
        Route::post('/logout', [AuthController::class, 'logout']);

        // Everything else requires a fully authenticated token (MFA completed)
        Route::middleware('ability:full_access')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/session/extend', [AuthController::class, 'sessionExtend']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| QR Code Routes (API v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/qr')->middleware(['auth:sanctum', 'ability:full_access'])->group(function () {
    // Generate QR code - admin/management/QR-generator only
    Route::post('/generate', [QRCodeController::class, 'generate'])
        ->middleware('role:admin,management,qr_generator')
        ->name('api.qr.generate');
    
    // Validate QR code - all authenticated users
    Route::post('/validate', [QRCodeController::class, 'verify'])
        ->name('qr.validate');
});

/*
|--------------------------------------------------------------------------
| Attendance Routes (API v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/attendance')->middleware(['auth:sanctum', 'ability:full_access'])->group(function () {
    // Submit attendance - handled by QRCodeController@verify
    // Get attendance history
    Route::get('/history', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'history'])
        ->name('api.attendance.history');
    
    // Get today's attendance status
    Route::get('/today', [\App\Http\Controllers\Api\V1\AttendanceController::class, 'today'])
        ->name('api.attendance.today');
});
