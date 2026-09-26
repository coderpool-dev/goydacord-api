<?php

namespace App\Support;

use App\Models\User;

/** Текущий трек из уже загруженной связи. Без eager load не ходим в БД. */
final class YandexPresence
{
    public static function nowPlaying(?User $user): ?array
    {
        if ($user === null || ! $user->relationLoaded('yandexMusicConnection')) {
            return null;
        }

        return $user->yandexMusicConnection?->currentTrack();
    }

    public static function isConnected(User $user): bool
    {
        return $user->relationLoaded('yandexMusicConnection') && $user->yandexMusicConnection !== null;
    }
}
