<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\QRCode;
use App\Services\AuditService;
use App\Services\GeolocationService;
use App\Services\QRCodeService;
use App\Services\WorkingHoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * QRCode API Controller (v1)
 *
 * POST /api/v1/qr/generate  - generate a new QR code (admin/management only)
 * POST /api/v1/qr/validate  - validate a scanned token and record attendance
 */
class QRCodeController extends Controller
{
    /** Maximum accepted difference between client and server clocks, in seconds. */
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    public function __construct(
        private readonly QRCodeService       $qrCodeService,
        private readonly AuditService        $auditService,
        private readonly GeolocationService  $geolocation,
        private readonly WorkingHoursService $workingHours,
    ) {}

    // -------------------------------------------------------------------------
    // Generate
    // -------------------------------------------------------------------------

    /**
     * Generate a new attendance QR code.
     *
     * POST /api/v1/qr/generate
     * Authorization: Bearer {sanctum_token}
     * Roles: admin, management
     */
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->qrCodeService->isAvailable()) {
            return $this->serviceUnavailable();
        }

        try {
            $qrCodeRecord = $this->qrCodeService->generate($user->id);
            $qrCodeImage  = $this->qrCodeService->generateImage($qrCodeRecord);

            $this->auditService->log('qr_code_generated', $user->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'ip_address' => $request->ip(),
                'channel'    => 'api',
            ]);

            return response()->json([
                'data' => [
                    'qr_code_id'        => $qrCodeRecord->id,
                    'qr_code_image'     => $qrCodeImage,
                    'expires_at'        => $qrCodeRecord->expires_at->toIso8601String(),
                    'expires_in_seconds'=> $qrCodeRecord->expires_at->diffInSeconds(now()),
                    'validity_minutes'  => QRCode::VALIDITY_MINUTES,
                    'status'            => $qrCodeRecord->status,
                ],
                'message' => 'QR code generated successfully.',
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => [
                    'code'    => 'GENERATION_FAILED',
                    'message' => 'Failed to generate QR code. Please try again.',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // -------------------------------------------------------------------------
    // Validate / record attendance
    // -------------------------------------------------------------------------

    /**
     * Validate a scanned QR token and record attendance.
     *
     * POST /api/v1/qr/validate
     * Authorization: Bearer {sanctum_token}
     * Body: { token, latitude, longitude, timestamp }
     *
     * If the user has no open session today, the scan creates a check-in.
     * If the user already has an open session today, the scan closes it as a
     * check-out. This supports lunch breaks naturally:
     *   check in -> check out for lunch -> check in after lunch -> check out home.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'     => 'required|string|max:512',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'timestamp' => 'required|integer',
        ]);

        $user      = $request->user();
        $latitude  = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        // Reject requests whose client clock is far from ours (stale or replayed scans).
        if (abs(now()->timestamp - (int) $validated['timestamp']) > self::MAX_CLOCK_SKEW_SECONDS) {
            return $this->error('STALE_TIMESTAMP', 'Your device clock is out of sync. Please correct it and scan again.');
        }

        // The QR image carries the encrypted token; recover the raw one before hashing.
        $token = $this->qrCodeService->resolveToken($validated['token']);

        $qrCodeRecord = $token === null ? null : QRCode::where('token_hash', hash('sha256', $token))
            ->where('status', QRCode::STATUS_PENDING)
            ->first();

        if (!$qrCodeRecord) {
            $this->auditService->log('qr_validate_not_found', $user->id, [
                'ip_address' => $request->ip(),
            ]);

            return $this->error('INVALID_TOKEN', 'Invalid or already used QR code.');
        }

        // Cryptographic + expiry validation (does not consume the code yet).
        $result = $this->qrCodeService->validate($qrCodeRecord, $token);

        if (!$result['valid']) {
            $this->auditService->log('qr_validate_failed', $user->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'reason'     => $result['message'],
                'ip_address' => $request->ip(),
            ]);

            return $this->error('VALIDATION_FAILED', $result['message']);
        }

        // Geofence check. Fails closed: without a configured workplace we cannot verify presence.
        $geofenceRadius = $this->geolocation->getGeofenceRadius();
        $distanceMeters = $this->geolocation->getDistanceToWorkplace($latitude, $longitude);

        if ($distanceMeters === null) {
            $this->auditService->log('qr_validate_geofence_unconfigured', $user->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->error(
                'GEOFENCE_NOT_CONFIGURED',
                'Workplace location is not configured. Contact an administrator.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        if ($distanceMeters > $geofenceRadius) {
            $this->auditService->log('qr_validate_geofence_failed', $user->id, [
                'qr_code_id'     => $qrCodeRecord->id,
                'distance'       => $distanceMeters,
                'allowed_radius' => $geofenceRadius,
                'ip_address'     => $request->ip(),
            ]);

            return $this->error('GEOFENCE_VIOLATION', 'You are outside the allowed workplace area.', details: [
                'distance_meters' => round($distanceMeters, 2),
                'allowed_radius'  => $geofenceRadius,
            ]);
        }

        $openSession = AttendanceRecord::where('user_id', $user->id)
            ->where('status', AttendanceRecord::STATUS_CONFIRMED)
            ->whereDate('check_in_at', now()->toDateString())
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();

        if ($openSession) {
            return $this->recordCheckOut($request, $qrCodeRecord, $openSession, $latitude, $longitude, $distanceMeters);
        }

        return $this->recordCheckIn($request, $qrCodeRecord, $latitude, $longitude, $distanceMeters);
    }

    private function recordCheckIn(
        Request $request,
        QRCode $qrCodeRecord,
        float $latitude,
        float $longitude,
        float $distanceMeters,
    ): JsonResponse {
        $user = $request->user();

        $attendanceRecord = DB::transaction(function () use ($qrCodeRecord, $user, $latitude, $longitude, $distanceMeters) {
            if (!$this->qrCodeService->claim($qrCodeRecord)) {
                return null;
            }

            $checkInAt = now();

            return AttendanceRecord::create([
                'user_id'         => $user->id,
                'qr_code_id'      => $qrCodeRecord->id,
                'latitude'        => $latitude,
                'longitude'       => $longitude,
                'distance_meters' => $distanceMeters,
                'status'          => AttendanceRecord::STATUS_CONFIRMED,
                'check_in_at'     => $checkInAt,
                'late_minutes'    => $this->workingHours->lateMinutes($checkInAt),
            ]);
        });

        if ($attendanceRecord === null) {
            $this->auditService->log('qr_validate_lost_race', $user->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'ip_address' => $request->ip(),
                'action'     => 'check_in',
            ]);

            return $this->error('INVALID_TOKEN', 'Invalid or already used QR code.');
        }

        $this->auditService->log('attendance_check_in_success', $user->id, [
            'qr_code_id'       => $qrCodeRecord->id,
            'attendance_id'    => $attendanceRecord->id,
            'distance_meters'  => $distanceMeters,
            'ip_address'       => $request->ip(),
        ]);

        return response()->json([
            'valid'   => true,
            'action'  => 'check_in',
            'message' => 'Checked in successfully.',
            'data'    => [
                'attendance_id' => $attendanceRecord->id,
                'recorded_at'   => $attendanceRecord->check_in_at->toIso8601String(),
                'check_in_at'   => $attendanceRecord->check_in_at->toIso8601String(),
                'late_minutes'  => $attendanceRecord->late_minutes,
                'location'      => [
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'distance_meters' => round($distanceMeters, 2),
                ],
            ],
        ]);
    }

    private function recordCheckOut(
        Request $request,
        QRCode $qrCodeRecord,
        AttendanceRecord $openSession,
        float $latitude,
        float $longitude,
        float $distanceMeters,
    ): JsonResponse {
        $user = $request->user();

        $attendanceRecord = DB::transaction(function () use ($qrCodeRecord, $openSession, $latitude, $longitude, $distanceMeters, $user) {
            if (!$this->qrCodeService->claim($qrCodeRecord)) {
                return null;
            }

            $session = AttendanceRecord::whereKey($openSession->id)
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return null;
            }

            $checkOutAt = now();
            $computed = $this->workingHours->compute($session->check_in_at, $checkOutAt, $user);

            $session->update([
                'check_out_at'              => $checkOutAt,
                'check_out_latitude'        => $latitude,
                'check_out_longitude'       => $longitude,
                'check_out_distance_meters' => $distanceMeters,
                'overtime_minutes'          => $computed['overtime_minutes'],
                'daily_fee'                 => $computed['daily_fee'],
            ]);

            return $session->refresh();
        });

        if ($attendanceRecord === null) {
            $this->auditService->log('qr_validate_lost_race', $user->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'ip_address' => $request->ip(),
                'action'     => 'check_out',
            ]);

            return $this->error('INVALID_TOKEN', 'Invalid or already used QR code.');
        }

        $this->auditService->log('attendance_check_out_success', $user->id, [
            'qr_code_id'       => $qrCodeRecord->id,
            'attendance_id'    => $attendanceRecord->id,
            'distance_meters'  => $distanceMeters,
            'worked_minutes'   => $attendanceRecord->workedMinutes(),
            'ip_address'       => $request->ip(),
        ]);

        return response()->json([
            'valid'   => true,
            'action'  => 'check_out',
            'message' => 'Checked out successfully.',
            'data'    => [
                'attendance_id'    => $attendanceRecord->id,
                'recorded_at'      => $attendanceRecord->check_out_at->toIso8601String(),
                'check_in_at'      => $attendanceRecord->check_in_at->toIso8601String(),
                'check_out_at'     => $attendanceRecord->check_out_at->toIso8601String(),
                'worked_minutes'   => $attendanceRecord->workedMinutes(),
                'overtime_minutes' => $attendanceRecord->overtime_minutes,
                'location'         => [
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'distance_meters' => round($distanceMeters, 2),
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function error(string $code, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['valid' => false, 'error' => $error], $status);
    }

    private function serviceUnavailable(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code'    => 'SERVICE_UNAVAILABLE',
                'message' => 'QR code generation service is temporarily unavailable.',
            ],
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
