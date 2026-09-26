<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameIconUploaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $name,
        public string $slug,
        public string $iconUrl,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('game-icons')];
    }

    public function broadcastAs(): string
    {
        return 'game.icon_uploaded';
    }

    public function broadcastWith(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'icon_url' => $this->iconUrl,
        ];
    }
}
