<?php

namespace App\Services\Social;

use App\Enums\FriendRequestOutcome;
use App\Enums\FriendRequestResolution;
use App\Enums\FriendStatus;
use App\Exceptions\ApiException;
use App\Models\Social\Friend;
use App\Models\User;
use Illuminate\Support\Collection;

class FriendService
{
    /** Поля пользователя для списка друзей: профиль и статусы активности. */
    private const PROFILE_COLUMNS = 'id,login,name,avatar,banner,banner_color,presence,status_emoji,status_text,game_status_text,game_status_synced_at,music_status_text,last_online,updated_at';

    public function sendRequest(User $currentUser, User $friendUser): FriendRequestOutcome
    {
        if ((int) $currentUser->id === (int) $friendUser->id) {
            throw new ApiException('Нельзя добавить самого себя в друзья', 400);
        }

        $friendship = $this->friendshipBetween($currentUser, $friendUser);

        if (! $friendship) {
            Friend::create([
                'users_id' => $currentUser->id,
                'friend_id' => $friendUser->id,
                'status' => FriendStatus::Pending,
            ]);

            return FriendRequestOutcome::Sent;
        }

        $status = $friendship->status;

        if ($status === FriendStatus::Pending && (int) $friendship->friend_id === (int) $currentUser->id) {
            $friendship->update(['status' => FriendStatus::Accepted]);

            return FriendRequestOutcome::AcceptedIncoming;
        }

        if ($status === FriendStatus::Rejected) {
            $friendship->update([
                'users_id' => $currentUser->id,
                'friend_id' => $friendUser->id,
                'status' => FriendStatus::Pending,
            ]);

            return FriendRequestOutcome::Resent;
        }

        throw new ApiException($this->existingFriendshipMessage($friendship, $currentUser), 409);
    }

    public function remove(User $currentUser, User $friendUser): void
    {
        $friendship = $this->friendshipBetween($currentUser, $friendUser)
            ?? throw new ApiException('Запись о дружбе не найдена', 404);

        $friendship->delete();
    }

    public function accept(User $currentUser, User $friendUser): void
    {
        $request = Friend::query()
            ->where('users_id', $friendUser->id)
            ->where('friend_id', $currentUser->id)
            ->where('status', FriendStatus::Pending)
            ->first()
            ?? throw new ApiException('Запрос на дружбу не найден или уже обработан', 404);

        $request->update(['status' => FriendStatus::Accepted]);
    }

    public function reject(User $currentUser, User $friendUser): FriendRequestResolution
    {
        $request = Friend::query()
            ->between($currentUser->id, $friendUser->id)
            ->where('status', FriendStatus::Pending)
            ->first()
            ?? throw new ApiException('Запрос на дружбу не найден или уже обработан', 404);

        // Входящую заявку отклоняем и запоминаем отказ, свою исходящую просто отменяем.
        if ((int) $request->users_id === (int) $friendUser->id) {
            $request->update(['status' => FriendStatus::Rejected]);

            return FriendRequestResolution::Rejected;
        }

        $request->delete();

        return FriendRequestResolution::Cancelled;
    }

    public function block(User $currentUser, User $friendUser): void
    {
        if ((int) $currentUser->id === (int) $friendUser->id) {
            throw new ApiException('Нельзя заблокировать самого себя', 400);
        }

        $attributes = [
            'users_id' => $currentUser->id,
            'friend_id' => $friendUser->id,
            'status' => FriendStatus::Blocked,
        ];

        // Блокировка заменяет любую прежнюю связь, а автором становится тот, кто блокирует.
        $friendship = $this->friendshipBetween($currentUser, $friendUser);
        $friendship ? $friendship->update($attributes) : Friend::create($attributes);
    }

    public function unblock(User $currentUser, User $friendUser): void
    {
        $block = Friend::query()
            ->where('users_id', $currentUser->id)
            ->where('friend_id', $friendUser->id)
            ->where('status', FriendStatus::Blocked)
            ->first()
            ?? throw new ApiException('Пользователь не заблокирован или блокировка не найдена', 404);

        $block->delete();
    }

    public function friendshipsFor(User $currentUser): Collection
    {
        return Friend::query()
            ->with([
                'friend:'.self::PROFILE_COLUMNS,
                'friend.yandexMusicConnection',
                'user:'.self::PROFILE_COLUMNS,
                'user.yandexMusicConnection',
            ])
            ->where(fn ($query) => $query
                ->where('users_id', $currentUser->id)
                ->orWhere('friend_id', $currentUser->id))
            ->where('status', '!=', FriendStatus::Rejected)
            ->orderByDesc('created_at')
            ->get();
    }

    public function mutualFriends(User $currentUser, User $other): Collection
    {
        if ((int) $currentUser->id === (int) $other->id) {
            return collect();
        }

        $mutualFriendIds = array_intersect(
            $this->acceptedFriendIdsFor($currentUser),
            $this->acceptedFriendIdsFor($other),
        );

        return User::query()
            ->whereIn('id', $mutualFriendIds)
            ->orderBy('name')
            ->get([
                'id', 'login', 'name', 'avatar', 'banner', 'banner_color', 'presence',
                'status_emoji', 'status_text', 'game_status_text', 'last_online', 'updated_at',
            ]);
    }

    private function friendshipBetween(User $currentUser, User $friendUser): ?Friend
    {
        return Friend::query()->between($currentUser->id, $friendUser->id)->first();
    }

    /** @return list<int> */
    private function acceptedFriendIdsFor(User $user): array
    {
        return Friend::query()
            ->where('status', FriendStatus::Accepted)
            ->where(fn ($query) => $query
                ->where('users_id', $user->id)
                ->orWhere('friend_id', $user->id))
            ->get(['users_id', 'friend_id'])
            ->map(fn (Friend $friendship) => (int) $friendship->users_id === (int) $user->id
                ? (int) $friendship->friend_id
                : (int) $friendship->users_id)
            ->unique()
            ->values()
            ->all();
    }

    private function existingFriendshipMessage(Friend $friendship, User $currentUser): string
    {
        return match ($friendship->status) {
            FriendStatus::Pending => $friendship->isSender($currentUser->id)
                ? 'Заявка уже отправлена'
                : 'У вас есть входящая заявка от этого пользователя',
            FriendStatus::Accepted => 'Вы уже друзья',
            FriendStatus::Blocked => 'Пользователь заблокирован',
            default => 'Запись о дружбе уже существует',
        };
    }
}
