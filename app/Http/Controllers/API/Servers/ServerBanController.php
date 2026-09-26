<?php

namespace App\Http\Controllers\API\Servers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\StoreServerBanRequest;
use App\Http\Resources\ServerBanResource;
use App\Models\Servers\Server;
use App\Models\Servers\ServerBan;
use App\Services\Servers\ServerAuditLogService;
use App\Services\Servers\ServerBanService;
use App\Services\Servers\ServerMemberService;
use App\Services\Servers\ServerRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerBanController extends Controller
{
    public function __construct(
        private readonly ServerBanService $bans,
        private readonly ServerRoleService $roles,
        private readonly ServerAuditLogService $audit,
        private readonly ServerMemberService $members,
    ) {}

    public function index(Server $server): JsonResponse
    {
        $this->authorize('ban', $server);

        $bans = $this->bans->listForServer($server)
            ->map(fn (ServerBan $ban) => new ServerBanResource($ban));

        return $this->successResponse('Список банов успешно получен', ['bans' => $bans]);
    }

    public function store(StoreServerBanRequest $request, Server $server): JsonResponse
    {
        $this->authorize('ban', $server);

        $target = $request->target();

        // Банить можно и не участника (превентивно) — тогда иерархию проверять не с чем.
        $targetMember = $this->members->activeMember((int) $server->id, (int) $target->id);
        if ($targetMember) {
            $this->roles->assertOutranks($this->roles->actorFor($server, $request->user()), $server, $targetMember);
        }
        $label = $this->audit->userLabel($server, $target);
        $reason = $request->reason();
        $ban = $this->bans->ban($server, $target, $request->user(), $reason);
        $this->audit->record($server, $request->user(), 'member.ban', 'user', $target->id, $label, $reason ? ['reason' => $reason] : null);

        return $this->successResponse('Пользователь забанен', ['ban' => new ServerBanResource($ban->load(['user', 'bannedBy']))], 201);
    }

    public function destroy(Request $request, Server $server, ServerBan $ban): JsonResponse
    {
        $this->authorize('ban', $server);

        if ((int) $ban->server_id !== (int) $server->id) {
            throw new ApiException('Бан не найден на этом сервере', 404);
        }

        $bannedUser = $ban->user;
        $this->bans->unban($ban);
        $this->audit->record($server, $request->user(), 'member.unban', 'user', $ban->user_id,
            $bannedUser ? (string) ($bannedUser->name ?? $bannedUser->login) : null);

        return $this->successResponse('Пользователь разбанен');
    }
}
