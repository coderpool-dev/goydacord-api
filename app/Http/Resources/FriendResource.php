<?php

namespace App\Http\Resources;

use App\Enums\FriendStatus;
use App\Models\Social\Friend;
use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Friend */
class FriendResource extends JsonResource
{
    public function __construct(Friend $resource, private readonly User $currentUser)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $isSentByMe = $this->users_id == $this->currentUser->id;
        $friendUser = $isSentByMe ? $this->friend : $this->user;
        $canSeePrivateActivity = $this->status === FriendStatus::Accepted;

        return [
            'id' => $friendUser->id,
            'login' => $friendUser->login ?? 'Неизвестно',
            'name' => $friendUser->name ?? null,
            'status' => $this->status,
            'status_display' => $this->statusDisplay($isSentByMe),
            'direction' => $isSentByMe ? 'sent' : 'received',
            'created_at' => $this->created_at->toIso8601String(),
            'is_current_user_sender' => $isSentByMe,
            'avatar' => User::getAvatarUrl($friendUser->avatar, $friendUser->updated_at?->toISOString()),
            'banner' => User::getBannerUrl($friendUser->banner, $friendUser->updated_at?->toISOString()),
            'banner_color' => $friendUser->banner_color,
            'presence' => $friendUser->presence ?? 'online',
            'status_emoji' => $friendUser->status_emoji,
            'status_text' => $friendUser->status_text,
            'game_status_text' => $canSeePrivateActivity ? $friendUser->game_status_text : null,
            'music_status_text' => $canSeePrivateActivity ? $friendUser->music_status_text : null,
            'yandex_music_now_playing' => $canSeePrivateActivity
                ? YandexPresence::nowPlaying($friendUser)
                : null,
            'online' => $friendUser->isOnline(),
        ];
    }

    private function statusDisplay(bool $isSentByMe): string
    {
        return match ($this->status) {
            FriendStatus::Pending => $isSentByMe ? 'заявка отправлена' : 'заявка получена',
            FriendStatus::Accepted => 'друзья',
            FriendStatus::Rejected => 'отклонено',
            FriendStatus::Blocked => 'заблокировано',
        };
    }
}
