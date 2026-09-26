<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\HeaderUtils;

final class ContentDisposition
{
    /**
     * Заголовок Content-Disposition с именем файла. Для имён не в ASCII (например,
     * на кириллице) Symfony требует запасное ASCII-имя, иначе бросает исключение.
     */
    public static function make(string $disposition, string $fileName, string $fallbackName = 'file'): string
    {
        $asciiName = (string) preg_replace('/[^\x20-\x7E]/', '_', $fileName);
        $asciiName = trim(str_replace(['"', '/', '\\'], '_', $asciiName)) ?: $fallbackName;

        return HeaderUtils::makeDisposition($disposition, $fileName, $asciiName);
    }
}
