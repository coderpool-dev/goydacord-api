<?php

namespace App\Http\Resources;

use App\Models\Conversations\CallSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CallSession */
class ServerVoiceParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user->id,
            'login' => $this->user->login,
            // Ник на сервере (display_name — динамическое свойство из participants()), иначе имя аккаунта.
            'name' => ($this->display_name ?? null) ?: ($this->user->name ?? $this->user->login),
            'joined_at' => $this->last_seen_at ?? $this->created_at,
            'screen_sharing' => (bool) $this->screen_sharing,
            'is_screen_sharing' => (bool) $this->screen_sharing,
            'avatar_url' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
            'banner' => User::getBannerUrl($this->user->banner, $this->user->updated_at?->toISOString()),
            'banner_color' => $this->user->banner_color,
            // Метка последнего кадра демонстрации — считает ServerChannelCallService::participants()
            // и навешивает динамическим свойством (в CallSession такой колонки нет).
            'screen_preview_at' => $this->screen_preview_at ?? null,
            // Тоже динамические свойства из ServerChannelCallService::participants().
            'voice_muted' => (bool) ($this->voice_muted ?? false),
            'voice_deafened' => (bool) ($this->voice_deafened ?? false),
            'priority_speaker' => (bool) ($this->priority_speaker ?? false),
        ];
    }
}
