<?php

namespace App\Http\Resources;

use App\Models\Support\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin SupportMessage */
class SupportMessageResource extends JsonResource
{
    public const ATTACHMENT_URL_TTL_HOURS = 24;

    public function toArray(Request $request): array
    {
        $attachments = $this->attachmentsFor($request);

        return [
            'id' => (int) $this->id,
            'body' => (string) $this->body,
            'is_staff' => (bool) $this->is_staff,
            'author_name' => $this->is_staff
                ? 'Администрация'
                : (($this->user?->name ?: $this->user?->login) ?: 'Пользователь'),
            'user_id' => $this->user_id ? (int) $this->user_id : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'attachment' => $attachments[0] ?? null,
            'attachments' => $attachments,
        ];
    }

    /** Скриншоты открываются по подписанной ссылке, привязанной к тому, кто смотрит. */
    private function attachmentsFor(Request $request): array
    {
        // Срок округлён до часа, чтобы адрес не менялся на каждом запросе и картинка бралась из кэша.
        $expiresAt = now()->startOfHour()->addHours(self::ATTACHMENT_URL_TTL_HOURS + 1);
        $viewer = $request->user() ? ['user' => $request->user()->id] : [];

        return collect($this->storedAttachments())
            ->map(fn (array $item, int $index) => [
                'kind' => 'image',
                'mime' => $item['mime'] ?? 'image/webp',
                'name' => $item['name'] ?? 'screenshot.webp',
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                'url' => URL::temporarySignedRoute('support.attachments.show', $expiresAt, [
                    'message' => $this->id,
                    'index' => $index,
                    ...$viewer,
                ]),
            ])
            ->values()
            ->all();
    }
}
