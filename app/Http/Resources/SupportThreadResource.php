<?php

namespace App\Http\Resources;

use App\Models\Support\SupportThread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Обращение в поддержку. unread заполняет SupportThreadService: в списке админки — подзапросом,
 * у открытой переписки — false, потому что её только что прочитали.
 *
 * @mixin SupportThread
 */
class SupportThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'category' => $this->category ?? SupportThread::CATEGORY_INBOX,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user ? [
                'id' => (int) $this->user->id,
                'login' => (string) $this->user->login,
                'name' => (string) $this->user->name,
                'avatar' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
            ] : null,
            'unread' => (bool) $this->unread,
            'last_message' => $this->whenLoaded(
                'latestMessage',
                fn () => $this->latestMessage ? new SupportMessageResource($this->latestMessage) : null,
            ),
        ];
    }
}
