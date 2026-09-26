<?php

namespace App\Http\Resources;

use App\Models\Conversations\Message;
use App\Models\User;
use App\Support\YandexPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public const ATTACHMENT_URL_TTL_HOURS = 24;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'channels_id' => $this->channels_id,
            'type' => $this->type ?? 'text',
            'message' => $this->message,
            'reply_to_id' => $this->reply_to_id,
            'reply_to' => $this->buildReply(),
            'edited_at' => $this->edited_at,
            'reactions' => $this->buildReactions($request),
            // meta наружу отдаём только для системных событий: у вложений в meta лежит
            // внутренний disk_path, его светить нельзя — вместо этого отдаём attachment.
            'meta' => $this->type === 'system' ? $this->meta : null,
            // id стикера из встроенного каталога — картинку резолвит фронт.
            'sticker' => $this->type === 'sticker' && is_array($this->meta) ? ($this->meta['sticker'] ?? null) : null,
            'attachment' => $this->buildAttachment($request),
            // Кого сообщение упоминает (только каналы сервера) — для подсветки упоминаний и «меня упомянули».
            'mentions' => $this->server_channel_id !== null ? $this->mentions : null,
            'created_at' => $this->created_at,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'login' => $this->user->login,
                'avatar' => User::getAvatarUrl($this->user->avatar, $this->user->updated_at?->toISOString()),
                // Профильные поля автора — чтобы клик по имени открывал карточку профиля.
                'banner' => User::getBannerUrl($this->user->banner, $this->user->updated_at?->toISOString()),
                'banner_color' => $this->user->banner_color,
                'presence' => $this->user->presence ?? 'online',
                'online' => $this->user->isOnline(),
                'status_emoji' => $this->user->status_emoji,
                'status_text' => $this->user->status_text,
                'game_status_text' => $this->user->game_status_text,
                'music_status_text' => $this->user->music_status_text,
                'yandex_music_now_playing' => YandexPresence::nowPlaying($this->user),
            ],
        ];
    }

    private function buildReply(): ?array
    {
        if (! $this->replyTo) {
            return null;
        }

        return [
            'id' => $this->replyTo->id,
            'message' => $this->replyTo->message,
            'type' => $this->replyTo->type ?? 'text',
            'sticker' => $this->replyTo->type === 'sticker' && is_array($this->replyTo->meta)
                ? ($this->replyTo->meta['sticker'] ?? null) : null,
            'user' => [
                'id' => $this->replyTo->user->id,
                'name' => $this->replyTo->user->name,
                'avatar' => User::getAvatarUrl(
                    $this->replyTo->user->avatar,
                    $this->replyTo->user->updated_at?->toISOString()
                ),
            ],
        ];
    }

    private function buildReactions(Request $request): array
    {
        return $this->reactions
            ->groupBy('emoji')
            ->map(fn ($reactions, $emoji) => [
                'emoji' => $emoji,
                'count' => $reactions->count(),
                'reacted' => $reactions->contains(
                    fn ($reaction) => (int) $reaction->user_id === (int) $request->user()?->id
                ),
            ])
            ->values()
            ->all();
    }

    private function buildAttachment(Request $request): ?array
    {
        if (! in_array($this->type, ['image', 'file'], true)) {
            return null;
        }

        $attachment = is_array($this->meta) ? ($this->meta['attachment'] ?? null) : null;

        if (! $attachment) {
            return null;
        }

        $viewerId = (int) $request->user()?->id;
        $parameters = ['message' => $this->id];
        if ($viewerId > 0) {
            $parameters['user'] = $viewerId;
        }

        return [
            // Срок округлён до часа: иначе адрес меняется на каждом запросе списка,
            // и браузер заново скачивает все картинки чата.
            'url' => URL::temporarySignedRoute(
                'attachments.show',
                now()->startOfHour()->addHours(self::ATTACHMENT_URL_TTL_HOURS + 1),
                $parameters,
            ),
            'name' => $attachment['name'] ?? 'file',
            'mime' => $attachment['mime'] ?? 'application/octet-stream',
            'size' => $attachment['size'] ?? 0,
            'kind' => $attachment['kind'] ?? 'file',
            'width' => $attachment['width'] ?? null,
            'height' => $attachment['height'] ?? null,
        ];
    }
}
