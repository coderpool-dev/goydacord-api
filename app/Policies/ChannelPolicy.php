<?php

namespace App\Policies;

use App\Models\Conversations\Channel;
use App\Models\User;
use App\Services\Conversations\ChannelService;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    public function __construct(private readonly ChannelService $channels) {}

    public function view(User $user, Channel $channel): Response
    {
        return $this->allowMembers($user, $channel);
    }

    public function update(User $user, Channel $channel): Response
    {
        return $this->allowAdmins($user, $channel);
    }

    public function leave(User $user, Channel $channel): Response
    {
        return $this->allowMembers($user, $channel);
    }

    public function addMembers(User $user, Channel $channel): Response
    {
        return $this->allowMembers($user, $channel);
    }

    public function kick(User $user, Channel $channel): Response
    {
        return $this->allowAdmins($user, $channel);
    }

    public function setRole(User $user, Channel $channel): Response
    {
        return $this->allowAdmins($user, $channel);
    }

    public function createMessage(User $user, Channel $channel): Response
    {
        return $this->allowMembers($user, $channel);
    }

    /** Начать звонок, войти в него, выйти, слать heartbeat и превью демонстрации. */
    public function call(User $user, Channel $channel): Response
    {
        return $this->allowMembers($user, $channel);
    }

    private function allowMembers(User $user, Channel $channel): Response
    {
        return $this->channels->isMember((int) $user->id, (int) $channel->id)
            ? Response::allow()
            : Response::deny('Нет доступа');
    }

    private function allowAdmins(User $user, Channel $channel): Response
    {
        return $this->channels->isAdmin((int) $user->id, (int) $channel->id)
            ? Response::allow()
            : Response::deny('Нет прав');
    }
}
