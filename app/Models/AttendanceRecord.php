<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceRecord Model
 *
 * Represents one attendance work session with geolocation data.
 * A user may have multiple confirmed sessions per day, e.g. morning work,
 * lunch break, afternoon work.
 *
 * @property int $id
 * @property int $user_id
 * @property int $qr_code_id
 * @property float $latitude
 * @property float $longitude
 * @property float $distance_meters
 * @property float|null $check_out_latitude
 * @property float|null $check_out_longitude
 * @property float|null $check_out_distance_meters
 * @property string $status
 * @property string|null $rejection_reason
 * @property \Carbon\Carbon|null $check_in_at
 * @property \Carbon\Carbon|null $check_out_at
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
        'check_out_latitude',
        'check_out_longitude',
        'check_out_distance_meters',
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
        'user_id'                   => 'integer',
        'qr_code_id'                => 'integer',
        'latitude'                  => 'float',
        'longitude'                 => 'float',
        'distance_meters'           => 'float',
        'check_out_latitude'        => 'float',
        'check_out_longitude'       => 'float',
        'check_out_distance_meters' => 'float',
        'check_in_at'               => 'datetime',
        'check_out_at'              => 'datetime',
        'late_minutes'              => 'integer',
        'overtime_minutes'          => 'integer',
        'daily_fee'                 => 'float',
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
     * Check if this session is currently open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_CONFIRMED
            && $this->check_in_at !== null
            && $this->check_out_at === null;
    }

    /**
     * Total worked minutes for this session.
     */
    public function workedMinutes(): int
    {
        if (!$this->check_in_at || !$this->check_out_at) {
            return 0;
        }

        return (int) $this->check_in_at->diffInMinutes($this->check_out_at);
    }

    /**
     * Check if the attendance is within the geofence radius.
     *
     * Compares the recorded distance against the currently configured radius,
     * so a record's badge follows the operator setting rather than a constant.
     *
     * @return bool True if within geofence, false otherwise
     */
    public function isWithinGeofence(): bool
    {
        if ($this->distance_meters === null) {
            return false;
        }

        return $this->distance_meters <= app(\App\Services\GeolocationService::class)->getGeofenceRadius();
    }

    /**
     * Check if the check-out location was within the geofence radius.
     */
    public function isCheckoutWithinGeofence(): bool
    {
        if ($this->check_out_distance_meters === null) {
            return false;
        }

        return $this->check_out_distance_meters <= app(\App\Services\GeolocationService::class)->getGeofenceRadius();
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

    /**
     * Scope to get currently open sessions.
     */
    public function scopeOpen($query)
    {
        return $query->confirmed()->whereNotNull('check_in_at')->whereNull('check_out_at');
    }
}
