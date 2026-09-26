<?php

namespace App\Http\Controllers\API\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\StoreAttachmentRequest;
use App\Http\Requests\Messages\StoreMessageRequest;
use App\Http\Requests\Messages\StoreStickerRequest;
use App\Http\Requests\Messages\ToggleReactionRequest;
use App\Http\Requests\Messages\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversations\Channel;
use App\Models\Conversations\Message;
use App\Services\Conversations\MessageService;
use App\Services\Servers\ServerAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly ServerAuditLogService $audit,
    ) {}

    /** ?before={messageId} — сообщения старше этого (подгрузка истории при прокрутке вверх). */
    public function index(Request $request, Channel $channel): AnonymousResourceCollection
    {
        $this->authorize('view', $channel);

        $before = $request->integer('before') ?: null;

        return MessageResource::collection($this->messages->latestInChannel((int) $channel->id, null, $before));
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $this->authorize('createMessage', $channel);

        $message = $this->messages->storeText(
            $request->user(),
            (int) $channel->id,
            null,
            $request->string('message')->toString(),
            $request->replyToId(),
        );

        return $this->successResponse('Сообщение успешно отправлено', ['message' => $message->id], 201);
    }

    public function storeAttachment(StoreAttachmentRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $this->authorize('createMessage', $channel);

        $message = $this->messages->storeAttachment(
            $request->user(),
            (int) $channel->id,
            null,
            $request->file('file'),
            $request->string('caption')->toString(),
            $request->replyToId(),
        );

        return $this->successResponse('Файл отправлен', ['message' => $message->id], 201);
    }

    public function storeSticker(StoreStickerRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $this->authorize('createMessage', $channel);

        $message = $this->messages->storeSticker(
            $request->user(),
            (int) $channel->id,
            null,
            $request->string('sticker')->toString(),
            $request->replyToId(),
        );

        return $this->successResponse('Стикер отправлен', ['message' => $message->id], 201);
    }

    public function recentStickers(Request $request): JsonResponse
    {
        return $this->successResponse('Недавние стикеры загружены', [
            'stickers' => $this->messages->recentStickers($request->user()),
        ]);
    }

    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);

        $this->messages->updateText($message, $request->string('message')->toString());

        return $this->successResponse('Сообщение изменено');
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        // Модератор удалил чужое сообщение на сервере — в журнал аудита (как в Discord). Своё — нет.
        $moderated = $message->server_channel_id !== null && (int) $message->user_id !== (int) $request->user()->id;
        $channel = $moderated ? $message->serverChannel : null;
        $author = $moderated ? $message->user : null;

        $this->messages->delete($message);

        if ($channel && $author) {
            $this->audit->record($channel->server_id, $request->user(), 'message.delete', 'user', $author->id,
                $this->audit->userLabel($channel->server_id, $author), ['channel' => ['new' => $channel->name]]);
        }

        return $this->successResponse('Сообщение удалено');
    }

    public function toggleReaction(ToggleReactionRequest $request, Message $message): JsonResponse
    {
        $this->authorize('react', $message);

        $reacted = $this->messages->toggleReaction(
            $request->user(),
            $message,
            $request->string('emoji')->toString(),
        );

        return $this->successResponse('Реакция обновлена', ['reacted' => $reacted]);
    }
}
