<?php

namespace App\Services\Conversations;

use App\Enums\CallStatus;
use App\Enums\MemberCallStatus;
use App\Events\CallParticipantJoined;
use App\Events\CallParticipantLeft;
use App\Events\CallScreenShareStarted;
use App\Events\CallScreenShareStopped;
use App\Events\CallSessionSuperseded;
use App\Events\ServerVoiceParticipantLeft;
use App\Models\Conversations\Call;
use App\Models\Conversations\CallSession;
use App\Models\Conversations\ChannelMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Подключения устройств к звонку. У пользователя одна живая сессия на звонок, и она жива,
 * пока приходит heartbeat. По сессиям видно, кто реально в звонке и кто показывает экран.
 */
class CallPresenceService
{
    /** Вход с другого устройства вытесняет прежние сессии: старое устройство получает CallSessionSuperseded и выходит само. */
    public function registerSession(Call $call, User $user, string $sessionId): void
    {
        $replaced = CallSession::query()
            ->where('call_id', $call->call_id)
            ->where('user_id', $user->id)
            ->where('session_id', '!=', $sessionId)
            ->delete();

        if ($replaced > 0) {
            broadcast(new CallSessionSuperseded((int) $user->id, $call->call_id, $sessionId));
        }

        CallSession::query()->updateOrCreate(
            ['call_id' => $call->call_id, 'session_id' => $sessionId],
            ['user_id' => $user->id, 'channel_id' => $call->channel_id, 'last_seen_at' => now()],
        );
    }

    /**
     * Продлевает сессию и сообщает клиенту, актуальна ли она. Если сессию вытеснило
     * другое устройство, клиент по ответу выходит из звонка.
     *
     * @return array{active: bool, superseded: bool, call_id?: string}
     */
    public function heartbeat(User $user, int $channelId, string $sessionId, bool $screenSharing = false): array
    {
        $call = Call::activeIn($channelId);

        if (! $call) {
            return ['active' => false, 'superseded' => false];
        }

        $sessionExists = CallSession::query()
            ->where('call_id', $call->call_id)
            ->where('session_id', $sessionId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $sessionExists && $this->hasSession($call->call_id, (int) $user->id)) {
            return ['active' => false, 'superseded' => true, 'call_id' => $call->call_id];
        }

        $wasSharing = $sessionExists && $this->isScreenSharing($call->call_id, (int) $user->id);

        CallSession::query()->updateOrCreate(
            ['call_id' => $call->call_id, 'session_id' => $sessionId],
            ['user_id' => $user->id, 'channel_id' => $channelId, 'last_seen_at' => now(), 'screen_sharing' => $screenSharing],
        );

        // Статус участника могла сбросить уборка в окно между пингами — поднимаем обратно.
        ChannelMember::query()
            ->where('channels_id', $channelId)
            ->where('users_id', $user->id)
            ->where('call_status', '!=', MemberCallStatus::InCall)
            ->update(['call_status' => MemberCallStatus::InCall]);

        $this->broadcastScreenSharingChange($call, $user, $wasSharing);

        if ($sessionExists) {
            $this->pruneStaleSessions(collect([$channelId]));
        } else {
            // Сессии не было, но heartbeat пришёл: устройство живо, просто пропустило пинги, и сессию
            // убрала уборка. Если та успела разослать «вышел», сообщаем, что пользователь снова в звонке.
            broadcast(new CallParticipantJoined($call, $user, true))->toOthers();
        }

        return ['active' => true, 'superseded' => false, 'call_id' => $call->call_id];
    }

    /** @return bool остались ли у пользователя сессии в звонке на других устройствах */
    public function disconnectDevice(Call $call, User $user, ?string $sessionId = null): bool
    {
        CallSession::query()
            ->where('call_id', $call->call_id)
            ->where('user_id', $user->id)
            ->when($sessionId, fn ($query) => $query->where('session_id', $sessionId))
            ->delete();

        return $this->hasSession($call->call_id, (int) $user->id);
    }

