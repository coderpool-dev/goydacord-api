<?php

namespace App\Http\Controllers\API\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Http\Resources\SupportMessageResource;
use App\Http\Resources\SupportThreadResource;
use App\Services\Support\SupportThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Чат пользователя с поддержкой: у каждого пользователя одно обращение. */
class SupportThreadController extends Controller
{
    public function __construct(private readonly SupportThreadService $support) {}

    public function show(Request $request): JsonResponse
    {
        $thread = $this->support->openThread($this->support->findOrCreateThreadForUser($request->user()));

        return $this->successResponse('Поддержка', [
            'thread' => (new SupportThreadResource($thread))->resolve($request),
            'messages' => SupportMessageResource::collection($thread->messages)->resolve($request),
        ]);
    }

    /** Счётчик для пункта «Поддержка» в меню: сколько ответов поддержки ещё не открыто. */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse('Непрочитанные ответы', [
            'unread' => $this->support->unreadMessageCountForUser($request->user()),
        ]);
    }

    public function storeMessage(StoreSupportMessageRequest $request): JsonResponse
    {
        $message = $this->support->postUserMessage($request->user(), $request->body(), $request->uploadedFiles());

        return $this->successResponse('Сообщение отправлено', [
            'message' => (new SupportMessageResource($message))->resolve($request),
        ], 201);
    }
}
