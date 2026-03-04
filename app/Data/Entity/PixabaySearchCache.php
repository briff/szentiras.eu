<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $cache_key
 * @property array $params
 * @property array $response
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache whereCacheKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache whereParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PixabaySearchCache whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PixabaySearchCache extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'cache_key';

    protected $table = 'pixabay_search_cache';

    protected $fillable = [
        'cache_key',
        'params',
        'response',
        'expires_at',
    ];

    protected $casts = [
        'params' => 'array',
        'response' => 'array',
        'expires_at' => 'datetime',
    ];
}
