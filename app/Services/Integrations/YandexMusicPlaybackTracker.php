<?php

namespace App\Services\Integrations;

use App\Models\Integrations\YandexMusicConnection;
use Carbon\CarbonInterface;

/**
 * Прогресс текущего трека. Яндекс присылает живой прогресс только в момент play, pause или seek,
 * а между опросами часто отдаёт 0 или null. Поэтому храним свой якорь — прогресс и время, когда
 * он был верен, — а серверное значение принимаем, только когда оно означает перемотку или возобновление.
 */
class YandexMusicPlaybackTracker
{
    /** Расхождение с ожидаемым прогрессом больше этого — перемотка, а не погрешность опроса. */
    private const SEEK_THRESHOLD_MS = 4000;

    /** На паузе прогресс вырос больше чем на столько — трек на самом деле играет. */
    private const RESUME_THRESHOLD_MS = 1500;

    /** @return array<string, mixed> поля current_track_* для YandexMusicConnection */
    public function connectionAttributes(
        array $track,
        YandexMusicConnection $connection,
        string $trackKey,
        ?string $previousTrackKey,
    ): array {
        $reportedProgress = isset($track['progress_ms']) ? (int) $track['progress_ms'] : null;
        $paused = (bool) ($track['paused'] ?? false);
        $reportedDuration = isset($track['duration_ms']) && (int) $track['duration_ms'] > 0 ? (int) $track['duration_ms'] : null;
        $durationMs = $reportedDuration ?? $connection->current_track_duration_ms;
        $progressMs = $reportedProgress;
        $seenAt = now();

        if ($previousTrackKey !== null && $trackKey === $previousTrackKey && $connection->current_track_seen_at) {
            [$progressMs, $paused, $seenAt] = $this->continueSameTrack($connection, $reportedProgress, $paused);
        }

        // На паузе прогресс вырос — трек на самом деле играет.
        if ($paused && $reportedProgress !== null && $connection->current_track_progress_ms !== null
            && $reportedProgress > (int) $connection->current_track_progress_ms) {
            $paused = false;
        }

        if ($durationMs !== null && $progressMs !== null) {
            $progressMs = min((int) $durationMs, max(0, (int) $progressMs));
        }

        return [
            'current_track_title' => $track['title'] ?? null,
            'current_track_artist' => $track['artist'] ?? null,
            'current_track_album' => $track['album'] ?? null,
            'current_track_cover_url' => $track['cover_url'] ?? null,
            'current_track_url' => $track['track_url'] ?? null,
            'current_track_duration_ms' => $durationMs,
            'current_track_progress_ms' => $progressMs,
            'current_track_paused' => $paused,
            'current_track_seen_at' => $seenAt,
        ];
    }

    /** @return array{0: int|null, 1: bool, 2: CarbonInterface} прогресс, пауза и время якоря */
    private function continueSameTrack(YandexMusicConnection $connection, ?int $reported, bool $paused): array
    {
        $previousProgress = $connection->current_track_progress_ms;
        $previousPaused = (bool) $connection->current_track_paused;
        $previousSeenAt = $connection->current_track_seen_at;
        $elapsedMs = (int) abs($previousSeenAt->diffInMilliseconds(now()));

        // Где трек был бы сейчас, если бы всё это время играл без перемотки.
        $estimated = match (true) {
            $previousProgress === null => null,
            $previousPaused => $previousProgress,
            default => $previousProgress + $elapsedMs,
        };

        // Ynison иногда помечает перемотку как паузу: трек играл, а прогресс резко не совпал с ожидаемым.
        if ($paused && ! $previousPaused && $reported !== null && $estimated !== null
            && abs($reported - $estimated) > self::SEEK_THRESHOLD_MS) {
            return [$reported, false, now()];
        }

        // После возобновления Ynison ещё присылает paused = true, хотя прогресс уже растёт.
        if ($previousPaused && $paused && $reported !== null && $previousProgress !== null
            && $reported > $previousProgress + self::RESUME_THRESHOLD_MS) {
            return [$reported, false, now()];
        }

        // Сняли с паузы: новый якорь, чтобы фронт не прибавил время, пока трек стоял.
        if ($previousPaused && ! $paused) {
            return [$reported > 0 ? $reported : ($previousProgress ?? 0), false, now()];
        }

        // На паузе серверное значение точное, а если его нет — фиксируем свою оценку.
        if ($paused) {
            return [$reported ?? $estimated ?? $previousProgress, true, now()];
        }

        // Прогресс не пришёл — продолжаем тикать от прошлого якоря.
        if (! $reported) {
            return [$previousProgress ?? 0, false, $previousSeenAt];
        }

        // Ynison подтверждает ровное воспроизведение — якорь не трогаем, чтобы прогресс на фронте не дёргался.
        if ($estimated !== null && abs($reported - $estimated) <= self::SEEK_THRESHOLD_MS) {
            return [$previousProgress, false, $previousSeenAt];
        }

        return [$reported, false, now()];
    }
}
