<?php

namespace App\Services\Servers;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Events\ServerVoiceModerated;
use App\Exceptions\ApiException;
use App\Models\Conversations\CallSession;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannel;
use App\Models\Servers\ServerMember;
use App\Models\User;
use App\Services\Conversations\LiveKitService;

/**
 * Модерация голоса на сервере как в Discord: заглушить/отключить звук на сервере,
 * переместить в другой голосовой канал, отключить от голоса. Право (MUTE_MEMBERS и т.п.)
 * проверяет ServerPolicy до вызова; здесь — иерархия и правила самого действия.
 */
class ServerVoiceModerationService
{
    public function __construct(
        private readonly ServerRoleService $roles,
        private readonly ServerChannelCallService $calls,
        private readonly ServerChannelPermissionResolver $resolver,
        private readonly LiveKitService $liveKit,
    ) {}

    public function setVoiceState(Server $server, ServerActor $actor, User $target, ?bool $muted, ?bool $deafened): ServerMember
    {
        $member = $this->targetMember($server, $actor, $target);

        if ($muted !== null) {
            $member->voice_muted = $muted;
        }
        if ($deafened !== null) {
            $member->voice_deafened = $deafened;
        }
        $member->save();
        $voiceChannelId = $this->currentVoiceChannelId($server, $target);

        // В LiveKit мьют модератора серверный: публиковать микрофон сервер просто не даст.
        if ($muted !== null && $voiceChannelId !== null) {
            $channel = ServerChannel::query()->find($voiceChannelId);
            $canSpeak = $channel !== null && ((int) $server->owner_id === (int) $target->id
                || ServerPermission::has($this->resolver->effectivePermissions($member, $channel), ServerPermission::SPEAK));
            $this->liveKit->setCanSpeak($voiceChannelId, (int) $target->id, $canSpeak && ! $member->voice_muted);
        }

        broadcast(new ServerVoiceModerated(
            (int) $target->id,
            (int) $server->id,
            'state',
            (bool) $member->voice_muted,
            (bool) $member->voice_deafened,
            $voiceChannelId,
        ));

        return $member;
    }

    /** $channelId = null — отключить от голоса. */
    public function move(Server $server, ServerActor $actor, User $target, ?int $channelId): void
    {
        $member = $this->targetMember($server, $actor, $target);
        $currentChannelId = $this->currentVoiceChannelId($server, $target)
            ?? throw new ApiException('Участник сейчас не в голосовом канале', 422);

        if ($channelId === null) {
            $this->calls->leave($target, ServerChannel::query()->findOrFail($currentChannelId), null);
            $this->liveKit->removeParticipant($currentChannelId, (int) $target->id);
            broadcast(new ServerVoiceModerated(
                (int) $target->id, (int) $server->id, 'disconnect',
                (bool) $member->voice_muted, (bool) $member->voice_deafened, null,
            ));

            return;
        }

        if ($channelId === $currentChannelId) {
            return;
        }

        $channel = ServerChannel::query()
            ->whereKey($channelId)
            ->where('server_id', $server->id)
            ->where('kind', ServerChannelKind::Voice)
            ->first() ?? throw new ApiException('Голосовой канал не найден на этом сервере', 404);

        // Переносим только туда, куда человек и сам мог бы зайти.
        if ((int) $server->owner_id !== (int) $target->id
            && ! ServerPermission::has($this->resolver->effectivePermissions($member, $channel), ServerPermission::CONNECT_VOICE)
        ) {
            throw new ApiException('У участника нет доступа к этому голосовому каналу', 422);
        }

        // Сам вход делает клиент перемещаемого (join() уже умеет «выйти из старого — войти в новый»):
        // сессия и P2P-соединения живут на его стороне.
        broadcast(new ServerVoiceModerated(
            (int) $target->id, (int) $server->id, 'move',
            (bool) $member->voice_muted, (bool) $member->voice_deafened, (int) $channel->id,
        ));
    }

    private function targetMember(Server $server, ServerActor $actor, User $target): ServerMember
    {
        $member = ServerMember::query()
            ->where('server_id', $server->id)
            ->where('user_id', $target->id)
            ->active()
            ->with('roles')
            ->first() ?? throw new ApiException('Участник не найден на этом сервере', 404);

        // Себя модерировать можно (как в Discord), остальных — только ниже себя.
        if (! $actor->member || (int) $actor->member->id !== (int) $member->id) {
            if (! ($actor->isOwner && (int) $server->owner_id === (int) $target->id)) {
                $this->roles->assertOutranks($actor, $server, $member);
            }
        }

        return $member;
    }

    private function currentVoiceChannelId(Server $server, User $target): ?int
    {
        $channelId = CallSession::query()
            ->where('user_id', $target->id)
            ->whereIn('server_channel_id', ServerChannel::query()->where('server_id', $server->id)->select('id'))
            ->latest('last_seen_at')
            ->value('server_channel_id');

        return $channelId !== null ? (int) $channelId : null;
    }
}
