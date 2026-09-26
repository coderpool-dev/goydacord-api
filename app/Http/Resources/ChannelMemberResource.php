<?php

namespace App\Http\Resources;

use App\Enums\MembershipStatus;
use App\Models\Conversations\ChannelMember;
use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChannelMember */
class ChannelMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'login' => $this->user->login,
                'avatar' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
                'banner' => User::getBannerUrl($this->user->banner, $this->user->updated_at?->toISOString()),
                'banner_color' => $this->user->banner_color,
                'presence' => $this->user->presence ?? 'online',
                'online' => $this->user->isOnline(),
                'status_emoji' => $this->user->status_emoji,
                'status_text' => $this->user->status_text,
                'game_status_text' => $this->user->game_status_text,
                'music_status_text' => $this->user->music_status_text,
                'yandex_music_now_playing' => YandexPresence::nowPlaying($this->user),
            ],
            'role' => $this->status === MembershipStatus::Admin ? 'admin' : 'member',
        ];
    }
}
