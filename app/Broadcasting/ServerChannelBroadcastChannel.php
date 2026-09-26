<?php

namespace App\Broadcasting;

use App\Models\Servers\ServerChannel;
use App\Models\User;
use App\Services\Servers\ServerService;

/** Presence-канал текстового канала сервера: подписаться могут только участники сервера. */
class ServerChannelBroadcastChannel
{
    public function __construct(private readonly ServerService $servers) {}

    public function join(User $user, int|string $serverChannelId): array|false
    {
        $channel = ServerChannel::find($serverChannelId);

        if (! $channel || ! $this->servers->isMember((int) $user->id, (int) $channel->server_id)) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
            'avatar' => $user->avatar,
        ];
    }
}
