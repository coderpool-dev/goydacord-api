<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Параллель MessageChanged для текстовых каналов сервера — см. ServerChannelMessageSent. */
class ServerChannelMessageChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $serverChannelId,
        public readonly int $messageId,
        public readonly string $action
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('server-messages.'.$this->serverChannelId);
    }

    public function broadcastAs(): string
    {
        return 'message.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'server_channel_id' => $this->serverChannelId,
            'message_id' => $this->messageId,
            'action' => $this->action,
        ];
    }
}
