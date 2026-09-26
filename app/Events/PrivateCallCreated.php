<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateCallCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $call;

    protected int $userId;

    public function __construct(array $callData, int $userId)
    {
        $this->call = $callData;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('webrtc.'.$this->userId)];
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
