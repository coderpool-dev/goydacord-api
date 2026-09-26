<?php

namespace App\Enums;

/** Чем закончилась отправка заявки в друзья. */
enum FriendRequestOutcome
{
    case Sent;
    case Resent;
    // Человек уже сам звал в друзья — вместо новой заявки принимаем его.
    case AcceptedIncoming;

    public function message(): string
    {
        return match ($this) {
            self::Sent => 'Запрос на дружбу отправлен успешно',
            self::Resent => 'Запрос на дружбу отправлен повторно',
            self::AcceptedIncoming => 'Входящая заявка принята, вы теперь друзья',
        };
    }

    public function createsRequest(): bool
    {
        return $this !== self::AcceptedIncoming;
    }
}
