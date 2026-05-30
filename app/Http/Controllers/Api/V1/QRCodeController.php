<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QRCode;
use App\Models\SystemConfig;
use App\Services\AuditService;
use App\Services\QRCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * QRCode API Controller (v1)
 *
 * POST /api/v1/qr/generate  – generate a new QR code (admin/management only)
 * POST /api/v1/qr/validate  – validate a scanned token and record attendance
 */
class QRCodeController extends Controller
{
    public function __construct(
        private readonly QRCodeService $qrCodeService,
        private readonly AuditService  $auditService,
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
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'     => 'required|string',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'timestamp' => 'required|integer',
        ]);

        $user      = $request->user();
        $token     = $validated['token'];
        $latitude  = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        // Look up the QR record by token hash (never store raw tokens)
        $qrCodeRecord = QRCode::where('token_hash', hash('sha256', $token))
            ->where('status', QRCode::STATUS_PENDING)
            ->first();

        if (!$qrCodeRecord) {
            $this->auditService->log('qr_validate_not_found', $user->id, [
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'valid' => false,
                'error' => ['code' => 'INVALID_TOKEN', 'message' => 'Invalid or already used QR code.'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Cryptographic validation + mark as used
        $result = $this->qrCodeService->validate($qrCodeRecord, $token);

        if (!$result['valid']) {
            $this->auditService->log('qr_validate_failed', $user->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'reason'     => $result['message'],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'valid' => false,
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => $result['message']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Geofence check (optional – skipped when workplace coords not configured)
        $distanceMeters = null;
        $geofenceRadius = QRCode::GEOFENCE_RADIUS_METERS;

        $workplaceCoords = $this->getWorkplaceCoordinates();

        if ($workplaceCoords !== null) {
            $distanceMeters = $this->haversineDistance(
                $latitude, $longitude,
                $workplaceCoords['latitude'], $workplaceCoords['longitude']
            );

            if ($distanceMeters > $geofenceRadius) {
                $this->auditService->log('qr_validate_geofence_failed', $user->id, [
                    'qr_code_id'     => $qrCodeRecord->id,
                    'distance'       => $distanceMeters,
                    'allowed_radius' => $geofenceRadius,
                    'ip_address'     => $request->ip(),
                ]);

                return response()->json([
                    'valid' => false,
                    'error' => [
                        'code'    => 'GEOFENCE_VIOLATION',
                        'message' => 'You are outside the allowed workplace area.',
                        'details' => [
                            'distance_meters' => round($distanceMeters, 2),
                            'allowed_radius'  => $geofenceRadius,
                        ],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Persist attendance record inside a transaction
        $attendanceRecord = DB::transaction(
            fn () => \App\Models\AttendanceRecord::create([
                'user_id'         => $user->id,
                'qr_code_id'      => $qrCodeRecord->id,
                'latitude'        => $latitude,
                'longitude'       => $longitude,
                'distance_meters' => $distanceMeters,
                'status'          => 'confirmed',
                'recorded_at'     => now(),
            ])
        );

        $this->auditService->log('qr_validate_success', $user->id, [
            'qr_code_id'       => $qrCodeRecord->id,
            'attendance_id'    => $attendanceRecord->id,
            'distance_meters'  => $distanceMeters,
            'ip_address'       => $request->ip(),
        ]);

        return response()->json([
            'valid'   => true,
            'message' => 'Attendance recorded successfully.',
            'data'    => [
                'attendance_id' => $attendanceRecord->id,
                'recorded_at'   => $attendanceRecord->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
                'location'      => [
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'distance_meters' => $distanceMeters !== null ? round($distanceMeters, 2) : null,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve workplace coordinates from system config.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function getWorkplaceCoordinates(): ?array
    {
        $config = SystemConfig::where('key', 'workplace_coordinates')->first();

        if (!$config) {
            return null;
        }

        $coords = json_decode($config->value, true);

        if (!isset($coords['latitude'], $coords['longitude'])) {
            return null;
        }

        return $coords;
    }

    /**
     * Calculate the great-circle distance between two points (Haversine formula).
     *
     * @return float Distance in metres
     */
    private function haversineDistance(
        float $lat1, float $lon1,
        float $lat2, float $lon2,
    ): float {
        $R    = 6_371_000; // Earth radius in metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
