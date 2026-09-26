<?php

namespace App\Http\Controllers\API\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkPreview\ShowLinkPreviewRequest;
use App\Services\Integrations\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/** Превью ссылки собирает сервер, чтобы чужой сайт не видел IP пользователя. */
class LinkPreviewController extends Controller
{
    public function __construct(private readonly LinkPreviewService $linkPreviews) {}

    public function show(ShowLinkPreviewRequest $request): JsonResponse
    {
        try {
            $preview = $this->linkPreviews->preview($request->validated('url'));
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('Предпросмотр ссылки', $preview);
    }
}
