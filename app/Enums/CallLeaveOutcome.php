<?php

namespace App\Enums;

/** Чем закончился выход из звонка. */
enum CallLeaveOutcome
{
    case Left;
    // Вышел на одном устройстве, но остался в звонке на другом.
    case StillConnectedElsewhere;
    // Вышел последний участник, и звонок завершён.
    case CallEnded;

    public function message(): string
    {
        return match ($this) {
            self::Left => 'Вы вышли из звонка',
            self::StillConnectedElsewhere => 'Вы вышли из звонка на этом устройстве',
            self::CallEnded => 'Вы вышли, звонок завершён',
        };
    }
}
