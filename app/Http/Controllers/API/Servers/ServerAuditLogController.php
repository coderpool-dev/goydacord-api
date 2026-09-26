<?php

namespace App\Http\Controllers\API\Servers;

use App\Http\Controllers\Controller;
use App\Models\Servers\Server;
use App\Models\Servers\ServerAuditLog;
use App\Models\User;
use App\Services\Servers\ServerAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerAuditLogController extends Controller
{
    public function __construct(private readonly ServerAuditLogService $audit) {}

    /** ?before={id} — страница старее, ?action= — фильтр по действию, ?actor_id= — по тому, кто сделал. */
    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorize('viewAuditLog', $server);

        $entries = $this->audit->listForServer(
            $server,
            $request->integer('before') ?: null,
            $request->filled('action') ? (string) $request->string('action') : null,
            $request->integer('actor_id') ?: null,
        );

        return $this->successResponse('Журнал аудита', [
            'entries' => $entries->map(fn (ServerAuditLog $entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'actor' => $entry->actor ? [
                    'id' => $entry->actor->id,
                    'name' => $entry->actor->name ?? $entry->actor->login,
                    'avatar' => User::getAvatarUrl($entry->actor->avatar, $entry->actor->updated_at?->toISOString()),
                ] : null,
                'target_type' => $entry->target_type,
                'target_id' => $entry->target_id,
                'target_label' => $entry->target_label,
                'changes' => $entry->changes,
                'created_at' => $entry->created_at,
            ])->values(),
            'has_more' => $entries->count() === ServerAuditLogService::PAGE_SIZE,
        ]);
    }
}
