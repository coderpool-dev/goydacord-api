<?php

namespace App\Http\Resources;

use App\Models\Servers\ServerInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServerInvite */
class ServerInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'channel_id' => $this->channel_id,
            // Аватар — чтобы в настройках было видно, кто создал приглашение (как в Discord).
            // Автор мог удалить аккаунт — тогда creator null, а не падение на ->id.
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'login' => $this->creator->login,
                'avatar' => User::getAvatarUrl($this->creator->avatar, $this->creator->updated_at?->toISOString()),
            ] : null),
            'max_uses' => $this->max_uses,
            'uses' => $this->uses,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }
}
