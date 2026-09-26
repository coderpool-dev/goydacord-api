<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRTCSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $signal;

    public int $toUserId;

    public function __construct(array $signal, int $toUserId)
    {
        $this->signal = $signal;
        $this->toUserId = $toUserId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('webrtc.'.$this->toUserId);
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal';
    }

    public function broadcastWith(): array
    {
        return ['signal' => $this->signal];
    }
}
