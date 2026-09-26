<?php

namespace App\Policies;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Models\Servers\ServerChannel;
use App\Models\User;
use App\Services\Servers\ServerChannelPermissionResolver;
use App\Services\Servers\ServerService;
use Illuminate\Auth\Access\Response;

class ServerChannelPolicy
{
    public function __construct(
        private readonly ServerService $servers,
        private readonly ServerChannelPermissionResolver $resolver,
    ) {}

    public function view(User $user, ServerChannel $channel): Response
    {
        return $this->allowChannelPermission($user, $channel, ServerPermission::VIEW_CHANNELS);
    }

    public function createMessage(User $user, ServerChannel $channel): Response
    {
        if ($channel->kind !== ServerChannelKind::Text && $channel->kind !== ServerChannelKind::News) {
            return Response::deny('Сообщения можно отправлять только в текстовый или новостной канал');
        }

        return $this->allowChannelPermission($user, $channel, ServerPermission::SEND_MESSAGES);
    }

    public function call(User $user, ServerChannel $channel): Response
    {
        return $this->allowChannelPermission($user, $channel, ServerPermission::CONNECT_VOICE);
    }

    private function allowChannelPermission(User $user, ServerChannel $channel, int $permission): Response
    {
        if ($this->servers->isOwner((int) $user->id, (int) $channel->server_id)) {
            return Response::allow();
        }

        $member = $this->servers->activeMembershipWithRoles((int) $user->id, (int) $channel->server_id);

        if (! $member) {
            return Response::deny('Нет доступа');
        }

        return ServerPermission::has($this->resolver->effectivePermissions($member, $channel), $permission)
            ? Response::allow()
            : Response::deny('Нет прав');
    }
}
