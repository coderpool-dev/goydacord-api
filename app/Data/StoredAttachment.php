<?php

namespace App\Data;

/** Файл вложения на диске: всё, что нужно, чтобы отдать его браузеру. */
final readonly class StoredAttachment
{
    public function __construct(
        public string $diskPath,
        public string $name,
        public string $mime,
        public string $kind,
        // Маленькие файлы зашифрованы целиком, большие лежат как есть.
        public bool $encrypted,
        public int $keyId,
    ) {}

    /** Картинки, аудио и видео открываются прямо в браузере, остальное скачивается. */
    public function opensInBrowser(): bool
    {
        return $this->kind === 'image'
            || str_starts_with($this->mime, 'audio/')
            || str_starts_with($this->mime, 'video/');
    }
}
