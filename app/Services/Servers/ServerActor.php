<?php

namespace App\Services\Servers;

use App\Models\Servers\ServerMember;

/**
 * Кто совершает действие на сервере: владелец или участник с правами и высшей ролью.
 * Высшая роль (topPosition) — основа иерархии как в Discord: управлять можно только тем,
 * что строго ниже неё. Владелец стоит над всеми ролями.
 */
final class ServerActor
{
    public function __construct(
        public readonly bool $isOwner,
        public readonly int $permissions,
        public readonly int $topPosition,
        public readonly ?ServerMember $member,
    ) {}
}
