<?php

namespace SzentirasHu\Data\Entity;

use Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot table linking places to verse references (book, chapter, verse).
 *
 * @property int $id
 * @property int $place_id
 * @property string $book_code
 * @property int $chapter_number
 * @property int $verse_number
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \SzentirasHu\Data\Entity\Place $place
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse whereBookCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse whereChapterNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse wherePlaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaceVerse whereVerseNumber($value)
 * @property-read string $reference
 * @mixin Eloquent
 */
class PlaceVerse extends Eloquent
{
    protected $table = 'place_verse';

    protected $fillable = [
        'place_id',
        'book_code',
        'chapter_number',
        'verse_number',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Get the verse reference as a string (e.g., "GEN 1:1").
     */
    public function getReferenceAttribute(): string
    {
        return "{$this->book_code} {$this->chapter_number}:{$this->verse_number}";
    }
}