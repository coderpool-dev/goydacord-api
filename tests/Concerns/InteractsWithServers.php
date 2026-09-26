<?php

namespace Tests\Concerns;

use App\Enums\ServerChannelKind;
use App\Enums\ServerMembershipStatus;
use App\Enums\ServerPermission;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannel;
use App\Models\Servers\ServerInvite;
use App\Models\Servers\ServerMember;
use App\Models\Servers\ServerRole;
use App\Models\User;
use Illuminate\Support\Str;

trait InteractsWithServers
{
    protected int $serverSeq = 0;

    protected function makeServer(User $owner, array $attributes = []): Server
    {
        $this->serverSeq++;

        return Server::create(array_merge([
            'name' => 'Server '.$this->serverSeq,
            'owner_id' => $owner->id,
        ], $attributes));
    }

    /**
     * Вешает дефолтную роль @everyone (создаёт её при первом вызове для сервера) — так же,
     * как реальные ServerService::create()/ServerInviteService::join(). Без роли участник
     * получил бы ноль прав под новой битовой системой (Discord-style allow/deny, Фаза 4).
     */
    protected function addServerMember(
        Server $server,
        User $user,
        ServerMembershipStatus $status = ServerMembershipStatus::Member,
    ): ServerMember {
        $member = ServerMember::create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'status' => $status,
            'joined_at' => now(),
        ]);

        $defaultRole = ServerRole::query()->where('server_id', $server->id)->where('is_default', true)->first()
            ?? ServerRole::create([
                'server_id' => $server->id,
                'name' => 'everyone',
                'permissions' => ServerPermission::DEFAULT,
                'is_default' => true,
            ]);

        $member->roles()->attach($defaultRole->id);

        return $member;
    }

    protected function makeServerRole(Server $server, array $attributes = []): ServerRole
    {
        // Каждая новая роль — выше предыдущих (и выше @everyone с position 0), иначе иерархия
        // в тестах вырождалась бы: все роли на одном уровне, никто никем не управляет.
        return ServerRole::create(array_merge([
            'server_id' => $server->id,
            'name' => 'Role '.Str::random(5),
            'permissions' => ServerPermission::DEFAULT,
            'position' => (int) ServerRole::query()->where('server_id', $server->id)->max('position') + 1,
        ], $attributes));
    }

    protected function makeServerChannel(Server $server, array $attributes = []): ServerChannel
    {
        return ServerChannel::create(array_merge([
            'server_id' => $server->id,
            'name' => 'channel-'.Str::random(5),
            'kind' => ServerChannelKind::Text,
        ], $attributes));
    }

    protected function makeServerInvite(Server $server, User $creator, array $attributes = []): ServerInvite
    {
        return ServerInvite::create(array_merge([
            'code' => Str::random(8),
            'server_id' => $server->id,
            'created_by' => $creator->id,
        ], $attributes));
    }
}
