<?php

namespace App\Events;

use App\Models\Conversations\Call;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallParticipantJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call;

    public $user;

    public bool $resumed;

    /**
     * $resumed=true — это не первый вход в звонок, а повтор: heartbeat восстановил
     * пропавшую presence-сессию, либо accept() пришёл, когда участник уже отмечен
     * «в звонке» (retry, смена устройства или клиент молча перезагрузился и зовёт
     * accept заново, не послав leave). Фронт на этот флаг принудительно пересоздаёт
     * P2P-соединение с этим участником — оно могло протухнуть незаметно для остальных.
     */
    public function __construct(Call $call, User $user, bool $resumed = false)
    {
        $this->call = $call;
        $this->user = $user;
        $this->resumed = $resumed;
    }

    public function broadcastOn(): array
    {
        // тот же канал, что и у CallCreated
        return [new PrivateChannel('channel.'.$this->call->channel_id)];
    }

    public function broadcastAs(): string
    {
        return 'call.participant_joined';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->call->channel_id,
            'resumed' => $this->resumed,
            'user' => [
                'id' => $this->user->id,
                'login' => $this->user->login,
                'name' => $this->user->name ?? $this->user->login,
                'avatar' => $this->user->avatar,
                'avatar_url' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
            ],
        ];
    }
}
