<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * @property int $id
 * @property string $hungarian_keyword
 * @property Vector $embedding
 * @property string|null $photo_keywords
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme nearestNeighbors(string $column, ?mixed $value, \Pgvector\Laravel\Distance $distance)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereHungarianKeyword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme wherePhotoKeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Theme extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'hungarian_keyword',
        'embedding',
        'photo_keywords',
    ];

    protected $casts = [
        'embedding' => Vector::class,
    ];

    /**
     * Get the embedding vector as an array.
     */
    public function getEmbeddingArray(): array
    {
        return $this->embedding->toArray();
    }
}