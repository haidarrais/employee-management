<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\AuditService;
use App\Services\GeolocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin: workplace location and geofence tolerance.
 *
 * The workplace position and the accepted radius live in `system_configs`
 * under the key `workplace_coordinates` (see GeolocationService). This
 * controller is the only web surface that writes them.
 */
class LocationController extends Controller
{
    public function __construct(
        private readonly GeolocationService $geolocation,
    ) {}

    /**
     * Show the current workplace position and radius.
     */
    public function edit()
    {
        $config = $this->geolocation->getWorkplaceConfig();

        // Recent check-ins, so an operator can see real distances against the
        // configured radius and judge whether the fence is too tight or loose.
        $recent = AttendanceRecord::with('user:id,name')
            ->whereNotNull('distance_meters')
            ->orderBy('check_in_at', 'desc')
            ->limit(15)
            ->get();

        return view('admin.location', [
            'config' => $config,
            'recent' => $recent,
            'minRadius' => GeolocationService::MIN_GEOFENCE_RADIUS,
            'maxRadius' => GeolocationService::MAX_GEOFENCE_RADIUS,
            'defaultRadius' => GeolocationService::DEFAULT_GEOFENCE_RADIUS,
        ]);
    }

    /**
     * Save the workplace position and radius.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => [
                'required', 'numeric',
                'min:' . GeolocationService::MIN_GEOFENCE_RADIUS,
                'max:' . GeolocationService::MAX_GEOFENCE_RADIUS,
            ],
        ], [
            'radius_meters.min' => 'The tolerance must be at least :min meters. GPS on phones is accurate to roughly 5-10 meters, so a smaller fence would reject employees who are standing in the right place.',
            'radius_meters.max' => 'The tolerance may not exceed :max meters.',
            'latitude.between'  => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ]);

        $before = $this->geolocation->getWorkplaceConfig();

        $this->geolocation->setWorkplaceConfig(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['radius_meters'],
        );

        $after = $this->geolocation->getWorkplaceConfig();

        // Record both sides of the change: a geofence edit silently changes who
        // can check in, so the old value has to be recoverable from the log.
        app(AuditService::class)->log('workplace_location_updated', $request->user()?->id, [
            'before' => $before,
            'after'  => $after,
        ]);

        return redirect()->route('admin.location')
            ->with('success', 'Workplace location and tolerance saved.');
    }

    /**
     * Derive the workplace position from recent check-ins.
     *
     * Every check-in stores the coordinates it was recorded at, so the median
     * of recent accepted check-ins is a good estimate of where people actually
     * stand when they scan. The median is used rather than the mean so a single
     * outlier cannot drag the estimate away from the site.
     */
    public function suggest()
    {
        $points = AttendanceRecord::where('status', AttendanceRecord::STATUS_CONFIRMED)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('check_in_at', 'desc')
            ->limit(200)
            ->get(['latitude', 'longitude']);

        if ($points->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No confirmed check-ins yet, so there is nothing to derive a position from. Enter the coordinates by hand.',
            ], 422);
        }

        $median = function (array $values): float {
            sort($values);
            $n = count($values);
            $mid = intdiv($n, 2);

            return $n % 2
                ? (float) $values[$mid]
                : ((float) $values[$mid - 1] + (float) $values[$mid]) / 2;
        };

        $latitude  = $median($points->pluck('latitude')->map(fn ($v) => (float) $v)->all());
        $longitude = $median($points->pluck('longitude')->map(fn ($v) => (float) $v)->all());

        // How far the sample spreads. A tight spread means the estimate is
        // trustworthy; a wide one means check-ins are coming from several
        // places and no single point is really "the workplace".
        $spread = $points
            ->map(fn ($p) => $this->geolocation->calculateDistance(
                $latitude, $longitude, (float) $p->latitude, (float) $p->longitude
            ))
            ->sort()
            ->values();

        $p90 = $spread[(int) floor(0.9 * ($spread->count() - 1))] ?? 0;

        return response()->json([
            'ok'             => true,
            'latitude'       => round($latitude, 8),
            'longitude'      => round($longitude, 8),
            'samples'        => $points->count(),
            'median_offset'  => round($spread[intdiv($spread->count(), 2)] ?? 0, 1),
            'p90_offset'     => round($p90, 1),
            'suggested_radius' => round(max(
                (float) GeolocationService::MIN_GEOFENCE_RADIUS,
                min((float) GeolocationService::MAX_GEOFENCE_RADIUS, $p90 * 1.5)
            ), 1),
        ]);
    }
}
