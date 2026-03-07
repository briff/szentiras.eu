<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $celebration_name
 * @property array|null $raw_parts
 * @property array|null $processed_refs
 * @property string $status
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read string $combinedRefString
 * @mixin \Eloquent
 */
class DailyReading extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Models\DailyReadingFactory
    {
        return \Database\Factories\Models\DailyReadingFactory::new();
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_FETCHING = 'fetching';
    public const STATUS_FETCHED = 'fetched';
    public const STATUS_COMMENTARIES_QUEUED = 'commentaries_queued';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'date',
        'celebration_name',
        'raw_parts',
        'processed_refs',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'raw_parts' => 'array',
            'processed_refs' => 'array',
        ];
    }

    /**
     * Determine if today's reading is available for display.
     */
    public function isAvailable(): bool
    {
        return in_array($this->status, [
            self::STATUS_FETCHED,
            self::STATUS_COMMENTARIES_QUEUED,
        ]) && !empty($this->processed_refs);
    }

    /**
     * Get the combined reference string for the reading page URL.
     */
    protected function combinedRefString(): Attribute
    {
        return Attribute::make(
            get: fn () => empty($this->processed_refs) ? '' : implode(';', $this->processed_refs),
        );
    }
}