    /** @return int сколько сессий удалено */
    public function disconnectFromChannel(int $channelId, User $user): int
    {
        return CallSession::query()
            ->where('channel_id', $channelId)
            ->where('user_id', $user->id)
            ->delete();
    }

    /** @return Collection<int, int> */
    public function channelIdsWithSessions(User $user, int $exceptChannelId): Collection
    {
        return CallSession::query()
            ->where('user_id', $user->id)
            ->where('channel_id', '!=', $exceptChannelId)
            ->pluck('channel_id');
    }

    /**
     * Голосовые каналы сервера: аналог registerSession, но без ChannelMember — присутствие
     * целиком выводится из CallSession, отдельной таблицы статуса участников тут нет.
     */
    public function registerServerChannelSession(Call $call, User $user, string $sessionId): void
    {
        $replaced = CallSession::query()
            ->where('call_id', $call->call_id)
            ->where('user_id', $user->id)
            ->where('session_id', '!=', $sessionId)
            ->delete();

        if ($replaced > 0) {
            broadcast(new CallSessionSuperseded((int) $user->id, $call->call_id, $sessionId));
        }

        CallSession::query()->updateOrCreate(
            ['call_id' => $call->call_id, 'session_id' => $sessionId],
            ['user_id' => $user->id, 'server_channel_id' => $call->server_channel_id, 'last_seen_at' => now()],
        );
    }

    /** @return array{active: bool, superseded: bool, call_id?: string} */
    public function heartbeatServerChannel(User $user, int $serverChannelId, string $sessionId, bool $screenSharing = false): array
    {
        $call = Call::activeInServerChannel($serverChannelId);

        if (! $call) {
            return ['active' => false, 'superseded' => false];
        }

        $sessionExists = CallSession::query()
            ->where('call_id', $call->call_id)
            ->where('session_id', $sessionId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $sessionExists && $this->hasSession($call->call_id, (int) $user->id)) {
            return ['active' => false, 'superseded' => true, 'call_id' => $call->call_id];
        }

        $wasSharing = $sessionExists && $this->isScreenSharing($call->call_id, (int) $user->id);

        CallSession::query()->updateOrCreate(
            ['call_id' => $call->call_id, 'session_id' => $sessionId],
            ['user_id' => $user->id, 'server_channel_id' => $serverChannelId, 'last_seen_at' => now(), 'screen_sharing' => $screenSharing],
        );

        $this->broadcastScreenSharingChange($call, $user, $wasSharing);

        if ($sessionExists) {
            $this->pruneStaleServerChannelSessions(collect([$serverChannelId]));
        }

        return ['active' => true, 'superseded' => false, 'call_id' => $call->call_id];
    }

    /** @return bool остались ли у пользователя сессии в этом голосовом канале на других устройствах */
    public function disconnectServerChannelDevice(Call $call, User $user, ?string $sessionId = null): bool
    {
        CallSession::query()
            ->where('call_id', $call->call_id)
            ->where('user_id', $user->id)
            ->when($sessionId, fn ($query) => $query->where('session_id', $sessionId))
            ->delete();

        return $this->hasSession($call->call_id, (int) $user->id);
    }

