<?php

namespace App\Http\Controllers\API\Servers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\MoveVoiceMemberRequest;
use App\Http\Requests\Servers\UpdateVoiceStateRequest;
use App\Models\Servers\Server;
use App\Models\User;
use App\Services\Servers\ServerAuditLogService;
use App\Services\Servers\ServerRoleService;
use App\Services\Servers\ServerVoiceModerationService;
use Illuminate\Http\JsonResponse;

/** Заглушить / отключить звук / переместить / отключить участника в голосовых каналах сервера. */
class ServerVoiceModerationController extends Controller
{
    public function __construct(
        private readonly ServerVoiceModerationService $moderation,
        private readonly ServerRoleService $roles,
        private readonly ServerAuditLogService $audit,
    ) {}

    public function updateState(UpdateVoiceStateRequest $request, Server $server, User $user): JsonResponse
    {
        if ($request->has('muted')) {
            $this->authorize('muteMembers', $server);
        }
        if ($request->has('deafened')) {
            $this->authorize('deafenMembers', $server);
        }

        $member = $this->moderation->setVoiceState(
            $server,
            $this->roles->actorFor($server, $request->user()),
            $user,
            $request->has('muted') ? $request->boolean('muted') : null,
            $request->has('deafened') ? $request->boolean('deafened') : null,
        );
        $label = $this->audit->userLabel($server, $user);
        if ($request->has('muted')) {
            $this->audit->record($server, $request->user(), $request->boolean('muted') ? 'voice.mute' : 'voice.unmute', 'user', $user->id, $label);
        }
        if ($request->has('deafened')) {
            $this->audit->record($server, $request->user(), $request->boolean('deafened') ? 'voice.deafen' : 'voice.undeafen', 'user', $user->id, $label);
        }

        return $this->successResponse('Голосовое состояние участника обновлено', [
            'voice_muted' => (bool) $member->voice_muted,
            'voice_deafened' => (bool) $member->voice_deafened,
        ]);
    }

    public function move(MoveVoiceMemberRequest $request, Server $server, User $user): JsonResponse
    {
        $this->authorize('moveMembers', $server);

        $channelId = $request->input('server_channel_id');
        $this->moderation->move(
            $server,
            $this->roles->actorFor($server, $request->user()),
            $user,
            $channelId !== null ? (int) $channelId : null,
        );
        $this->audit->record(
            $server,
            $request->user(),
            $channelId !== null ? 'voice.move' : 'voice.disconnect',
            'user',
            $user->id,
            $this->audit->userLabel($server, $user),
            $channelId !== null ? ['channel' => ['new' => $server->channels()->whereKey($channelId)->value('name')]] : null,
        );

        return $this->successResponse($channelId !== null ? 'Участник перемещён' : 'Участник отключён от голосового канала');
    }
}
