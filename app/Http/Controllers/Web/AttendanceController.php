<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function history(Request $request)
    {
        $user = Auth::user();
        
        $query = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
        
        // Filter by month if selected
        if ($request->filled('month')) {
            $query->whereYear('created_at', substr($request->month, 0, 4))
                  ->whereMonth('created_at', substr($request->month, 5, 2));
        }
        
        $records = $query->paginate(20);
        
        // Calculate stats
        $stats = $this->calculateStats($query->get());
        
        return view('attendance.history', compact('records', 'stats'));
    }
    
    private function calculateStats($records)
    {
        $total = $records->count();
        $present = $records->where('status', 'confirmed')->count();
        $absent = $records->where('status', 'rejected')->count();
        $rate = $total > 0 ? round(($present / $total) * 100) : 0;
        
        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'rate' => $rate,
        ];
    }
}