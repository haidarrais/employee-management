<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\GeolocationService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(GeolocationService $geolocation)
    {
        $user = Auth::user();

        $recentAttendance = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('check_in_at', 'desc')
            ->limit(10)
            ->get();

        // The geofence radius is an operator setting, so the check-in
        // instructions have to state the real value rather than a constant.
        return view('dashboard.index', [
            'recentAttendance' => $recentAttendance,
            'geofenceRadius'   => $geolocation->getGeofenceRadius(),
        ]);
    }
}
