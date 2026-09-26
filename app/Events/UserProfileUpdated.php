<?php

namespace App\Events;

use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UserProfileUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $channelIds
    ) {}

    public function broadcastOn(): array
    {
        return $this->channelIds
            ->unique()
            ->map(fn (int $channelId) => new PrivateChannel('channel.'.$channelId))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'user.profile_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'login' => $this->user->login,
                'avatar' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
                'status_text' => $this->user->status_text,
                'game_status_text' => $this->user->game_status_text,
                'music_status_text' => $this->user->music_status_text,
                'yandex_music_now_playing' => YandexPresence::nowPlaying($this->user),
                'status_emoji' => $this->user->status_emoji,
                'presence' => $this->user->presence ?? 'online',
            ],
        ];
    }
}
