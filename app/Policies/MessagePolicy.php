<?php

namespace App\Policies;

use App\Enums\ServerPermission;
use App\Models\Conversations\Message;
use App\Models\Servers\ServerChannel;
use App\Models\User;
use App\Services\Conversations\ChannelService;
use App\Services\Servers\ServerChannelPermissionResolver;
use App\Services\Servers\ServerService;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function __construct(
        private readonly ChannelService $channels,
        private readonly ServerService $servers,
        private readonly ServerChannelPermissionResolver $channelPermissions,
    ) {}

    public function view(User $user, Message $message): Response
    {
        return $this->allowMembers($user, $message);
    }

    public function update(User $user, Message $message): Response
    {
        $access = $this->allowMembers($user, $message);
        if (! $access->allowed()) {
            return $access;
        }

        if ((int) $message->user_id !== (int) $user->id || $message->type === 'system') {
            return Response::deny('Можно изменять только свои сообщения');
        }

        return Response::allow();
    }

    public function delete(User $user, Message $message): Response
    {
        $access = $this->allowMembers($user, $message);
        if (! $access->allowed()) {
            return $access;
        }

        if ($message->type === 'system') {
            return Response::deny('Системное сообщение нельзя удалить');
        }

        if ((int) $message->user_id === (int) $user->id) {
            return Response::allow();
        }

        // Чужое сообщение может удалить только модератор с MANAGE_MESSAGES на канале сервера
        // (владелец сервера уже проходит через allowMembers→isOwner-независимую проверку ниже).
        // Редактировать чужие сообщения нельзя даже с этим правом — как в Discord, только удалять.
        if ($message->server_channel_id !== null && $this->canManageServerMessages($user, (int) $message->server_channel_id)) {
            return Response::allow();
        }

        return Response::deny('Можно удалять только свои сообщения');
    }

    private function canManageServerMessages(User $user, int $serverChannelId): bool
    {
        $channel = ServerChannel::query()->find($serverChannelId);

        if (! $channel) {
            return false;
        }

        if ($this->servers->isOwner((int) $user->id, (int) $channel->server_id)) {
            return true;
        }

        $member = $this->servers->activeMembershipWithRoles((int) $user->id, (int) $channel->server_id);

        return $member !== null
            && ServerPermission::has($this->channelPermissions->effectivePermissions($member, $channel), ServerPermission::MANAGE_MESSAGES);
    }

    public function react(User $user, Message $message): Response
    {
        $access = $this->allowMembers($user, $message);
        if (! $access->allowed()) {
            return $access;
        }

        if ($message->type === 'system') {
            return Response::deny('На системные сообщения нельзя ставить реакции');
        }

        return Response::allow();
    }

    private function allowMembers(User $user, Message $message): Response
    {
        if ($message->channels_id !== null) {
            return $this->channels->isMember((int) $user->id, (int) $message->channels_id)
                ? Response::allow()
                : Response::deny('Нет доступа');
        }

        $serverId = ServerChannel::query()->whereKey($message->server_channel_id)->value('server_id');

        return $serverId && $this->servers->isMember((int) $user->id, (int) $serverId)
            ? Response::allow()
            : Response::deny('Нет доступа');
    }
}
