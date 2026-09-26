<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $message;

    public $channelId;

    public $type;

    public $meta;

    public function __construct(User $user, $message, $channelId, $type = 'text', $meta = null)
    {
        $this->user = $user;
        $this->message = $message;
        $this->channelId = $channelId;
        $this->type = $type;
        $this->meta = $meta;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('messages.'.$this->channelId);
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
            'channels_id' => $this->channelId,
            'type' => $this->type,
            'meta' => $this->meta,
            'time' => now()->toDateTimeString(),
        ];
    }
}
