<?php

namespace App\Services\Conversations;

use App\Events\WebRTCSignal;
use App\Exceptions\ApiException;
use App\Models\Conversations\Call;
use App\Models\User;
use App\Services\Servers\ServerChannelCallService;
use Illuminate\Support\Collection;

/** Пересылка WebRTC-сигналов (offer/answer/ICE) между участниками звонка. */
class SignalingService
{
    public function __construct(
        private readonly ChannelService $channels,
        private readonly ServerChannelCallService $serverCalls,
    ) {}

    /** Без получателя сигнал уходит всем остальным участникам канала. */
    public function relay(User $sender, Call $call, array $signal, ?int $recipientId): void
    {
        $recipientIds = $recipientId !== null
            ? $this->singleRecipient($sender, $call, $recipientId)
            : $this->otherMembers($sender, $call);

        if ($recipientIds->isEmpty()) {
            throw new ApiException('В канале нет других участников', 422);
        }

        $payload = [
            'from_user_id' => $sender->id,
            ...$signal,
            'call_id' => $call->call_id,
        ];

        foreach ($recipientIds as $userId) {
            event(new WebRTCSignal($payload, $userId));
        }
    }

    /** @return Collection<int, int> */
    private function singleRecipient(User $sender, Call $call, int $recipientId): Collection
    {
        $isMember = $call->server_channel_id !== null
            ? $this->serverCalls->activeParticipantUserIds((int) $call->server_channel_id)->contains($recipientId)
            : $this->channels->isMember($recipientId, (int) $call->channel_id);

        if (! $isMember) {
            throw new ApiException('Получатель не участник этого канала', 403);
        }

        // Блокировка в любую сторону запрещает соединение, иначе его можно
        // установить в обход блокировки.
        if ($sender->isBlockedWith($recipientId)) {
            throw new ApiException('Пользователь заблокирован', 403);
        }

        return collect([$recipientId]);
    }

    /**
     * @return Collection<int, int>
     *
     * Для голосового канала сервера — не все участники сервера, а те, кто сейчас реально
     * в этом голосовом (CallSession), иначе ICE-кандидаты сыпались бы всему серверу.
     */
    private function otherMembers(User $sender, Call $call): Collection
    {
        $memberIds = $call->server_channel_id !== null
            ? $this->serverCalls->activeParticipantUserIds((int) $call->server_channel_id)
            : $this->channels->activeMemberIds((int) $call->channel_id);

        return $memberIds
            ->reject(fn (int $userId) => $userId === (int) $sender->id || $sender->isBlockedWith($userId))
            ->values();
    }
}
