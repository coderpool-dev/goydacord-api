<?php

namespace App\Http\Resources;

use App\Models\Servers\ServerRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServerRole */
class ServerRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'permissions' => $this->permissions,
            'is_default' => $this->is_default,
            'hoist' => (bool) $this->hoist,
            'mentionable' => (bool) $this->mentionable,
        ];
    }
}
