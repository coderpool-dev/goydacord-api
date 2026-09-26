<?php

namespace App\Http\Controllers\API\Servers;

use App\Enums\ServerPermission;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\UpdateServerNicknameRequest;
use App\Http\Resources\ServerMemberResource;
use App\Models\Servers\Server;
use App\Models\Servers\ServerMember;
use App\Services\Servers\ServerAuditLogService;
use App\Services\Servers\ServerMemberService;
use App\Services\Servers\ServerRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerMemberController extends Controller
{
    public function __construct(
        private readonly ServerMemberService $members,
        private readonly ServerRoleService $roles,
        private readonly ServerAuditLogService $audit,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        $members = $this->members->activeMembers($server)
            ->map(fn (ServerMember $member) => new ServerMemberResource($member));

        return $this->successResponse('Список участников успешно получен', ['members' => $members]);
    }

    /**
     * Ник на сервере. Свой — с CHANGE_NICKNAME (по умолчанию у всех), чужой — с MANAGE_NICKNAMES
     * и только у того, кто ниже по иерархии. Пустой ник — сброс к имени аккаунта.
     */
    public function updateNickname(UpdateServerNicknameRequest $request, Server $server, ServerMember $member): JsonResponse
    {
        if ((int) $member->server_id !== (int) $server->id) {
            throw new ApiException('Участник не найден на этом сервере', 404);
        }

        $actor = $this->roles->actorFor($server, $request->user());
        $isSelf = (int) $member->user_id === (int) $request->user()->id;

        if ($isSelf && ($actor->isOwner || ServerPermission::has($actor->permissions, ServerPermission::CHANGE_NICKNAME))) {
            // свой ник — хватает CHANGE_NICKNAME
        } else {
            $this->authorize('manageNicknames', $server);
            if (! $isSelf) {
                $this->roles->assertOutranks($actor, $server, $member);
            }
        }

        $nickname = trim((string) $request->validated('nickname', ''));
        $oldNickname = $member->nickname;
        $member->update(['nickname' => $nickname !== '' ? $nickname : null]);
        if ($oldNickname !== $member->nickname) {
            $member->loadMissing('user');
            $this->audit->record($server, $request->user(), 'member.nickname', 'user', $member->user_id,
                (string) ($member->user->name ?? $member->user->login), ['nickname' => ['old' => $oldNickname, 'new' => $member->nickname]]);
        }

        return $this->successResponse('Ник обновлён', ['member' => new ServerMemberResource($member->load(['user', 'roles']))]);
    }

    public function destroy(Request $request, Server $server, ServerMember $member): JsonResponse
    {
        $this->authorize('kick', $server);

        if ((int) $member->server_id !== (int) $server->id) {
            throw new ApiException('Участник не найден на этом сервере', 404);
        }

        $this->roles->assertOutranks($this->roles->actorFor($server, $request->user()), $server, $member);

        $member->loadMissing('user');
        $label = $this->audit->userLabel($server, $member->user);
        $this->members->kick($server, $member);
        $this->audit->record($server, $request->user(), 'member.kick', 'user', $member->user_id, $label);

        return $this->successResponse('Участник исключён');
    }
}
