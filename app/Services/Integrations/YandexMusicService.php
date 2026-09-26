<?php

namespace App\Services\Integrations;

use App\Events\UserProfileUpdated;
use App\Models\Integrations\YandexMusicTrackHistory;
use App\Models\User;
use App\Support\YandexMusicStatus;

/** Что пользователь слушает в Яндекс Музыке: текущий трек, статус «Слушает …» и история прослушиваний. */
class YandexMusicService
{
    public function __construct(
        private readonly YandexYnisonService $ynison,
        private readonly YandexMusicApiClient $api,
        private readonly YandexMusicPlaybackTracker $playback,
    ) {}

    /** Узнаёт у Яндекса текущий трек и обновляет по нему подключение, статус и историю. */
    public function syncNowPlaying(User $user): ?array
    {
        $user->loadMissing('yandexMusicConnection');
        $connection = $user->yandexMusicConnection;

        if (! $connection) {
            return null;
        }

        $track = $this->fetchCurrentTrack($connection->access_token);

        // Яндекс не ответил или ничего не играет — отдаём последний известный трек или разбираем статус.
        if ($track === null) {
            return $connection->currentTrack() ?? YandexMusicStatus::parse($this->autoMusicStatus($user));
        }

        $trackKey = mb_strtolower(trim(($track['artist'] ?? '').'|'.($track['title'] ?? '')));
        $previousTrackKey = $connection->last_track_key;
        $hadCover = filled($connection->current_track_cover_url);

        $connection->last_track_key = $trackKey;
        $connection->fill($this->playback->connectionAttributes($track, $connection, $trackKey, $previousTrackKey));

        if (! filled($connection->current_track_cover_url)) {
            $cover = $this->knownCover($user, $trackKey, $previousTrackKey, $connection->getOriginal('current_track_cover_url'));

            if ($cover !== null) {
                $connection->current_track_cover_url = $cover;
                $track['cover_url'] = $cover;
            }
        }

        $connection->save();

        $this->recordHistory($user, $track, $trackKey, isNewPlay: $previousTrackKey !== $trackKey);
        $this->updateMusicStatus($user, $track, coverAppeared: ! $hadCover && filled($connection->current_track_cover_url));

        // Клиенту отдаём трек из подключения с устойчивым прогрессом, а не сырой ответ Ynison:
        // тот часто присылает progress_ms = 0, и фронт показывал бы 00:00 посреди песни.
        return $connection->currentTrack() ?? $track;
    }

    public function history(User $user, int $limit = 12): array
    {
        return YandexMusicTrackHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_played_at')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn (YandexMusicTrackHistory $track) => [
                'title' => $track->title,
                'artist' => $track->artist ?? '',
                'album' => $track->album,
                'cover_url' => $track->cover_url,
                'track_url' => $track->track_url,
                'play_count' => $track->play_count,
                'first_played_at' => $track->first_played_at?->toIso8601String(),
                'last_played_at' => $track->last_played_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Ynison знает, что играет в приложении, а очереди дают полные данные трека — берём лучшее из обоих. */
    private function fetchCurrentTrack(string $accessToken): ?array
    {
        $playback = $this->ynison->fetchCurrentTrack($accessToken);
        $queueTrack = $this->api->currentQueueTrack($accessToken);

        if ($playback === null || $queueTrack === null) {
            return $playback ?? $queueTrack;
        }

        return [
            ...$queueTrack,
            'duration_ms' => $this->positiveInt($playback['duration_ms'] ?? null) ?? $this->positiveInt($queueTrack['duration_ms']),
            'progress_ms' => array_key_exists('progress_ms', $playback) ? $playback['progress_ms'] : $queueTrack['progress_ms'],
            'paused' => $playback['paused'] ?? $queueTrack['paused'],
            'cover_url' => $playback['cover_url'] ?? $queueTrack['cover_url'],
        ];
    }

    /** В «не беспокоить» и невидимке статус «Слушает …» не выставляем. */
    private function updateMusicStatus(User $user, array $track, bool $coverAppeared): void
    {
        if (in_array($user->presence, ['dnd', 'invisible'], true)) {
            return;
        }

        $status = YandexMusicStatus::format($track['artist'], $track['title']);
        $statusChanged = $user->music_status_text !== $status;

        if ($statusChanged) {
            $user->update(['music_status_text' => $status]);
        }

        if ($statusChanged || $coverAppeared) {
            broadcast(new UserProfileUpdated($user->fresh(['yandexMusicConnection']), $user->activeChannelIds()));
        }
    }

    private function autoMusicStatus(User $user): ?string
    {
        foreach ([$user->music_status_text, $user->status_text] as $status) {
            if (YandexMusicStatus::isAuto($status)) {
                return $status;
            }
        }

        return null;
    }

    /** Обложка того же трека: из прошлой синхронизации или из истории прослушиваний. */
    private function knownCover(User $user, string $trackKey, ?string $previousTrackKey, mixed $previousCover): ?string
    {
        if ($trackKey === $previousTrackKey && filled($previousCover)) {
            return (string) $previousCover;
        }

        $historyCover = YandexMusicTrackHistory::query()
            ->where('user_id', $user->id)
            ->where('track_key', $trackKey)
            ->whereNotNull('cover_url')
            ->value('cover_url');

        return filled($historyCover) ? (string) $historyCover : null;
    }

    private function recordHistory(User $user, array $track, string $trackKey, bool $isNewPlay): void
    {
        $history = YandexMusicTrackHistory::firstOrNew(['user_id' => $user->id, 'track_key' => $trackKey]);

        $history->fill([
            'title' => $track['title'] ?? '',
            'artist' => $track['artist'] ?? '',
            'album' => $track['album'] ?? null,
            'cover_url' => $track['cover_url'] ?? $history->cover_url,
            'track_url' => $track['track_url'] ?? $history->track_url,
            'last_played_at' => now(),
        ]);

        if (! $history->exists) {
            $history->first_played_at = now();
            $history->play_count = 1;
        } elseif ($isNewPlay) {
            $history->play_count++;
        }

        $history->save();
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
