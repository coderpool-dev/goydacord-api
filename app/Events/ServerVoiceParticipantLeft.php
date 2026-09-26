<?php

namespace App\Events;

use App\Models\Conversations\Call;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerVoiceParticipantLeft implements ShouldBroadcastNow
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
        return 'server-voice.participant_left';
    }

    public function broadcastWith(): array
    {
        return [
            'server_channel_id' => $this->call->server_channel_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
        ];
    }
}
