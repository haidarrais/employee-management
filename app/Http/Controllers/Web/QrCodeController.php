<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\GeolocationService;
use App\Services\QRCodeService;
use App\Models\QRCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web controller for QR code generation.
 *
 * Handles the admin/management-facing page that displays a live,
 * auto-refreshing QR code for employee attendance scanning.
 *
 * Access is restricted to admin and management roles via route middleware.
 */
class QrCodeController extends Controller
{
    public function __construct(
        private readonly QRCodeService      $qrCodeService,
        private readonly AuditService       $auditService,
        private readonly GeolocationService $geolocation,
    ) {}

    /**
     * Generate a new QR code and display it.
     *
     * GET /qr/generate
     */
    public function generate(Request $request): View|RedirectResponse
    {
        if (!$this->qrCodeService->isAvailable()) {
            return redirect()->route('dashboard')
                ->with('error', 'QR code service is currently unavailable. Please check your encryption configuration.');
        }

        try {
            $qrCodeRecord = $this->qrCodeService->generate($request->user()->id);
            $qrCodeImage  = $this->qrCodeService->generateImage($qrCodeRecord);

            $this->auditService->log('qr_code_generated', $request->user()->id, [
                'qr_code_id' => $qrCodeRecord->id,
                'ip_address' => $request->ip(),
                'channel'    => 'web',
            ]);

            return view('qr.generate', [
                'qrCodeImage'     => $qrCodeImage,
                'qrCode'          => $qrCodeRecord,
                'expiresAt'       => $qrCodeRecord->expires_at->toIso8601String(),
                'validityMinutes' => \App\Models\QRCode::VALIDITY_MINUTES,
                'geofenceRadius'  => $this->geolocation->getGeofenceRadius(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to generate QR code. Please try again.');
        }
    }

    /**
     * Return the current state of a QR code displayed by this station.
     *
     * The station polls this endpoint so a consumed single-use code can be
     * replaced immediately without operator interaction.
     */
    public function status(Request $request, QRCode $qrCode): JsonResponse
    {
        abort_unless((int) $qrCode->generated_by === (int) $request->user()->id, 404);

        if ($qrCode->isExpired()) {
            $qrCode->markAsExpired();
            $qrCode->refresh();
        }

        return response()->json([
            'id'      => $qrCode->id,
            'status'  => $qrCode->status,
            'used_at' => $qrCode->used_at?->toIso8601String(),
        ]);
    }
}
