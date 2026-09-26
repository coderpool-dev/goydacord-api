<?php

namespace App\Broadcasting;

use App\Models\User;
use App\Services\Conversations\ChannelService;

/** Presence-канал беседы: подписаться могут только её участники. */
class ConversationChannel
{
    public function __construct(private readonly ChannelService $channels) {}

    public function join(User $user, int|string $channelId): array|false
    {
        if (! $this->channels->isMember((int) $user->id, (int) $channelId)) {
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
