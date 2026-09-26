<?php

namespace App\Events;

use App\Models\Conversations\Call;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Параллель CallParticipantJoined для голосовых каналов сервера: своё вещание, т.к.
 * server_channels.id и channels.id — разные последовательности и совпадают по значению.
 */
class ServerVoiceParticipantJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call;

    public $user;

    public function __construct(Call $call, User $user)
    {
        $this->call = $call;
        $this->user = $user;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('server-voice.'.$this->call->server_channel_id)];
    }

    public function broadcastAs(): string
    {
        return 'server-voice.participant_joined';
    }

    public function broadcastWith(): array
    {
        return [
            'server_channel_id' => $this->call->server_channel_id,
            'call_id' => $this->call->call_id,
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
