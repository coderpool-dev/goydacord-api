<?php

namespace App\Services\Conversations;

use App\Models\Conversations\NotificationMute;
use Illuminate\Support\Collection;

/**
 * Заглушённые чаты, каналы и серверы: по ним не шлём пуши, а фронт не играет звук и не
 * показывает системное уведомление. Непрочитанное и счётчик упоминаний при этом остаются —
 * как «Заглушить» в Discord.
 */
class NotificationMuteService
{
    /** @return Collection<int, NotificationMute> */
    public function activeFor(int $userId): Collection
    {
        return NotificationMute::query()->where('user_id', $userId)->active()->get();
    }

    /** $minutes = null — пока не включат обратно. */
    public function mute(int $userId, string $type, int $targetId, ?int $minutes): NotificationMute
    {
        return NotificationMute::query()->updateOrCreate(
            ['user_id' => $userId, 'target_type' => $type, 'target_id' => $targetId],
            ['muted_until' => $minutes === null ? null : now()->addMinutes($minutes)],
        );
    }

    public function unmute(int $userId, string $type, int $targetId): void
    {
        NotificationMute::query()
            ->where('user_id', $userId)
            ->where('target_type', $type)
            ->where('target_id', $targetId)
            ->delete();
    }

    /**
     * Кто из $userIds заглушил хоть одну из целей.
     *
     * @param  int[]  $userIds
     * @param  array<string, int>  $targets  target_type => target_id
     * @return int[]
     */
    public function mutedAmong(array $userIds, array $targets): array
    {
        if ($userIds === [] || $targets === []) {
            return [];
        }

        return NotificationMute::query()
            ->whereIn('user_id', $userIds)
            ->active()
            ->where(function ($query) use ($targets) {
                foreach ($targets as $type => $id) {
                    $query->orWhere(fn ($query) => $query->where('target_type', $type)->where('target_id', $id));
                }
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  int[]  $userIds
     * @param  array<string, int>  $targets
     * @return int[]
     */
    public function withoutMuted(array $userIds, array $targets): array
    {
        $muted = $this->mutedAmong($userIds, $targets);

        return array_values(array_filter($userIds, fn ($id) => ! in_array((int) $id, $muted, true)));
    }
}
