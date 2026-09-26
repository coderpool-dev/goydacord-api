<?php

namespace App\Events;

use App\Models\Conversations\Call;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallScreenShareStopped implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Call $call,
        public readonly User $user,
    ) {}

    public function broadcastOn(): array
    {
        // Голосовые каналы сервера не имеют channel_id — вещаем на их отдельный
        // приватный канал (server_channels.id/channels.id — разные последовательности).
        $channelName = $this->call->server_channel_id !== null
            ? 'server-voice.'.$this->call->server_channel_id
            : 'channel.'.$this->call->channel_id;

        return [new PrivateChannel($channelName)];
    }

    public function broadcastAs(): string
    {
        return $this->call->server_channel_id !== null ? 'server-voice.screen_share_stopped' : 'call.screen_share_stopped';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call->call_id,
            'channel_id' => $this->call->channel_id,
            'server_channel_id' => $this->call->server_channel_id,
            'user_id' => $this->user->id,
            'screen_sharing' => false,
        ];
    }
}
