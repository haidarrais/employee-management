<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendance Controller
 * 
 * Handles attendance-related operations including:
 * - Retrieving attendance history with pagination
 * - Getting today's attendance status
 * 
 * @package App\Http\Controllers\Api\V1
 */
class AttendanceController extends Controller
{
    /**
     * Get attendance history for the authenticated user.
     * 
     * GET /api/v1/attendance/history
     * Query params: page (default: 1), per_page (default: 20, max: 100)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min($request->input('per_page', 20), 100);
        
        $user = $request->user();
        
        $records = AttendanceRecord::with(['qrCode:id,status'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Build the transformed data
        $transformed = [];
        foreach ($records as $record) {
            $transformed[] = [
                'id' => $record->id,
                'date' => $record->created_at->toDateString(),
                'time' => $record->created_at->toTimeString(),
                'status' => $record->status,
                'distance_meters' => round($record->distance_meters, 2),
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
                'rejection_reason' => $record->rejection_reason,
                'is_within_geofence' => $record->isWithinGeofence(),
            ];
        }

        return response()->json([
            'data' => $transformed,
            'pagination' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
                'next_page_url' => $records->nextPageUrl(),
                'prev_page_url' => $records->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Get today's attendance status for the authenticated user.
     * 
     * GET /api/v1/attendance/today
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get today's attendance records
        $todayRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->orderBy('created_at', 'desc')
            ->get();

        if ($todayRecords->isEmpty()) {
            return response()->json([
                'has_attended' => false,
                'message' => 'No attendance recorded for today',
                'data' => null,
            ]);
        }

        // Get the most recent attendance record
        $latestRecord = $todayRecords->first();
        
        // Check if there's a confirmed attendance
        $confirmedRecord = $todayRecords->firstWhere('status', AttendanceRecord::STATUS_CONFIRMED);

        return response()->json([
            'has_attended' => $confirmedRecord !== null,
            'message' => $confirmedRecord 
                ? 'Attendance confirmed for today' 
                : 'Attendance submission pending or rejected',
            'data' => [
                'records' => $todayRecords->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'time' => $record->created_at->toTimeString(),
                        'status' => $record->status,
                        'distance_meters' => round($record->distance_meters, 2),
                        'is_within_geofence' => $record->isWithinGeofence(),
                        'rejection_reason' => $record->rejection_reason,
                    ];
                }),
                'latest' => [
                    'id' => $latestRecord->id,
                    'time' => $latestRecord->created_at->toTimeString(),
                    'status' => $latestRecord->status,
                    'distance_meters' => round($latestRecord->distance_meters, 2),
                    'latitude' => $latestRecord->latitude,
                    'longitude' => $latestRecord->longitude,
                    'is_within_geofence' => $latestRecord->isWithinGeofence(),
                    'rejection_reason' => $latestRecord->rejection_reason,
                ],
                'total_submissions' => $todayRecords->count(),
                'confirmed_count' => $todayRecords->where('status', AttendanceRecord::STATUS_CONFIRMED)->count(),
                'rejected_count' => $todayRecords->where('status', AttendanceRecord::STATUS_REJECTED)->count(),
            ],
        ]);
    }
}