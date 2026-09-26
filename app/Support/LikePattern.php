<?php

namespace App\Support;

final class LikePattern
{
    /** Шаблон «содержит» для LIKE. Символы % и _ из пользовательского ввода экранируются. */
    public static function contains(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }
}
