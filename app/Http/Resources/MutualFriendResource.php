<?php

namespace App\Http\Resources;

use App\Enums\FriendStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MutualFriendResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $friend */
        $friend = $this->resource;

        return [
            'id' => $friend->id,
            'login' => $friend->login,
            'name' => $friend->name,
            'avatar' => User::getAvatarUrl($friend->avatar, $friend->updated_at?->toISOString()),
            'banner' => User::getBannerUrl($friend->banner, $friend->updated_at?->toISOString()),
            'banner_color' => $friend->banner_color,
            'presence' => $friend->presence ?? 'online',
            'status_emoji' => $friend->status_emoji,
            'status_text' => $friend->status_text,
            'game_status_text' => $friend->game_status_text,
            'online' => $friend->isOnline(),
            'status' => FriendStatus::Accepted,
            'status_display' => 'друзья',
            'direction' => 'received',
        ];
    }
}
