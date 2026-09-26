<?php

namespace App\Http\Controllers\API\Uploads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\DestroyAttachmentsRequest;
use App\Models\Conversations\Attachment;
use App\Services\Conversations\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Раздел «Хранилище»: сколько места занимают файлы пользователя и их удаление. */
class StorageController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function show(Request $request): JsonResponse
    {
        $files = $this->attachments->filesFor($request->user());

        return $this->successResponse('Данные о хранилище получены', [
            'used_bytes' => (int) $files->sum('size'),
            'quota_bytes' => (int) config('uploads.user_quota_bytes'),
            'files' => $files,
        ]);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->attachments->deleteWithMessage($attachment);

        return $this->successResponse('Файл удалён');
    }

    public function destroyMany(DestroyAttachmentsRequest $request): JsonResponse
    {
        $deleted = $this->attachments->deleteOwnedWithMessages($request->user(), $request->validated('ids'));

        return $this->successResponse('Файлы удалены', ['deleted' => $deleted]);
    }
}
