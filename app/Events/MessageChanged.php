<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $channelId,
        public readonly int $messageId,
        public readonly string $action
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('messages.'.$this->channelId);
    }

    public function broadcastAs(): string
    {
        return 'message.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'channels_id' => $this->channelId,
            'message_id' => $this->messageId,
            'action' => $this->action,
        ];
    }
}
