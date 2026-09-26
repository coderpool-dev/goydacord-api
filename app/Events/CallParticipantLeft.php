<?php

namespace App\Events;

use App\Models\Conversations\Call;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallParticipantLeft implements ShouldBroadcastNow
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
        return [new PrivateChannel('channel.'.$this->call->channel_id)];
    }

    public function broadcastAs(): string
    {
        return 'call.participant_left';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->call->channel_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
        ];
    }
}
