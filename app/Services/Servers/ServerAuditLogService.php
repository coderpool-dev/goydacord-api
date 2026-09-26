<?php

namespace App\Services\Servers;

use App\Models\Servers\Server;
use App\Models\Servers\ServerAuditLog;
use App\Models\Servers\ServerMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Журнал аудита сервера: пишется контроллерами ПОСЛЕ успешного действия. Сбой записи журнала
 * не должен ронять само действие (кик уже случился) — поэтому только лог ошибки.
 */
class ServerAuditLogService
{
    public const PAGE_SIZE = 50;

    public function record(
        Server|int $server,
        ?User $actor,
        string $action,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?string $targetLabel = null,
        ?array $changes = null,
    ): void {
        try {
            ServerAuditLog::create([
                'server_id' => $server instanceof Server ? $server->id : $server,
                'actor_id' => $actor?->id,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId !== null ? (int) $targetId : null,
                'target_label' => $targetLabel !== null ? mb_substr($targetLabel, 0, 255) : null,
                'changes' => $changes ?: null,
            ]);
        } catch (Throwable $e) {
            Log::warning('audit_log_write_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** Имя пользователя для журнала: ник на сервере, иначе имя аккаунта. */
    public function userLabel(Server|int $server, User $user): string
    {
        $nickname = ServerMember::query()
            ->where('server_id', $server instanceof Server ? $server->id : $server)
            ->where('user_id', $user->id)
            ->value('nickname');

        return $nickname ?: (string) ($user->name ?? $user->login);
    }

    /** @return Collection<int, ServerAuditLog> новые сверху, ?before — страница старее */
    public function listForServer(Server $server, ?int $before, ?string $action, ?int $actorId): Collection
    {
        return ServerAuditLog::query()
            ->where('server_id', $server->id)
            ->when($before, fn ($query) => $query->where('id', '<', $before))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($actorId, fn ($query) => $query->where('actor_id', $actorId))
            ->with('actor:id,name,login,avatar,updated_at')
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE)
            ->get();
    }
}
