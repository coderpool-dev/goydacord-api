<?php

namespace App\Services\Servers;

use App\Enums\ServerPermission;
use App\Models\Servers\ServerChannel;
use App\Models\Servers\ServerChannelRoleOverwrite;
use App\Models\Servers\ServerMember;
use App\Models\Servers\ServerRole;

/**
 * Эффективные права участника на конкретном канале сервера — единственное место,
 * где применяется алгоритм allow/deny (см. App\Enums\ServerPermission::CHANNEL_OVERRIDABLE).
 * Владелец сервера обходит эту проверку целиком — решается в policy, не здесь.
 */
class ServerChannelPermissionResolver
{
    public function __construct(private readonly ServerRoleService $roles) {}

    public function effectivePermissions(ServerMember $member, ServerChannel $channel): int
    {
        $permissions = $this->roles->basePermissions($member);

        // «Администратор» не ограничивается запретами на каналах — как в Discord.
        if (ServerPermission::has($permissions, ServerPermission::ADMINISTRATOR)) {
            return ServerPermission::ALL;
        }

        $roleIds = $member->roles->pluck('id');
        $defaultRoleId = $member->roles->first(fn (ServerRole $role) => $role->is_default)?->id;

        $channel->loadMissing(['roleOverwrites', 'memberOverwrites']);
        $roleOverwrites = $channel->roleOverwrites->whereIn('server_role_id', $roleIds);

        // Порядок как в Discord: сначала @everyone, потом остальные роли вместе (deny, затем
        // allow — при конфликте между ролями allow побеждает), последним — сам участник.
        // Так можно явно открыть канал @everyone и всё равно закрыть его одной роли.
        $everyone = $roleOverwrites->firstWhere('server_role_id', $defaultRoleId);
        if ($everyone) {
            $permissions = ($permissions & ~$everyone->deny) | $everyone->allow;
        }

        $others = $roleOverwrites->reject(fn (ServerChannelRoleOverwrite $overwrite) => (int) $overwrite->server_role_id === (int) $defaultRoleId);
        $deny = $others->reduce(fn (int $mask, ServerChannelRoleOverwrite $overwrite) => $mask | $overwrite->deny, 0);
        $allow = $others->reduce(fn (int $mask, ServerChannelRoleOverwrite $overwrite) => $mask | $overwrite->allow, 0);
        $permissions = ($permissions & ~$deny) | $allow;

        $own = $channel->memberOverwrites->firstWhere('server_member_id', $member->id);
        if ($own) {
            $permissions = ($permissions & ~$own->deny) | $own->allow;
        }

        return $permissions;
    }
}
