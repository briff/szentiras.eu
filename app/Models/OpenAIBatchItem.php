<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $openai_batch_id
 * @property string $custom_id
 * @property int|null $source_id
 * @property string $status
 * @property array|null $payload
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \SzentirasHu\Models\OpenAIBatch $batch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereCustomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereOpenaiBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatchItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OpenAIBatchItem extends Model
{
    protected $table = 'openai_batch_items';

    protected $fillable = [
        'openai_batch_id',
        'custom_id',
        'source_id',
        'status',
        'payload',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpenAIBatch::class, 'openai_batch_id');
    }
}
