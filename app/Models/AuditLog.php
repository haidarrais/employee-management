<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog Model
 * 
 * Represents immutable audit log entries for system actions.
 * This model enforces immutability - updates are not permitted.
 * 
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property array $metadata
 * @property string|null $ip_address
 * @property \Carbon\Carbon $created_at
 */
class AuditLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'audit_logs';

    /**
     * Disable automatic timestamp management since we only use created_at
     */
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'action',
        'metadata',
        'ip_address',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'user_id'    => 'integer',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Ensure metadata is always returned as an array regardless of storage format.
     *
     * The `metadata` attribute is cast to `array`, so passing an array in is
     * correct and Laravel encodes it once. Two legacy shapes still exist in the
     * table and both must read back as arrays:
     *
     *   - a JSON string that decodes to an array (correctly written rows)
     *   - a JSON string that decodes to *another JSON string*, which is what
     *     AuditService::log() produced by calling json_encode() before handing
     *     the value to a model that encodes it again. Reading those naively
     *     yielded [] and the audit page's detail column rendered blank for
     *     every historical entry.
     */
    public function getMetadataAttribute(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        // Legacy double-encoded row: the first decode returns the inner JSON
        // string rather than the array itself.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        // No sensitive data to hide
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Stamp created_at on every insert (timestamps = false so Laravel won't do it)
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });

        // Prevent updates to make audit logs immutable
        static::updating(function ($model) {
            throw new \RuntimeException('Audit logs are immutable - updates are not permitted');
        });

        // Prevent deletes to maintain complete audit trail
        static::deleting(function ($model) {
            throw new \RuntimeException('Audit logs cannot be deleted - they are retained for compliance');
        });
    }

    /**
     * Get the user that triggered this audit log.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new audit log entry.
     */
    public static function log(?int $userId, string $action, array $metadata = [], ?string $ipAddress = null): self
    {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
        ]);
    }
}