    /**
     * Удаляет сессии без heartbeat дольше CallSession::STALE_SECONDS в голосовых каналах сервера.
     *
     * @param  Collection<int, int>  $serverChannelIds
     */
    public function pruneStaleServerChannelSessions(Collection $serverChannelIds): void
    {
        if ($serverChannelIds->isEmpty()) {
            return;
        }

        $threshold = now()->subSeconds(CallSession::STALE_SECONDS);

        $stale = CallSession::query()
            ->whereIn('server_channel_id', $serverChannelIds)
            ->where(fn ($query) => $query
                ->where('last_seen_at', '<=', $threshold)
                ->orWhere(fn ($query) => $query->whereNull('last_seen_at')->where('created_at', '<=', $threshold)))
            ->with(['call', 'user'])
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        CallSession::query()->whereKey($stale->modelKeys())->delete();

        foreach ($stale->unique(fn (CallSession $session) => $session->server_channel_id.':'.$session->user_id) as $session) {
            if ($this->hasSession($session->call_id, (int) $session->user_id)) {
                continue;
            }

            if ($session->call?->status === CallStatus::Active && $session->user) {
                broadcast(new ServerVoiceParticipantLeft($session->call, $session->user))->toOthers();
            }
        }

        foreach ($stale->where('screen_sharing', true)->unique('user_id') as $session) {
            $stillSharing = $this->isScreenSharing($session->call_id, (int) $session->user_id);

            if ($session->call?->status === CallStatus::Active && $session->user && ! $stillSharing) {
                broadcast(new CallScreenShareStopped($session->call, $session->user))->toOthers();
            }
        }
    }

    /**
     * Удаляет сессии без heartbeat дольше CallSession::STALE_SECONDS и приводит статус участников
     * к реальности: закрывший вкладку клиент перестаёт висеть «призраком» в списке звонка.
     *
     * @param  Collection<int, int>  $channelIds
     */
    public function pruneStaleSessions(Collection $channelIds): void
    {
        if ($channelIds->isEmpty()) {
            return;
        }

        $threshold = now()->subSeconds(CallSession::STALE_SECONDS);

        $stale = CallSession::query()
            ->whereIn('channel_id', $channelIds)
            ->where(fn ($query) => $query
                ->where('last_seen_at', '<=', $threshold)
                ->orWhere(fn ($query) => $query->whereNull('last_seen_at')->where('created_at', '<=', $threshold)))
            ->with(['call', 'user'])
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        CallSession::query()->whereKey($stale->modelKeys())->delete();

        foreach ($stale->unique(fn (CallSession $session) => $session->channel_id.':'.$session->user_id) as $session) {
            if ($this->hasSession($session->call_id, (int) $session->user_id)) {
                continue;
            }

            ChannelMember::query()
                ->where('channels_id', $session->channel_id)
                ->where('users_id', $session->user_id)
                ->update(['call_status' => MemberCallStatus::Idle]);

            if ($session->call?->status === CallStatus::Active && $session->user) {
                broadcast(new CallParticipantLeft($session->call, $session->user))->toOthers();
            }
        }

        // Вместе с устройством закончилась и его демонстрация экрана.
        foreach ($stale->where('screen_sharing', true)->unique('user_id') as $session) {
            $stillSharing = $this->isScreenSharing($session->call_id, (int) $session->user_id);

            if ($session->call?->status === CallStatus::Active && $session->user && ! $stillSharing) {
                broadcast(new CallScreenShareStopped($session->call, $session->user))->toOthers();
            }
        }
    }

    public function isScreenSharing(string $callId, int $userId): bool
    {
        return CallSession::query()
            ->where('call_id', $callId)
            ->where('user_id', $userId)
            ->where('screen_sharing', true)
            ->exists();
    }

    /** @return Collection<int, int> */
    public function screenSharingUserIds(string $callId): Collection
    {
        return CallSession::query()
            ->where('call_id', $callId)
            ->where('screen_sharing', true)
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();
    }

    private function hasSession(string $callId, int $userId): bool
    {
        return CallSession::query()
            ->where('call_id', $callId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function broadcastScreenSharingChange(Call $call, User $user, bool $wasSharing): void
    {
        $isSharing = $this->isScreenSharing($call->call_id, (int) $user->id);

        if ($isSharing && ! $wasSharing) {
            broadcast(new CallScreenShareStarted($call, $user))->toOthers();
        } elseif ($wasSharing && ! $isSharing) {
            broadcast(new CallScreenShareStopped($call, $user))->toOthers();
        }
    }
}
