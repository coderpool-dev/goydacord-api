<?php

namespace App\Enums;

/** Связь двух пользователей, колонка friend.status. На пару одна строка, см. Friend::scopeBetween. */
enum FriendStatus: int
{
    case Pending = 0;
    case Accepted = 1;

    /** Заявку отклонили. Новая заявка возвращает строку в Pending. */
    case Rejected = 2;

    /** Запрещает связь в обе стороны. Автор строки — тот, кто заблокировал. */
    case Blocked = 3;
}
