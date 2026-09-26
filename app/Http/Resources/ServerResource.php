<?php

namespace App\Http\Resources;

use App\Models\Servers\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Server */
class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon ? Storage::disk('public')->url('server-icons/'.$this->icon) : null,
            'banner' => $this->banner ? Storage::disk('public')->url('server-banners/'.$this->banner) : null,
            'banner_color' => $this->banner_color,
            'owner_id' => $this->owner_id,
            'tags' => $this->tags ?? [],
            'members_count' => $this->when(
                $this->relationLoaded('members'),
                fn () => $this->members->count(),
            ),
            'is_owner' => $this->when(isset($this->is_owner), fn () => $this->is_owner),
            'my_permissions' => $this->when(isset($this->my_permissions), fn () => $this->my_permissions),
            'my_top_position' => $this->when(isset($this->is_owner), fn () => $this->my_top_position),
            'has_unread' => $this->when(isset($this->has_unread), fn () => $this->has_unread),
            'created_at' => $this->created_at,
        ];
    }
}
