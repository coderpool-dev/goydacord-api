<?php

namespace App\Policies;

use App\Enums\ServerPermission;
use App\Models\Servers\Server;
use App\Models\User;
use App\Services\Servers\ServerRoleService;
use App\Services\Servers\ServerService;
use Illuminate\Auth\Access\Response;

/** Владелец обходит все проверки; остальные — по битам ServerPermission их ролей. */
class ServerPolicy
{
    public function __construct(
        private readonly ServerService $servers,
        private readonly ServerRoleService $roles,
    ) {}

    public function view(User $user, Server $server): Response
    {
        return $this->allowMembers($user, $server);
    }

    public function update(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MANAGE_SERVER);
    }

    /** Сервер удаляет только владелец — это не выдаваемое право, как в Discord. */
    public function delete(User $user, Server $server): Response
    {
        return $this->allowOwner($user, $server);
    }

    public function leave(User $user, Server $server): Response
    {
        return $this->allowMembers($user, $server);
    }

    /** Создание/изменение каналов. */
    public function manageChannels(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MANAGE_CHANNELS);
    }

    /** Просмотр/отзыв ЛЮБЫХ инвайтов сервера. Создание своего — см. createInvite. */
    public function manageInvites(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MANAGE_INVITES);
    }

    public function createInvite(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::CREATE_INVITE);
    }

    public function kick(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::KICK_MEMBERS);
    }

    public function ban(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::BAN_MEMBERS);
    }

    public function manageRoles(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MANAGE_ROLES);
    }

    public function viewAuditLog(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::VIEW_AUDIT_LOG);
    }

    public function manageNicknames(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MANAGE_NICKNAMES);
    }

    public function muteMembers(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MUTE_MEMBERS);
    }

    public function deafenMembers(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::DEAFEN_MEMBERS);
    }

    public function moveMembers(User $user, Server $server): Response
    {
        return $this->allowPermission($user, $server, ServerPermission::MOVE_MEMBERS);
    }

    private function allowMembers(User $user, Server $server): Response
    {
        return $this->servers->isMember((int) $user->id, (int) $server->id)
            ? Response::allow()
            : Response::deny('Нет доступа');
    }

    private function allowOwner(User $user, Server $server): Response
    {
        return $this->servers->isOwner((int) $user->id, (int) $server->id)
            ? Response::allow()
            : Response::deny('Нет прав');
    }

    private function allowPermission(User $user, Server $server, int $permission): Response
    {
        if ($this->servers->isOwner((int) $user->id, (int) $server->id)) {
            return Response::allow();
        }

        $member = $this->servers->activeMembershipWithRoles((int) $user->id, (int) $server->id);

        if (! $member) {
            return Response::deny('Нет доступа');
        }

        return ServerPermission::has($this->roles->basePermissions($member), $permission)
            ? Response::allow()
            : Response::deny('Нет прав');
    }
}
