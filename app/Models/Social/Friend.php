<?php

namespace App\Models\Social;

use App\Enums\FriendStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Связь двух пользователей: заявка, дружба, отказ или блокировка. Одна строка на пару. */
class Friend extends Model
{
    use HasFactory;

    protected $table = 'friend';

    protected $fillable = [
        'friend_id',
        'users_id',
        'status',
    ];

    protected $casts = [
        'status' => FriendStatus::class,
    ];

    /**
     * Тот, кто отправил заявку или заблокировал.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /** @return BelongsTo<User, $this> */
    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    /** Строка о паре пользователей в любую сторону: заявку мог отправить любой из них. */
    public function scopeBetween(Builder $query, int $firstUserId, int $secondUserId): void
    {
        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('users_id', $firstUserId)->where('friend_id', $secondUserId))
            ->orWhere(fn (Builder $query) => $query->where('users_id', $secondUserId)->where('friend_id', $firstUserId)));
    }

    public function isSender(int $userId): bool
    {
        return (int) $this->users_id === $userId;
    }
}
