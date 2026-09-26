<?php

namespace App\Http\Resources;

use App\Enums\MembershipStatus;
use App\Models\Conversations\Channel;
use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Channel */
class ChannelResource extends JsonResource
{
    public function __construct($resource, private readonly ?User $currentUser = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        // Выход из группы не удаляет строку, а ставит статус MembershipStatus::Removed,
        // и связь members отдаёт её наравне с остальными. Без этого фильтра
        // вышедшие оставались и в счётчике, и в списке участников.
        $activeMembers = $this->whenLoaded(
            'members',
            fn () => $this->members->filter(
                fn ($member) => $member->status !== MembershipStatus::Removed
            ),
            null,
        );

        $otherMembers = $activeMembers === null ? [] : $activeMembers
            ->filter(fn ($member) => $this->currentUser === null || $member->users_id != $this->currentUser->id)
            ->filter(fn ($member) => $member->user !== null)
            ->map(function ($member) {
                $memberUser = $member->user;

                return [
                    'id' => $memberUser->id,
                    'name' => $memberUser->name,
                    'login' => $memberUser->login,
                    'avatar' => User::getAvatarUrl($memberUser->avatar, $memberUser->updated_at?->toISOString()),
                    'banner' => User::getBannerUrl($memberUser->banner, $memberUser->updated_at?->toISOString()),
                    'banner_color' => $memberUser->banner_color,
                    'presence' => $memberUser->presence ?? 'online',
                    'online' => $memberUser->isOnline(),
                    'status_emoji' => $memberUser->status_emoji,
                    'status_text' => $memberUser->status_text,
                    'game_status_text' => $memberUser->game_status_text,
                    'music_status_text' => $memberUser->music_status_text,
                    'yandex_music_now_playing' => YandexPresence::nowPlaying($memberUser),
                ];
            })
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'avatar' => $this->avatar
                ? User::getAvatarUrl((string) $this->avatar)
                : null,
            'members_count' => $activeMembers === null ? 0 : $activeMembers->count(),
            'other_members' => $otherMembers,
            'is_member' => $this->currentUser !== null,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'missed_calls' => (int) ($this->missed_calls ?? 0),
            // Для разделителя «Новые сообщения»: всё, что новее, — непрочитанное.
            'last_read_message_id' => $this->currentUser === null || ! $this->relationLoaded('members')
                ? null
                : (int) ($this->members->firstWhere('users_id', $this->currentUser->id)->last_read_message_id ?? 0),
            'last_activity_at' => $this->messages_max_created_at ?? null,
        ];
    }
}
