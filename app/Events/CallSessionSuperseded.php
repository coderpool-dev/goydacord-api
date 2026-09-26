<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Пользователь зашёл в звонок с другого устройства/вкладки. Шлём на webrtc.{userId}
 * (его слушают ВСЕ устройства этого юзера). Побеждает session_id из winningSessionId —
 * остальные устройства, увидев несовпадение, выходят из звонка.
 */
class CallSessionSuperseded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;

    public string $callId;

    public string $winningSessionId;

    public function __construct(int $userId, string $callId, string $winningSessionId)
    {
        $this->userId = $userId;
        $this->callId = $callId;
        $this->winningSessionId = $winningSessionId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('webrtc.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'call.session_superseded';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'session_id' => $this->winningSessionId,
        ];
    }
}
