<?php

namespace App\Support;

/** Имена файлов, которые прислал клиент. */
final class FileName
{
    /** Без пути и управляющих символов: имя попадает в заголовки ответа и в интерфейс. */
    public static function sanitize(string $name, int $maxLength = 120): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        return mb_substr($name, 0, $maxLength);
    }

    public static function withExtension(string $name, string $extension): string
    {
        return pathinfo($name, PATHINFO_FILENAME).'.'.$extension;
    }

    /** Расширение из имени, а если его нет — по MIME-типу. */
    public static function extension(string $name, string $mime): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
