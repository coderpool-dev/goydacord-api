<?php

namespace App\Services\Servers;

use App\Enums\ServerMembershipStatus;
use App\Exceptions\ApiException;
use App\Models\Servers\Server;
use App\Models\Servers\ServerMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Участники сервера: список, поиск, ник, исключение. Права проверяет ServerPolicy до вызова сервиса. */
class ServerMemberService
{
    /** @return Collection<int, ServerMember> */
    public function activeMembers(Server $server): Collection
    {
        return ServerMember::query()
            ->where('server_id', $server->id)
            ->active()
            ->with(['user', 'roles'])
            ->get();
    }

    public function activeMember(int $serverId, int $userId): ?ServerMember
    {
        return ServerMember::query()
            ->where('server_id', $serverId)
            ->where('user_id', $userId)
            ->active()
            ->first();
    }

    /** Как человека показывать на сервере: ник, если задан, иначе имя аккаунта. */
    public function displayName(int $serverId, User $user): string
    {
        $nickname = $this->activeMember($serverId, (int) $user->id)?->nickname;

        return $nickname ?: (string) ($user->name ?? $user->login);
    }

    public function kick(Server $server, ServerMember $member): void
    {
        if ((int) $member->user_id === (int) $server->owner_id) {
            throw new ApiException('Владельца сервера нельзя исключить', 422);
        }

        $member->update(['status' => ServerMembershipStatus::Removed]);
        $member->roles()->detach();
    }
}
