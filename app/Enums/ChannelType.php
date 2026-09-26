<?php

namespace App\Enums;

/** Тип канала. Хранится в колонке channels.status. */
enum ChannelType: int
{
    /** Беседа: своё название, аватар и админы. */
    case Group = 1;

    /** Личный чат двух пользователей. */
    case Private = 2;
}
