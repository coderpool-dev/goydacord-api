<?php

namespace App\Support;

/** Статус «Слушает Исполнитель — Трек · Яндекс Музыка», который ставится автоматически. */
final class YandexMusicStatus
{
    private const PREFIX = 'Слушает ';

    private const SUFFIX = ' · Яндекс Музыка';

    public static function isAuto(?string $status): bool
    {
        return is_string($status) && str_starts_with($status, self::PREFIX) && str_ends_with($status, self::SUFFIX);
    }

    public static function format(string $artist, string $title): string
    {
        $artist = trim($artist);
        $title = trim($title);

        return self::PREFIX.($artist !== '' ? $artist.' — '.$title : $title).self::SUFFIX;
    }

    /** @return array{artist: string, title: string}|null */
    public static function parse(?string $status): ?array
    {
        if (! self::isAuto($status)) {
            return null;
        }

        $rest = trim(substr($status, strlen(self::PREFIX)));
        $rest = trim(preg_replace('/\s*·\s*Яндекс Музыка\s*$/iu', '', $rest) ?? $rest);

        if ($rest === '') {
            return null;
        }

        if (! preg_match('/\s—\s/u', $rest, $match, PREG_OFFSET_CAPTURE)) {
            return ['artist' => '', 'title' => $rest];
        }

        $title = trim(substr($rest, $match[0][1] + strlen($match[0][0])));

        return $title !== '' ? ['artist' => trim(substr($rest, 0, $match[0][1])), 'title' => $title] : null;
    }
}
