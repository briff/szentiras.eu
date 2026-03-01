<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $key_hash
 * @property string $key_prefix
 * @property bool $is_internal
 * @property int|null $throttle_rate
 * @property bool $enabled
 * @property int|null $created_by_anonymous_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read AnonymousId|null $createdBy
 */
class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'is_internal',
        'throttle_rate',
        'enabled',
        'created_by_anonymous_id',
        'description',
        'last_used_at',
        'usage_count',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'enabled' => 'boolean',
        'throttle_rate' => 'integer',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * The anonymous user (editor) who created this key.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AnonymousId::class, 'created_by_anonymous_id');
    }

    /**
     * Determine if this key is considered internal (no throttling).
     */
    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    /**
     * Determine if this key is considered external (subject to throttling).
     */
    public function isExternal(): bool
    {
        return !$this->is_internal;
    }

    /**
     * Get the effective throttle rate (requests per minute).
     * Returns null for unlimited (internal keys) or explicit null.
     */
    public function effectiveThrottleRate(): ?int
    {
        if ($this->isInternal()) {
            return config('api.internal_throttle', null);
        }

        return $this->throttle_rate ?? config('api.default_throttle', 60);
    }
}
