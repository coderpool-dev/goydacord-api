<?php

namespace App\Http\Resources;

use App\Models\Conversations\ChannelMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChannelMember */
class CallParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user->id,
            'login' => $this->user->login,
            'name' => $this->user->name ?? $this->user->login,
            'joined_at' => $this->updated_at,
            'screen_sharing' => (bool) ($this->screen_sharing ?? false),
            'is_screen_sharing' => (bool) ($this->screen_sharing ?? false),
            // Метка последнего кадра демонстрации: null — превью нет, иначе фронт
            // грузит миниатюру и по значению обновляет её, пока демка идёт.
            'screen_preview_at' => $this->screen_preview_at ?? null,
            'avatar_url' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
        ];
    }
}
