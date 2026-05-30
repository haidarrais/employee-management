<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceRecord Model
 * 
 * Represents an employee's attendance submission with geolocation data.
 * Each record is linked to a user and a QR code used for submission.
 * 
 * @property int $id
 * @property int $user_id
 * @property int $qr_code_id
 * @property float $latitude
 * @property float $longitude
 * @property float $distance_meters
 * @property string $status
 * @property string|null $rejection_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 * @property-read QRCode $qrCode
 */
class AttendanceRecord extends Model
{
    /**
     * Status constants
     */
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PENDING = 'pending';

    /**
     * Geofence radius in meters
     */
    const GEOFENCE_RADIUS_METERS = 10;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'qr_code_id',
        'latitude',
        'longitude',
        'distance_meters',
        'status',
        'rejection_reason',
        'check_in_at',
        'check_out_at',
        'late_minutes',
        'overtime_minutes',
        'daily_fee',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id'          => 'integer',
        'qr_code_id'       => 'integer',
        'latitude'         => 'float',
        'longitude'        => 'float',
        'distance_meters'  => 'float',
        'check_in_at'      => 'datetime',
        'check_out_at'     => 'datetime',
        'late_minutes'     => 'integer',
        'overtime_minutes' => 'integer',
        'daily_fee'        => 'float',
    ];

    /**
     * Get the user who submitted this attendance record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the QR code used for this attendance submission.
     */
    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QRCode::class);
    }

    /**
     * Check if the attendance is within the geofence radius.
     * 
     * Compares the recorded distance against the configured geofence radius.
     * 
     * @return bool True if within geofence, false otherwise
     */
    public function isWithinGeofence(): bool
    {
        return $this->distance_meters <= self::GEOFENCE_RADIUS_METERS;
    }

    /**
     * Scope to get only confirmed attendance records.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope to get only rejected attendance records.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to get only pending attendance records.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get attendance records for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }
}