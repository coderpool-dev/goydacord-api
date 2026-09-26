<?php

namespace App\Http\Resources;

use App\Models\Servers\ServerMember;
use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServerMember */
class ServerMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'nickname' => $this->nickname,
            'joined_at' => $this->joined_at,
            'voice_muted' => (bool) $this->voice_muted,
            'voice_deafened' => (bool) $this->voice_deafened,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'color' => $role->color,
                'position' => $role->position,
                'permissions' => $role->permissions,
                'is_default' => $role->is_default,
                'hoist' => (bool) $role->hoist,
                'mentionable' => (bool) $role->mentionable,
            ])),
        ];
    }
}
