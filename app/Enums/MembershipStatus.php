<?php

namespace App\Enums;

/**
 * Участие пользователя в канале, колонка channels_members.status.
 * Выход и исключение не удаляют строку, а ставят Removed: в личный чат потом можно вернуться.
 */
enum MembershipStatus: int
{
    case Removed = 0;
    case Member = 1;
    case Admin = 2;
}
