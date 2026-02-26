<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $commentary_id
 * @property int $start_chapter
 * @property int $start_verse
 * @property int $end_chapter
 * @property int $end_verse
 * @property-read \SzentirasHu\Models\Commentary $commentary
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange whereCommentaryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange whereEndChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange whereEndVerse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange whereStartChapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentaryRange whereStartVerse($value)
 * @mixin \Eloquent
 */
class CommentaryRange extends Model
{
    protected $table = 'commentary_ranges';

    protected $fillable = [
        'commentary_id',
        'start_chapter',
        'start_verse',
        'end_chapter',
        'end_verse',
    ];

    public $timestamps = false;

    public function commentary(): BelongsTo
    {
        return $this->belongsTo(Commentary::class);
    }

    /**
     * Check if this range covers a specific verse.
     *
     * @param int $chapter
     * @param int $verse
     * @return bool
     */
    public function coversVerse(int $chapter, int $verse): bool
    {
        // Single verse range
        if ($this->start_chapter === $this->end_chapter && $this->start_verse === $this->end_verse) {
            return $this->start_chapter === $chapter && $this->start_verse === $verse;
        }

        // Multi-verse range within same chapter
        if ($this->start_chapter === $this->end_chapter) {
            return $this->start_chapter === $chapter
                && $verse >= $this->start_verse
                && $verse <= $this->end_verse;
        }

        // Cross-chapter range
        if ($chapter === $this->start_chapter) {
            return $verse >= $this->start_verse;
        }
        if ($chapter === $this->end_chapter) {
            return $verse <= $this->end_verse;
        }
        return $chapter > $this->start_chapter && $chapter < $this->end_chapter;
    }

    /**
     * Get a string representation of the range.
     *
     * @return string
     */
    public function toString(): string
    {
        if ($this->start_chapter === $this->end_chapter && $this->start_verse === $this->end_verse) {
            return "{$this->start_chapter}:{$this->start_verse}";
        }
        if ($this->start_chapter === $this->end_chapter) {
            return "{$this->start_chapter}:{$this->start_verse}-{$this->end_verse}";
        }
        return "{$this->start_chapter}:{$this->start_verse}-{$this->end_chapter}:{$this->end_verse}";
    }
}