<?php

namespace App\Http\Controllers\API\Support;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportMessage;
use App\Services\Support\SupportAttachmentService;
use App\Support\ContentDisposition;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Скриншот из обращения в поддержку. Доступ — по подписанной ссылке (middleware signed). */
class SupportAttachmentController extends Controller
{
    public function __construct(private readonly SupportAttachmentService $attachments) {}

    public function show(Request $request, SupportMessage $message, int $index = 0): BinaryFileResponse
    {
        // Кто смотрит — из подписанной ссылки: подделать параметр user нельзя.
        abort_unless($this->attachments->canView($message, (int) $request->query('user')), 403, 'Нет доступа');

        $attachment = $message->storedAttachment($index);
        $path = $attachment ? $this->attachments->streamPath($attachment) : null;

        if (! $path) {
            abort(404);
        }

        $name = $attachment['name'] ?? 'screenshot.webp';

        return response()->file($path, [
            'Content-Type' => $attachment['mime'] ?? 'image/webp',
            'Content-Disposition' => ContentDisposition::make('inline', $name, 'screenshot.webp'),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
