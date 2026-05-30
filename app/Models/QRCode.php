<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * QRCode Model
 * 
 * Represents a dynamic QR code for attendance verification.
 * QR codes are single-use and expire after 5 minutes.
 * 
 * @property int $id
 * @property int $generated_by
 * @property string $encrypted_token
 * @property string $token_hash
 * @property string $status
 * @property \Carbon\Carbon $generated_at
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class QRCode extends Model
{
    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_USED = 'used';
    const STATUS_EXPIRED = 'expired';

    /**
     * QR code validity duration in minutes
     */
    const VALIDITY_MINUTES = 5;

    /**
     * Geofence radius in meters for attendance validation
     */
    const GEOFENCE_RADIUS_METERS = 10;

    /**
     * The table associated with the model.
     * Explicitly set because Laravel pluralises "QRCode" as "q_r_codes".
     *
     * @var string
     */
    protected $table = 'qr_codes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'generated_by',
        'encrypted_token',
        'token_hash',
        'status',
        'generated_at',
        'expires_at',
        'used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'generated_by' => 'integer',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Get the user who generated this QR code.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the attendance record associated with this QR code (if used).
     */
    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class);
    }

    /**
     * Check if the QR code is valid (not expired and still pending).
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at->isFuture();
    }

    /**
     * Mark the QR code as used.
     */
    public function markAsUsed(): void
    {
        $this->update([
            'status' => self::STATUS_USED,
            'used_at' => now(),
        ]);
    }

    /**
     * Mark the QR code as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Check if the QR code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() && $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the QR code has already been used.
     */
    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /**
     * Scope to get only pending QR codes.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get only expired QR codes.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope to get only used QR codes.
     */
    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }
}