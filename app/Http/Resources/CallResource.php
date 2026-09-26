<?php

namespace App\Http\Resources;

use App\Models\Conversations\Call;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Call */
class CallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'call_id' => $this->call_id,
            'channel_id' => $this->channel_id,
            'initiator_id' => $this->initiator_id,
            'created_at' => $this->created_at,
            'status' => $this->status,
            'type' => 'voice',
            'active_count' => $this->when(isset($this->active_participants), fn () => $this->active_participants->count()),
            'participants' => $this->when(
                isset($this->active_participants),
                fn () => CallParticipantResource::collection($this->active_participants)
            ),
        ];
    }
}
