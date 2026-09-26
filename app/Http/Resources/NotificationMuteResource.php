<?php

namespace App\Http\Resources;

use App\Models\Conversations\NotificationMute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationMute */
class NotificationMuteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'target_type' => $this->target_type,
            'target_id' => (int) $this->target_id,
            'muted_until' => $this->muted_until?->toISOString(),
        ];
    }
}
