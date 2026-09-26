<?php

namespace App\Services\Servers;

use App\Enums\ServerMembershipStatus;
use App\Exceptions\ApiException;
use App\Models\Servers\Server;
use App\Models\Servers\ServerBan;
use App\Models\Servers\ServerMember;
use App\Models\User;
use Illuminate\Support\Collection;

/** Баны сервера: права проверяет ServerPolicy::ban до вызова сервиса. */
class ServerBanService
{
    public function listForServer(Server $server): Collection
    {
        return ServerBan::query()
            ->where('server_id', $server->id)
            ->with(['user:id,name,login,avatar', 'bannedBy:id,name,login'])
            ->latest()
            ->get();
    }

    public function ban(Server $server, User $target, User $bannedBy, ?string $reason): ServerBan
    {
        if ((int) $target->id === (int) $server->owner_id) {
            throw new ApiException('Владельца сервера нельзя забанить', 422);
        }

        if ($this->isBanned($server, (int) $target->id)) {
            throw new ApiException('Пользователь уже забанен', 422);
        }

        $ban = ServerBan::create([
            'server_id' => $server->id,
            'user_id' => $target->id,
            'banned_by' => $bannedBy->id,
            'reason' => $reason,
        ]);

        ServerMember::query()
            ->where('server_id', $server->id)
            ->where('user_id', $target->id)
            ->update(['status' => ServerMembershipStatus::Removed]);

        return $ban;
    }

    public function unban(ServerBan $ban): void
    {
        $ban->delete();
    }

    public function isBanned(Server $server, int $userId): bool
    {
        return ServerBan::query()
            ->where('server_id', $server->id)
            ->where('user_id', $userId)
            ->exists();
    }
}
