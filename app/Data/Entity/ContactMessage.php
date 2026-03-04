<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $sender_anonymous_id
 * @property int|null $receiver_anonymous_id
 * @property int|null $parent_id
 * @property string $message
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read AnonymousId|null $sender
 * @property-read AnonymousId|null $receiver
 * @property-read ContactMessage|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactMessage> $replies
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereReceiverAnonymousId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereSenderAnonymousId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage whereUpdatedAt($value)
 * @property-read int|null $replies_count
 * @method static \Database\Factories\Data\Entity\ContactMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage forParticipant(\SzentirasHu\Data\Entity\AnonymousId $participant)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage forReceiver(\SzentirasHu\Data\Entity\AnonymousId $receiver)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage forSender(\SzentirasHu\Data\Entity\AnonymousId $sender)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage resolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage rootMessages()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactMessage unresolved()
 * @mixin \Eloquent
 */
class ContactMessage extends Model
{
    /** @use HasFactory<\Database\Factories\Data\Entity\ContactMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'sender_anonymous_id',
        'receiver_anonymous_id',
        'parent_id',
        'message',
        'is_read',
        'resolved_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(AnonymousId::class, 'sender_anonymous_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(AnonymousId::class, 'receiver_anonymous_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    public function markAsResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isGuestMessage(): bool
    {
        return $this->sender_anonymous_id === null;
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeRootMessages($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForReceiver($query, AnonymousId $receiver)
    {
        return $query->where('receiver_anonymous_id', $receiver->id);
    }

    public function scopeForSender($query, AnonymousId $sender)
    {
        return $query->where('sender_anonymous_id', $sender->id);
    }

    public function scopeForParticipant($query, AnonymousId $participant)
    {
        return $query->where(function ($q) use ($participant) {
            $q->where('sender_anonymous_id', $participant->id)
              ->orWhere('receiver_anonymous_id', $participant->id);
        });
    }
}