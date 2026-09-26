<?php

namespace App\Http\Resources;

use App\Models\Servers\ServerBan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServerBan */
class ServerBanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'login' => $this->user->login,
            ],
            'banned_by' => $this->whenLoaded('bannedBy', fn () => [
                'id' => $this->bannedBy->id,
                'name' => $this->bannedBy->name,
                'login' => $this->bannedBy->login,
            ]),
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
