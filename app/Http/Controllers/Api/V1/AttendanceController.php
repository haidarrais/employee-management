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

        $transformed = [];
        foreach ($records as $record) {
            $transformed[] = $this->transformRecord($record);
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

        $todayRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->orderBy('check_in_at', 'desc')
            ->get();

        if ($todayRecords->isEmpty()) {
            return response()->json([
                'has_attended' => false,
                'has_open_session' => false,
                'message' => 'No attendance recorded for today',
                'data' => null,
            ]);
        }

        $latestRecord = $todayRecords->first();
        $openSession = $todayRecords->first(fn ($record) => $record->isOpen());
        $confirmedRecords = $todayRecords->where('status', AttendanceRecord::STATUS_CONFIRMED);

        return response()->json([
            'has_attended' => $confirmedRecords->isNotEmpty(),
            'has_open_session' => $openSession !== null,
            'message' => $openSession
                ? 'You are currently checked in'
                : 'All current sessions are checked out',
            'data' => [
                'records' => $todayRecords->map(fn ($record) => $this->transformRecord($record))->values(),
                'latest' => $this->transformRecord($latestRecord),
                'open_session' => $openSession ? $this->transformRecord($openSession) : null,
                'total_submissions' => $todayRecords->count(),
                'confirmed_count' => $confirmedRecords->count(),
                'rejected_count' => $todayRecords->where('status', AttendanceRecord::STATUS_REJECTED)->count(),
                'completed_sessions' => $confirmedRecords->whereNotNull('check_out_at')->count(),
            ],
        ]);
    }

    private function transformRecord(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'date' => optional($record->check_in_at ?? $record->created_at)->toDateString(),
            'time' => optional($record->check_in_at ?? $record->created_at)->toTimeString(),
            'check_in_at' => $record->check_in_at?->toIso8601String(),
            'check_out_at' => $record->check_out_at?->toIso8601String(),
            'check_in_time' => $record->check_in_at?->format('H:i:s'),
            'check_out_time' => $record->check_out_at?->format('H:i:s'),
            'worked_minutes' => $record->workedMinutes(),
            'status' => $record->status,
            'distance_meters' => round($record->distance_meters ?? 0, 2),
            'check_out_distance_meters' => $record->check_out_distance_meters === null ? null : round($record->check_out_distance_meters, 2),
            'latitude' => $record->latitude,
            'longitude' => $record->longitude,
            'check_out_latitude' => $record->check_out_latitude,
            'check_out_longitude' => $record->check_out_longitude,
            'rejection_reason' => $record->rejection_reason,
            'is_open' => $record->isOpen(),
            'is_within_geofence' => $record->isWithinGeofence(),
            'is_checkout_within_geofence' => $record->check_out_at ? $record->isCheckoutWithinGeofence() : null,
        ];
    }
}
