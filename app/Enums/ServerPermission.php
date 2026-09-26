<?php

namespace App\Enums;

/**
 * Битовая маска прав роли сервера (server_roles.permissions).
 * Обычный enum не годится под битовые операции сразу над несколькими правами,
 * поэтому это класс констант, а не backed enum.
 */
final class ServerPermission
{
    public const int VIEW_CHANNELS = 1 << 0;

    public const int SEND_MESSAGES = 1 << 1;

    public const int MANAGE_MESSAGES = 1 << 2;

    public const int CONNECT_VOICE = 1 << 3;

    public const int SPEAK = 1 << 4;

    public const int MANAGE_CHANNELS = 1 << 5;

    public const int MANAGE_ROLES = 1 << 6;

    public const int MANAGE_SERVER = 1 << 7;

    public const int KICK_MEMBERS = 1 << 8;

    public const int BAN_MEMBERS = 1 << 9;

    public const int CREATE_INVITE = 1 << 10;

    public const int MANAGE_INVITES = 1 << 11;

    public const int MENTION_EVERYONE = 1 << 12;

    public const int MANAGE_NICKNAMES = 1 << 13;

    /** Все права сразу и обход запретов на каналах — как «Администратор» в Discord. */
    public const int ADMINISTRATOR = 1 << 14;

    /** Заглушить участника в голосовых каналах сервера (server mute). */
    public const int MUTE_MEMBERS = 1 << 15;

    /** Отключить участнику звук в голосовых каналах сервера (server deafen). */
    public const int DEAFEN_MEMBERS = 1 << 16;

    /** Перемещать участников между голосовыми каналами и отключать их от голоса. */
    public const int MOVE_MEMBERS = 1 << 17;

    /** Когда говорит — остальные участники голосового канала звучат тише. */
    public const int PRIORITY_SPEAKER = 1 << 18;

    /** Менять свой ник на сервере. Чужие — MANAGE_NICKNAMES. */
    public const int CHANGE_NICKNAME = 1 << 19;

    /** Смотреть журнал аудита сервера. */
    public const int VIEW_AUDIT_LOG = 1 << 20;

    /** Права по умолчанию для роли @everyone — базовое участие. */
    public const int DEFAULT = self::VIEW_CHANNELS
        | self::SEND_MESSAGES
        | self::CONNECT_VOICE
        | self::SPEAK
        | self::CREATE_INVITE
        | self::CHANGE_NICKNAME;

    /** Все известные биты — валидация входящей маски от клиента (роль/оверрайд канала). */
    public const int ALL = self::VIEW_CHANNELS
        | self::SEND_MESSAGES
        | self::MANAGE_MESSAGES
        | self::CONNECT_VOICE
        | self::SPEAK
        | self::MANAGE_CHANNELS
        | self::MANAGE_ROLES
        | self::MANAGE_SERVER
        | self::KICK_MEMBERS
        | self::BAN_MEMBERS
        | self::CREATE_INVITE
        | self::MANAGE_INVITES
        | self::MENTION_EVERYONE
        | self::MANAGE_NICKNAMES
        | self::ADMINISTRATOR
        | self::MUTE_MEMBERS
        | self::DEAFEN_MEMBERS
        | self::MOVE_MEMBERS
        | self::PRIORITY_SPEAKER
        | self::CHANGE_NICKNAME
        | self::VIEW_AUDIT_LOG;

    /**
     * Права, которые можно переопределять per-channel (allow/deny в ServerChannelRoleOverwrite).
     * Административные биты (MANAGE_SERVER, MANAGE_ROLES, KICK_MEMBERS, ...) сервер-only,
     * как в Discord — их нельзя выдать/отнять только на одном канале.
     */
    public const int CHANNEL_OVERRIDABLE = self::VIEW_CHANNELS
        | self::SEND_MESSAGES
        | self::MANAGE_MESSAGES
        | self::CONNECT_VOICE
        | self::SPEAK
        | self::MENTION_EVERYONE;

    private function __construct() {}

    public static function has(int $mask, int $permission): bool
    {
        return ($mask & $permission) === $permission;
    }
}
