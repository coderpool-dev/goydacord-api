<?php

namespace App\Policies;

use App\Models\Conversations\Call;
use App\Models\Servers\ServerChannel;
use App\Models\User;
use App\Services\Conversations\ChannelService;
use App\Services\Servers\ServerService;
use Illuminate\Auth\Access\Response;

class CallPolicy
{
    public function __construct(
        private readonly ChannelService $channels,
        private readonly ServerService $servers,
    ) {}

    public function signal(User $user, Call $call): Response
    {
        if ($call->server_channel_id !== null) {
            $serverId = ServerChannel::query()->whereKey($call->server_channel_id)->value('server_id');

            return $serverId && $this->servers->isMember((int) $user->id, (int) $serverId)
                ? Response::allow()
                : Response::deny('Not a server member');
        }

        return $this->channels->isMember((int) $user->id, (int) $call->channel_id)
            ? Response::allow()
            : Response::deny('Not a channel member');
    }
}
