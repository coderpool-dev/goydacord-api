<?php

namespace App\Http\Resources;

use App\Enums\ServerPermission;
use App\Models\Servers\ServerChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServerChannel */
class ServerChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'kind' => $this->kind,
            'topic' => $this->topic,
            'position' => $this->position,
            // Только у текстовых каналов в списке: непрочитано, если last_message_id > last_read_message_id.
            'last_message_id' => $this->when(isset($this->last_message_id), fn () => $this->last_message_id),
            'last_read_message_id' => $this->when(isset($this->last_read_message_id), fn () => $this->last_read_message_id),
            'active_count' => $this->when(isset($this->active_participants), fn () => $this->active_participants->count()),
            'active_participants' => $this->when(
                isset($this->active_participants),
                fn () => ServerVoiceParticipantResource::collection($this->active_participants)
            ),
            'overwrites' => $this->whenLoaded('roleOverwrites', fn () => $this->roleOverwrites->map(fn ($overwrite) => [
                'role_id' => $overwrite->server_role_id,
                'allow' => $overwrite->allow,
                'deny' => $overwrite->deny,
            ])),
            'member_overwrites' => $this->whenLoaded('memberOverwrites', fn () => $this->memberOverwrites->map(fn ($overwrite) => [
                'member_id' => $overwrite->server_member_id,
                'allow' => $overwrite->allow,
                'deny' => $overwrite->deny,
            ])),
            'is_restricted' => $this->whenLoaded('roleOverwrites', fn () => $this->roleOverwrites->isNotEmpty()
                || ($this->relationLoaded('memberOverwrites') && $this->memberOverwrites->isNotEmpty())),
            'can_view' => $this->when(isset($this->my_permissions), fn () => ServerPermission::has($this->my_permissions, ServerPermission::VIEW_CHANNELS)),
            'can_send_messages' => $this->when(isset($this->my_permissions), fn () => ServerPermission::has($this->my_permissions, ServerPermission::SEND_MESSAGES)),
            // Фронт по нему показывает «Удалить» на чужих сообщениях (сама проверка — MessagePolicy::delete).
            'can_manage_messages' => $this->when(isset($this->my_permissions), fn () => ServerPermission::has($this->my_permissions, ServerPermission::MANAGE_MESSAGES)),
            'can_connect_voice' => $this->when(isset($this->my_permissions), fn () => ServerPermission::has($this->my_permissions, ServerPermission::CONNECT_VOICE)),
            'can_speak' => $this->when(isset($this->my_permissions), fn () => ServerPermission::has($this->my_permissions, ServerPermission::SPEAK)),
        ];
    }
}
