<?php

namespace App\Http\Resources;

use App\Models\Conversations\Channel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Краткие данные беседы — там, где список участников не нужен.
 *
 * @mixin Channel
 */
class ChannelSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'status' => $this->status->value,
            'avatar' => $this->avatar
                ? User::getAvatarUrl((string) $this->avatar, null)
                : null,
        ];
    }
}
