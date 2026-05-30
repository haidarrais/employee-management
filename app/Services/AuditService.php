<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an action to the audit log.
     *
     * @param string $action The action being logged
     * @param int|null $userId The user ID (null if not authenticated)
     * @param array $metadata Additional metadata for the action
     * @return AuditLog
     */
    public function log(string $action, ?int $userId = null, array $metadata = []): AuditLog
    {
        $ipAddress = request()->ip() ?? 'unknown';

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'metadata' => json_encode($metadata),
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Log a login event.
     */
    public function logLogin(int $userId, bool $success, array $extra = []): AuditLog
    {
        return $this->log(
            $success ? 'login_success' : 'login_failed',
            $success ? $userId : null,
            array_merge(['ip_address' => request()->ip()], $extra)
        );
    }

    /**
     * Log a logout event.
     */
    public function logLogout(int $userId): AuditLog
    {
        return $this->log('logout', $userId, [
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Log a QR code generation event.
     */
    public function logQrCodeGenerated(int $userId, int $qrCodeId): AuditLog
    {
        return $this->log('qr_code_generated', $userId, [
            'qr_code_id' => $qrCodeId,
        ]);
    }

    /**
     * Log a QR code usage event.
     */
    public function logQrCodeUsed(int $userId, int $qrCodeId, string $result): AuditLog
    {
        return $this->log('qr_code_used', $userId, [
            'qr_code_id' => $qrCodeId,
            'result' => $result,
        ]);
    }

    /**
     * Log an attendance submission event.
     */
    public function logAttendanceSubmitted(
        int $userId,
        int $attendanceId,
        string $status,
        array $location = []
    ): AuditLog {
        return $this->log('attendance_submitted', $userId, [
            'attendance_id' => $attendanceId,
            'status' => $status,
            'location' => $location,
        ]);
    }

    /**
     * Log a role change event.
     */
    public function logRoleChanged(int $adminUserId, int $targetUserId, string $oldRole, string $newRole): AuditLog
    {
        return $this->log('role_changed', $adminUserId, [
            'target_user_id' => $targetUserId,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);
    }
}