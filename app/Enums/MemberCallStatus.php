<?php

namespace App\Enums;

/**
 * Участник канала относительно звонка, колонка channels_members.call_status.
 * По ней строятся списки участников звонка, а подключения устройств лежат в call_sessions.
 */
enum MemberCallStatus: int
{
    case Idle = 0;

    // Значение 1 («звонит») было в первой схеме и больше не записывается.
    case InCall = 2;

    case Declined = 3;
}
