<?php

namespace App\Enums;

/**
 * Участие пользователя в сервере, колонка server_members.status.
 * Выход/кик/бан не удаляют строку, а ставят Removed — как MembershipStatus у каналов.
 */
enum ServerMembershipStatus: int
{
    case Removed = 0;
    case Member = 1;
}
