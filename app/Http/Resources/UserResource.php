<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'login' => $this->login,
            'date' => $this->date,
            'avatar' => User::getAvatarUrl($this->avatar, $this->updated_at?->toISOString()),
            'banner' => User::getBannerUrl($this->banner, $this->updated_at?->toISOString()),
            'banner_color' => $this->banner_color,
            'presence' => $this->presence ?? 'online',
            'status_emoji' => $this->status_emoji,
            'status_text' => $this->status_text,
            'game_status_text' => $this->game_status_text,
            'music_status_text' => $this->music_status_text,
            'yandex_music_now_playing' => YandexPresence::nowPlaying($this->resource),
            'yandex_music_connected' => YandexPresence::isConnected($this->resource),
            'email_verified_at' => $this->email_verified_at,
            'email_verified' => $this->email_verified_at !== null,
            'is_admin' => $this->resource instanceof User
                ? $this->resource->isAdmin()
                : false,
            // Временный аккаунт демо-входа: фронт показывает плашку «демо, удалится ...».
            'is_demo' => $this->resource instanceof User && $this->resource->isDemoGuest(),
            'demo_expires_at' => $this->resource instanceof User
                ? $this->resource->demoExpiresAt()?->toISOString()
                : null,
            'updated_at' => $this->updated_at,
        ];
    }
}
