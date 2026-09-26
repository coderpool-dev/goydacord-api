<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Поддержка ответила пользователю. Шлём на webrtc.{userId} (его слушают все устройства
 * пользователя): открытый чат поддержки сразу показывает ответ, остальные экраны — тост
 * и счётчик непрочитанных на пункте «Поддержка».
 */
class SupportReplyPosted implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param  array<string, mixed>  $message  SupportMessageResource */
    public function __construct(
        public readonly int $userId,
        public readonly array $message,
        public readonly int $unread,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('webrtc.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'support.reply';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'unread' => $this->unread,
        ];
    }
}
