<?php

namespace App\Support;

/** Ссылки на обложки и треки Яндекс Музыки. */
final class YandexMusicUrls
{
    /** API отдаёт обложку шаблоном вида avatars.yandex.net/.../%% без схемы. */
    public static function cover(?string $coverUri): ?string
    {
        $coverUri = str_replace('%%', '400x400', trim((string) $coverUri));

        if ($coverUri === '') {
            return null;
        }

        return preg_match('~^https?://~', $coverUri) ? $coverUri : 'https://'.$coverUri;
    }

    /** Id трека может прийти вместе с альбомом в виде «трек:альбом». */
    public static function track(string|int|null $trackId, string|int|null $albumId = null): ?string
    {
        $trackId = (string) $trackId;

        if ($trackId === '') {
            return null;
        }

        if (str_contains($trackId, ':')) {
            [$trackId, $albumFromId] = explode(':', $trackId, 2);
            $albumId = $albumId ?: $albumFromId;
        }

        return $albumId === null || $albumId === ''
            ? "https://music.yandex.ru/track/{$trackId}"
            : "https://music.yandex.ru/album/{$albumId}/track/{$trackId}";
    }
}
