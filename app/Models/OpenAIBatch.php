<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $input_file_id
 * @property string|null $batch_id
 * @property string $status
 * @property string|null $output_file_id
 * @property string|null $error_file_id
 * @property string $endpoint
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\OpenAIBatchItem> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereErrorFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereInputFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereOutputFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OpenAIBatch whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OpenAIBatch extends Model
{
    protected $table = 'openai_batches';

    protected $fillable = [
        'input_file_id',
        'batch_id',
        'status',
        'output_file_id',
        'error_file_id',
        'endpoint',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OpenAIBatchItem::class, 'openai_batch_id');
    }
}
