<?php

namespace App\Exceptions;

class UploadException extends ApiException
{
    public static function tooLarge(int $maxBytes): self
    {
        return new self('Файл слишком большой', 413, ['max_bytes' => $maxBytes]);
    }

    public static function quotaExceeded(): self
    {
        return new self('Превышена квота хранилища. Удалите старые файлы.', 413);
    }

    /** Клиент прислал кусок не с того места — пусть продолжит с принятого сервером смещения. */
    public static function offsetMismatch(int $received): self
    {
        return new self('Offset mismatch', 409, ['received' => $received]);
    }

    public static function emptyChunk(): self
    {
        return new self('Empty chunk', 422);
    }

    public static function exceedsDeclaredSize(): self
    {
        return new self('Chunk exceeds declared size', 422);
    }

    public static function incomplete(int $received, int $total): self
    {
        return new self('Upload incomplete', 422, ['received' => $received, 'total' => $total]);
    }

    public static function sizeMismatch(?int $assembled, int $total): self
    {
        return new self('Upload size mismatch — повторите загрузку', 422, [
            'assembled' => $assembled,
            'total' => $total,
        ]);
    }

    public static function writeFailed(): self
    {
        return new self('Не удалось записать чанк', 500);
    }
}
