<?php

namespace App\Enums;

/** Тип канала сервера, колонка server_channels.kind. */
enum ServerChannelKind: int
{
    case Text = 1;
    case Voice = 2;
    case Forum = 3;
    case Category = 4;
    /** Новостной/объявления-канал: тот же пайплайн сообщений, что у Text, отдельная иконка
     *  на фронте; кто может писать — настраивается через overwrites канала (та же система,
     *  что у приватных каналов), отдельного права заводить не стали. */
    case News = 5;
}
