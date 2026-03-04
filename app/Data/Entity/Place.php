<?php

namespace SzentirasHu\Data\Entity;

use Eloquent;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Place represents a geographic location referenced in the Bible.
 *
 * @property int $id
 * @property string $external_id
 * @property string $type
 * @property string $friendly_id
 * @property string|null $comment
 * @property string|null $lon_lat
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Data\Entity\PlaceVerse> $verseReferences
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereFriendlyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereLonLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Place whereUpdatedAt($value)
 * @property-read int|null $verse_references_count
 * @mixin Eloquent
 */
class Place extends Eloquent
{
    protected $table = 'places';

    protected $fillable = [
        'external_id',
        'type',
        'friendly_id',
        'comment',
        'lon_lat',
    ];

    public function verseReferences(): HasMany
    {
        return $this->hasMany(PlaceVerse::class);
    }
}