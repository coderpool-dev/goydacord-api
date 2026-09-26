<?php

namespace App\Http\Controllers\API\Servers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servers\StoreServerAttachmentRequest;
use App\Http\Requests\Servers\StoreServerMessageRequest;
use App\Http\Requests\Servers\StoreServerStickerRequest;
use App\Http\Resources\MessageResource;
use App\Models\Servers\ServerChannel;
use App\Services\Conversations\MessageService;
use App\Services\Servers\ServerChannelReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Сообщения в текстовых каналах сервера — тот же MessageService, что у ЛС/групп (вложения,
 * стикеры, реакции, редактирование, шифрование — всё общее). Свои экшены только там, где
 * нужен ServerChannel вместо Channel в роуте (index/store/attachment/sticker); update/destroy/
 * toggleReaction переиспользуют общие messages/{message}-роуты — см. MessagePolicy.
 */
class ServerChannelMessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly ServerChannelReadService $reads,
    ) {}

    /** ?before={messageId} — сообщения старше этого (подгрузка истории при прокрутке вверх). */
    public function index(Request $request, ServerChannel $serverChannel): AnonymousResourceCollection
    {
        $this->authorize('view', $serverChannel);

        $before = $request->integer('before') ?: null;

        return MessageResource::collection($this->messages->latestInChannel(null, (int) $serverChannel->id, $before));
    }

    /** ?message_id — дочитал до него (по умолчанию до последнего сообщения канала). */
    public function markRead(Request $request, ServerChannel $serverChannel): JsonResponse
    {
        $this->authorize('view', $serverChannel);

        $upTo = $request->integer('message_id') ?: null;
        $lastRead = $this->reads->markRead($request->user(), $serverChannel, $upTo);

        return $this->successResponse('Канал отмечен прочитанным', ['last_read_message_id' => $lastRead]);
    }

    public function store(StoreServerMessageRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $this->authorize('createMessage', $channel);

        $message = $this->messages->storeText(
            $request->user(),
            null,
            (int) $channel->id,
            $request->string('message')->toString(),
            $request->replyToId(),
        );

        return $this->successResponse('Сообщение успешно отправлено', ['message' => $message->id], 201);
    }

    public function storeAttachment(StoreServerAttachmentRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $this->authorize('createMessage', $channel);

        $message = $this->messages->storeAttachment(
            $request->user(),
            null,
            (int) $channel->id,
            $request->file('file'),
            $request->string('caption')->toString(),
            $request->replyToId(),
        );

        return $this->successResponse('Файл отправлен', ['message' => $message->id], 201);
    }

    public function storeSticker(StoreServerStickerRequest $request): JsonResponse
    {
        $channel = $request->channel();
        $this->authorize('createMessage', $channel);

        $message = $this->messages->storeSticker(
            $request->user(),
            null,
            (int) $channel->id,
            $request->string('sticker')->toString(),
            $request->replyToId(),
        );

        return $this->successResponse('Стикер отправлен', ['message' => $message->id], 201);
    }
}
