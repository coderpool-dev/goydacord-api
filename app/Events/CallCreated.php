<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $call;

    public function __construct(array $callData)
    {
        $this->call = $callData;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('channel.'.$this->call['channel_id'])];
    }

    public function broadcastAs(): string
    {
        return 'call.created';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call['call_id'],
            'channel_id' => $this->call['channel_id'],
            'initiator_id' => $this->call['initiator_id'],
            'initiator_login' => $this->call['initiator_login'] ?? null,
            'status' => $this->call['status'],
            'type' => $this->call['type'] ?? 'voice',
            'silent_user_ids' => $this->call['silent_user_ids'] ?? [],
        ];
    }
}
