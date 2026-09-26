<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Модератор изменил голосовое состояние участника: заглушил/отключил звук на сервере,
 * переместил в другой голосовой канал или отключил от голоса. Шлём на личный
 * webrtc.{userId} — клиент применяет сам (P2P-звук сервер физически не контролирует).
 *
 * action: "state" | "move" | "disconnect".
 */
class ServerVoiceModerated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $serverId,
        public string $action,
        public bool $voiceMuted,
        public bool $voiceDeafened,
        public ?int $serverChannelId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('webrtc.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'server-voice.moderated';
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,
            'action' => $this->action,
            'voice_muted' => $this->voiceMuted,
            'voice_deafened' => $this->voiceDeafened,
            'server_channel_id' => $this->serverChannelId,
        ];
    }
}
