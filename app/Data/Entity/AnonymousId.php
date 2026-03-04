<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $last_login
 * @property string $token
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnonymousId whereUpdatedAt($value)
 * @method static \Database\Factories\Data\Entity\AnonymousIdFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class AnonymousId extends Model
{
    /** @use HasFactory<\Database\Factories\Data\Entity\AnonymousIdFactory> */
    use HasFactory;

    protected $fillable = ['token', 'last_login'];
}
