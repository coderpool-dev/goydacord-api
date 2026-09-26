<?php

namespace App\Enums;

/** Отказ от заявки: входящую отклоняют, свою исходящую отменяют. */
enum FriendRequestResolution: string
{
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function message(): string
    {
        return match ($this) {
            self::Rejected => 'Запрос на дружбу отклонен успешно',
            self::Cancelled => 'Запрос на дружбу отменен успешно',
        };
    }
}
