<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Database\Factories\Data\Entity\VerseCardSessionFactory;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $verse_ref
 * @property string|null $verse_text
 * @property string $theme_slug
 * @property array $keywords
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VerseCardAsset> $assets
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereKeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereThemeSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereVerseRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereUpdatedAt($value)
 * @property-read int|null $assets_count
 * @property int $pixabay_page
 * @property int $pixabay_offset
 * @method static \Database\Factories\Data\Entity\VerseCardSessionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession wherePixabayOffset($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession wherePixabayPage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardSession whereVerseText($value)
 * @mixin \Eloquent
 */
class VerseCardSession extends Model
{
    /** @use HasFactory<VerseCardSessionFactory> */
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'verse_ref',
        'verse_text',
        'theme_slug',
        'keywords',
        'status',
        'pixabay_page',
        'pixabay_offset',
        'expires_at',
    ];

    protected $casts = [
        'keywords' => 'array',
        'expires_at' => 'datetime',
        'pixabay_page' => 'integer',
        'pixabay_offset' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (!$model->id) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    /** @return HasMany<VerseCardAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(VerseCardAsset::class, 'session_id');
    }

    protected static function newFactory()
    {
        return VerseCardSessionFactory::new();
    }
}
