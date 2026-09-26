<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Пользователя упомянули в канале сервера (лично, ролью, @everyone или @here). Шлём на
 * личный webrtc.{userId} — клиент показывает уведомление и счётчик на сервере/канале.
 * excerpt — уже с «@ник» вместо токенов: у получателя может не быть списка участников.
 */
class ServerMentioned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $serverId,
        public string $serverName,
        public int $serverChannelId,
        public string $channelName,
        public int $messageId,
        public int $authorId,
        public string $authorName,
        public ?string $authorAvatar,
        public string $excerpt,
        /** Упомянули лично (<@id>), а не ролью/@everyone — такое доходит и в заглушённом сервере. */
        public bool $direct = false,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('webrtc.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'server.mention';
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,
            'server_name' => $this->serverName,
            'server_channel_id' => $this->serverChannelId,
            'channel_name' => $this->channelName,
            'message_id' => $this->messageId,
            'author' => ['id' => $this->authorId, 'name' => $this->authorName, 'avatar' => $this->authorAvatar],
            'excerpt' => $this->excerpt,
            'direct' => $this->direct,
        ];
    }
}
