<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Отдельное событие, а не переиспользование MessageSent: у server_channels и channels
 * разные последовательности id, "messages.{id}" пересёкся бы с чужим каналом того же id.
 */
class ServerChannelMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $message;

    public $serverChannelId;

    public $type;

    public $meta;

    public function __construct(User $user, $message, $serverChannelId, $type = 'text', $meta = null)
    {
        $this->user = $user;
        $this->message = $message;
        $this->serverChannelId = $serverChannelId;
        $this->type = $type;
        $this->meta = $meta;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('server-messages.'.$this->serverChannelId);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'login' => $this->user->login,
                'avatar' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
            ],
            'message' => $this->message,
            'server_channel_id' => $this->serverChannelId,
            'type' => $this->type,
            'meta' => $this->meta,
            'time' => now()->toDateTimeString(),
        ];
    }
}
