<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SzentirasHu\Data\Entity\Translation;

/**
 * @property int $id
 * @property int $translation_id
 * @property string $usx_code
 * @property string $commentary_text
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \SzentirasHu\Data\Entity\Translation $translation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Models\CommentaryRange> $ranges
 * @property-read int|null $ranges_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereCommentaryText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereTranslationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commentary whereUsxCode($value)
 * @mixin \Eloquent
 */
class Commentary extends Model
{
    protected $table = 'commentaries';

    protected $fillable = [
        'translation_id',
        'usx_code',
        'commentary_text',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    public function ranges(): HasMany
    {
        return $this->hasMany(CommentaryRange::class);
    }

    /**
     * Check if this commentary covers a specific verse.
     *
     * @param int $chapter
     * @param int $verse
     * @return bool
     */
    public function coversVerse(int $chapter, int $verse): bool
    {
        return $this->ranges->contains(function (CommentaryRange $range) use ($chapter, $verse) {
            return $range->coversVerse($chapter, $verse);
        });
    }

    /**
     * Add a range to this commentary.
     *
     * @param int $startChapter
     * @param int $startVerse
     * @param int $endChapter
     * @param int $endVerse
     * @return CommentaryRange
     */
    public function addRange(int $startChapter, int $startVerse, int $endChapter, int $endVerse): CommentaryRange
    {
        return $this->ranges()->create([
            'start_chapter' => $startChapter,
            'start_verse' => $startVerse,
            'end_chapter' => $endChapter,
            'end_verse' => $endVerse,
        ]);
    }
}