<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Database\Factories\Data\Entity\VerseCardAssetFactory;

/**
 * @property int $id
 * @property string $session_id
 * @property string $kind
 * @property string $state
 * @property int|null $pixabay_id
 * @property string|null $pixabay_user
 * @property string|null $pixabay_page_url
 * @property string|null $remote_url
 * @property string $disk
 * @property string|null $path
 * @property string|null $thumb_path
 * @property int|null $bytes
 * @property int|null $width
 * @property int|null $height
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read VerseCardSession $session
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset wherePixabayId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset wherePixabayPageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset wherePixabayUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereRemoteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereThumbPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerseCardAsset whereUpdatedAt($value)
 * @method static \Database\Factories\Data\Entity\VerseCardAssetFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class VerseCardAsset extends Model
{
    /** @use HasFactory<VerseCardAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'kind',
        'state',
        'pixabay_id',
        'pixabay_user',
        'pixabay_page_url',
        'remote_url',
        'web_format_url',
        'disk',
        'path',
        'thumb_path',
        'bytes',
        'width',
        'height',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(VerseCardSession::class, 'session_id');
    }

    protected static function newFactory()
    {
        return VerseCardAssetFactory::new();
    }
}
