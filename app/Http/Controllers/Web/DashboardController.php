<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $recentAttendance = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('check_in_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact('recentAttendance'));
    }
}
